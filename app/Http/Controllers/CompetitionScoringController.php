<?php

namespace App\Http\Controllers;

use App\Models\Competition;
use App\Models\CompetitionGroup;
use App\Models\CompetitionHole;
use App\Models\CompetitionShot;
use App\Models\TrainingRoundShot;
use App\Support\CardScores;
use App\Support\RatingEngine;
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
            ->with(['shots' => fn ($query) => $query->whereIn('user_id', $playerIds)->with('recorder:id,name')])
            ->get();
        $me = Auth::id();

        return [
            'players' => $group->registrations->map(fn ($registration) => [
                'id' => $registration->user_id,
                'name' => $registration->user->name,
                'division' => $registration->division,
                'avatarUrl' => $registration->user->avatar ? asset('storage/' . $registration->user->avatar) : null,
                'isMe' => $registration->user_id === Auth::id(),
                'profileUrl' => route('profile.show', $registration->user_id),
            ])->values(),
            'holes' => $holes->map(fn (CompetitionHole $hole) => [
                'id' => $hole->id,
                'number' => $hole->number,
                'par' => $hole->par,
                'distanceM' => $hole->distance_m,
                // This phone's own copy of each player's hole; other scorers' copies are only
                // summarised, so a hole isn't filled in here just because someone else scored it
                'shots' => $hole->shots->where('recorded_by', $me)->groupBy('user_id')
                    ->map(fn ($shots) => $shots->map(fn (CompetitionShot $shot) => $this->shotPayload($shot))->values()),
                'entries' => $hole->shots->where('recorded_by', '!=', $me)->groupBy('user_id')
                    ->map(fn ($shots) => CardScores::entries($shots)->map(fn (array $entry) => [
                        'scorerId' => $entry['scorer'],
                        'scorerName' => $entry['shots']->first()->recorder?->name ?? 'Another scorer',
                        'finished' => $entry['finished'],
                        'strokes' => $entry['strokes'],
                        'scoreOnly' => $entry['score_only'],
                    ])->values()),
            ])->values(),
        ];
    }

    public function show(Request $request, $id)
    {
        // Only reads: the scheduler draws the groups (competitions:sync), score entry moves the event on
        $competition = Competition::findOrFail($id);

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
            $shots = $this->myCopy($hole, $userId)->lockForUpdate()->get();
            abort_if($shots->last()?->result === 'in_basket', 422, 'You already finished this hole for that player.');

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
            'shot' => $this->shotPayload($shot),
        ]);
    }

    /**
     * Scores a whole hole as one number (UDisc-style) for a player whose throws
     * aren't tracked shot by shot. An empty score clears the hole again.
     */
    public function setScore(Request $request, $id, CompetitionHole $hole)
    {
        $competition = Competition::findOrFail($id);
        $userId = $this->authorizeShot($request, $competition, $hole);
        $strokes = $request->validate([
            'strokes' => ['nullable', 'integer', 'min:1', 'max:' . TrainingRoundShot::MAX_HOLE_SCORE],
        ])['strokes'] ?? null;

        $shot = DB::transaction(function () use ($hole, $userId, $strokes) {
            $shots = $this->myCopy($hole, $userId)->lockForUpdate()->get();
            abort_if($shots->contains(fn (CompetitionShot $shot) => !$shot->score_only), 422, 'You are tracking this hole shot by shot for that player.');

            $this->myCopy($hole, $userId)->delete();

            return $strokes ? $hole->shots()->create([
                'user_id' => $userId,
                'recorded_by' => Auth::id(),
                'shot_number' => 1,
                'result' => 'in_basket',
                'strokes' => $strokes,
                'score_only' => true,
            ]) : null;
        });

        $finished = $shot && $competition->refresh()->finishIfComplete();

        return response()->json([
            'finished' => $finished,
            'shot' => $shot ? $this->shotPayload($shot) : null,
        ]);
    }

    /**
     * Settles a score conflict by taking another scorer's copy of the hole as your own
     * (their throws too, when they tracked shot by shot).
     */
    public function acceptScore(Request $request, $id, CompetitionHole $hole)
    {
        $competition = Competition::findOrFail($id);
        $userId = $this->authorizeShot($request, $competition, $hole);
        $scorerId = $request->validate(['scorer_id' => ['required', 'integer']])['scorer_id'];

        $shots = DB::transaction(function () use ($hole, $userId, $scorerId) {
            $theirs = $hole->shots()->where('user_id', $userId)->where('recorded_by', $scorerId)->orderBy('shot_number')->get();
            abort_unless($theirs->last()?->result === 'in_basket', 422, 'That scorer has not finished this hole.');

            $this->myCopy($hole, $userId)->lockForUpdate()->get();
            $this->myCopy($hole, $userId)->delete();

            return $theirs->map(fn (CompetitionShot $shot) => $hole->shots()->create([
                'user_id' => $userId,
                'recorded_by' => Auth::id(),
            ] + $shot->only(['shot_number', 'result', 'ob_lie', 'strokes', 'distance_m', 'score_only'])));
        });

        $finished = $competition->refresh()->finishIfComplete();

        return response()->json([
            'finished' => $finished,
            'shots' => $shots->map(fn (CompetitionShot $shot) => $this->shotPayload($shot))->values(),
        ]);
    }

    /** Official scorecard editor for every card at once (admins and the tournament director). */
    public function editScorecard($id)
    {
        $competition = Competition::findOrFail($id);
        abort_unless($competition->canEditScores(Auth::user()), 403);

        $registrations = $this->cardRegistrations($competition);
        $holes = $competition->courseHoles()->with('shots')->get();

        return view('competitions.scorecard-edit', [
            'competition' => $competition,
            'holes' => $holes,
            'players' => $registrations->map(fn ($registration) => [
                'id' => $registration->user_id,
                'name' => $registration->user->name,
                'label' => 'Card ' . $registration->group->number . ' · ' . $registration->division,
            ])->values(),
            'scores' => $holes->mapWithKeys(fn (CompetitionHole $hole) => [
                $hole->id => $registrations->mapWithKeys(fn ($registration) => [
                    $registration->user_id => CardScores::resolve($hole->shots->where('user_id', $registration->user_id))['strokes'],
                ])->all(),
            ])->all(),
        ]);
    }

    /**
     * Saves the editor. A hole whose official score changed is replaced by a single
     * score-only entry from the editor, wiping every scorer's copy (and any conflict).
     */
    public function updateScorecard(Request $request, $id)
    {
        $competition = Competition::findOrFail($id);
        abort_unless($competition->canEditScores(Auth::user()), 403);

        $validated = $request->validate([
            'holes' => 'required|array',
            'holes.*.par' => 'required|integer|min:1|max:10',
            'holes.*.distance_m' => 'nullable|integer|min:1|max:2000',
            'scores' => 'nullable|array',
            'scores.*' => 'array',
            'scores.*.*' => 'nullable|integer|min:1|max:' . TrainingRoundShot::MAX_HOLE_SCORE,
        ]);

        $playerIds = $this->cardRegistrations($competition)->pluck('user_id');
        $holes = $competition->courseHoles()->with('shots')->get();

        DB::transaction(function () use ($holes, $playerIds, $validated) {
            foreach ($holes as $hole) {
                $hole->update([
                    'par' => $validated['holes'][$hole->id]['par'] ?? $hole->par,
                    'distance_m' => $validated['holes'][$hole->id]['distance_m'] ?? null,
                ]);

                foreach ($playerIds as $userId) {
                    $strokes = $validated['scores'][$hole->id][$userId] ?? null;
                    $strokes = $strokes === null ? null : (int) $strokes;
                    $resolved = CardScores::resolve($hole->shots->where('user_id', $userId));

                    // Unchanged holes keep every scorer's copy; a blank box leaves an open or disputed hole alone
                    if ($strokes === $resolved['strokes'] || ($strokes === null && $resolved['status'] === CardScores::CONFLICT)) {
                        continue;
                    }

                    $hole->shots()->where('user_id', $userId)->delete();
                    if ($strokes) {
                        $hole->shots()->create([
                            'user_id' => $userId,
                            'recorded_by' => Auth::id(),
                            'shot_number' => 1,
                            'result' => 'in_basket',
                            'strokes' => $strokes,
                            'score_only' => true,
                        ]);
                    }
                }
            }
        });

        // A finished tournament's scores feed round, course and player ratings
        if ($competition->status === 'completed') {
            app(RatingEngine::class)->recalculate();
            $competition->registrations()->with('user')->get()->each(fn ($registration) => $registration->user?->syncVerification());
        } else {
            $competition->finishIfComplete();
        }

        return redirect()->route('competitions.view', $competition->id)->with('success', 'Scores updated.');
    }

    /** Everyone on a card, in card order. */
    private function cardRegistrations(Competition $competition)
    {
        return $competition->registrations()
            ->whereNotNull('competition_group_id')
            ->with(['user', 'group'])
            ->get()
            ->sortBy(fn ($registration) => [$registration->group->number, $registration->user->name])
            ->values();
    }

    /** The current scorer's own copy of a player's hole. */
    private function myCopy(CompetitionHole $hole, int $userId)
    {
        return $hole->shots()->where('user_id', $userId)->where('recorded_by', Auth::id());
    }

    private function shotPayload(CompetitionShot $shot): array
    {
        return [
            'shotNumber' => $shot->shot_number,
            'result' => $shot->result,
            'strokes' => $shot->strokes,
            'obLie' => $shot->ob_lie,
            'distanceM' => $shot->distance_m,
            'scoreOnly' => (bool) $shot->score_only,
        ];
    }

    public function undoShot(Request $request, $id, CompetitionHole $hole)
    {
        $competition = Competition::findOrFail($id);
        $userId = $this->authorizeShot($request, $competition, $hole);

        $this->myCopy($hole, $userId)->reorder('shot_number', 'desc')->first()?->delete();

        return response()->json(['ok' => true]);
    }
}
