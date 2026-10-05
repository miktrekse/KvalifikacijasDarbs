<?php

namespace App\Http\Controllers;

use App\Models\Competition;
use App\Models\CompetitionGroup;
use App\Models\CompetitionHole;
use App\Models\CompetitionShot;
use App\Models\TrainingRoundShot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CompetitionScoringController extends Controller
{
    /**
     * The card the current user keeps score for: their own group, or for
     * admins any group picked with ?group=N.
     */
    private function resolveGroup(Request $request, Competition $competition): ?CompetitionGroup
    {
        if (Auth::user()->isAdmin() && $request->filled('group')) {
            return $competition->groups()->where('number', (int) $request->input('group'))->first();
        }

        $registration = $competition->registrations()->where('user_id', Auth::id())->first();

        return $registration?->group
            ?? (Auth::user()->isAdmin() ? $competition->groups()->first() : null);
    }

    private function groupPayload(Competition $competition, CompetitionGroup $group): array
    {
        $group->load('registrations.user');
        $playerIds = $group->registrations->pluck('user_id');

        $holes = $competition->courseHoles()
            ->with(['shots' => fn ($query) => $query->whereIn('user_id', $playerIds)])
            ->get();

        return [
            'players' => $group->registrations->map(fn ($registration) => [
                'id' => $registration->user_id,
                'name' => $registration->user->name,
                'division' => $registration->division,
                'avatarUrl' => $registration->user->avatar ? asset('storage/' . $registration->user->avatar) : null,
                'isMe' => $registration->user_id === Auth::id(),
            ])->values(),
            'holes' => $holes->map(fn (CompetitionHole $hole) => [
                'id' => $hole->id,
                'number' => $hole->number,
                'par' => $hole->par,
                'distanceM' => $hole->distance_m,
                'shots' => $hole->shots->groupBy('user_id')->map(fn ($shots) => $shots->map(fn (CompetitionShot $shot) => [
                    'shotNumber' => $shot->shot_number,
                    'result' => $shot->result,
                    'strokes' => $shot->strokes,
                    'obLie' => $shot->ob_lie,
                    'distanceM' => $shot->distance_m,
                ])->values()),
            ])->values(),
        ];
    }

    public function show(Request $request, $id)
    {
        $competition = Competition::findOrFail($id);
        $competition->syncLifecycle();

        if (!$competition->hasGroups()) {
            return redirect()->route('competitions.view', $competition->id)
                ->with('error', 'Groups are drawn ' . Competition::GROUPS_DRAWN_MINUTES_BEFORE . ' minutes before the start (' . $competition->groupsDrawAt()->format('M j, H:i') . ').');
        }

        $group = $this->resolveGroup($request, $competition);
        if (!$group) {
            return redirect()->route('competitions.view', $competition->id)
                ->with('error', 'You are not on a card in this competition.');
        }

        return view('competitions.score', [
            'competition' => $competition,
            'group' => $group,
            'groups' => Auth::user()->isAdmin() ? $competition->groups : collect(),
            'payload' => $this->groupPayload($competition, $group),
        ]);
    }

    /** Polled by the scorecard so everyone on the card sees each other's entries. */
    public function data(Request $request, $id)
    {
        $competition = Competition::findOrFail($id);
        $group = $this->resolveGroup($request, $competition);
        abort_unless($group, 403);

        return response()->json($this->groupPayload($competition, $group) + [
            'scoringOpen' => $competition->scoringIsOpen(),
        ]);
    }

    /**
     * Validates that the scorer may write for this player: same card (or admin),
     * on a hole of this competition, while the round is being played.
     */
    private function authorizeShot(Request $request, Competition $competition, CompetitionHole $hole): int
    {
        abort_unless($hole->competition_id === $competition->id, 404);
        $competition->syncLifecycle();
        abort_unless($competition->scoringIsOpen(), 422, 'Scoring opens when the round starts at ' . $competition->startsAt()->format('H:i') . '.');

        $validated = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('competition_registrations', 'user_id')->where('competition_id', $competition->id)],
        ]);

        if (!Auth::user()->isAdmin()) {
            $myGroupId = $competition->registrations()->where('user_id', Auth::id())->value('competition_group_id');
            $theirGroupId = $competition->registrations()->where('user_id', $validated['user_id'])->value('competition_group_id');
            abort_unless($myGroupId && (int) $myGroupId === (int) $theirGroupId, 403, 'You can only keep score for players on your card.');
        }

        return (int) $validated['user_id'];
    }

    public function addShot(Request $request, $id, CompetitionHole $hole)
    {
        $competition = Competition::findOrFail($id);
        $userId = $this->authorizeShot($request, $competition, $hole);
        $request->validate([
            'result' => ['required', Rule::in(array_keys(CompetitionShot::RESULTS))],
            // After an OB the scorer says where play resumes; a made throw from outside C2 can carry its distance
            'ob_lie' => ['nullable', 'required_if:result,out_of_bounds', Rule::in(array_keys(CompetitionShot::OB_LIES))],
            'distance_m' => ['nullable', 'integer', 'min:1', 'max:300'],
        ]);

        // Two scorers on the same card may tap at once; lock the player's hole while numbering the shot.
        $shot = DB::transaction(function () use ($hole, $userId, $request) {
            $shots = $hole->shots()->where('user_id', $userId)->lockForUpdate()->get();
            abort_if($shots->last()?->result === 'in_basket', 422, 'This hole is already finished for that player.');

            return $hole->shots()->create([
                'user_id' => $userId,
                'recorded_by' => Auth::id(),
                'shot_number' => $shots->count() + 1,
                'result' => $request->input('result'),
                'ob_lie' => $request->input('result') === 'out_of_bounds' ? $request->input('ob_lie') : null,
                'strokes' => CompetitionShot::RESULTS[$request->input('result')]['strokes'],
                'distance_m' => TrainingRoundShot::throwInDistance($request->input('result'), $request->integer('distance_m') ?: null, $shots->last()),
            ]);
        });

        // The last putt of the last card finishes the tournament and rates it
        $finished = $shot->result === 'in_basket' && $competition->refresh()->finishIfComplete();

        return response()->json([
            'finished' => $finished,
            'shot' => [
                'shotNumber' => $shot->shot_number,
                'result' => $shot->result,
                'strokes' => $shot->strokes,
                'obLie' => $shot->ob_lie,
                'distanceM' => $shot->distance_m,
            ],
        ]);
    }

    public function undoShot(Request $request, $id, CompetitionHole $hole)
    {
        $competition = Competition::findOrFail($id);
        $userId = $this->authorizeShot($request, $competition, $hole);

        $hole->shots()->where('user_id', $userId)->reorder('shot_number', 'desc')->first()?->delete();

        return response()->json(['ok' => true]);
    }
}
