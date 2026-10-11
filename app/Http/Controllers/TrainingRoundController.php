<?php

namespace App\Http\Controllers;

use App\Models\TrainingRound;
use App\Models\TrainingRoundHole;
use App\Models\TrainingRoundShot;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TrainingRoundController extends Controller
{
    /** Players on the round who haven't deleted it from their list. */
    private function abortUnlessParticipant(TrainingRound $round): void
    {
        abort_unless($round->isVisibleTo(Auth::id()), 404);
    }

    public function index()
    {
        $mine = fn ($query) => $query->where('user_id', Auth::id());

        $rounds = TrainingRound::with(['user', 'players', 'holes.shots'])
            ->visibleTo(Auth::id())
            ->orderByDesc('created_at')
            ->paginate(10);

        // Career numbers across every round, counting only the current player's throws
        $allRounds = TrainingRound::with(['players' => $mine, 'holes.shots' => $mine])
            ->visibleTo(Auth::id())
            ->get();
        $myTotals = $allRounds->map(fn (TrainingRound $round) => ['round' => $round] + ($round->playerTotals()->get(Auth::id()) ?? ['thru' => 0]));
        $fullRounds = $myTotals->filter(fn ($t) => $t['round']->isFinishedBy(Auth::id()));

        $summary = [
            'rounds' => $allRounds->count(),
            'in_progress' => $allRounds->where('status', '!=', 'completed')->count(),
            'holes' => $myTotals->sum('thru'),
            'best' => $fullRounds->sortBy('relative')->first(),
            'average' => $fullRounds->isNotEmpty() ? $fullRounds->avg('relative') : null,
        ];

        return view('training.index', compact('rounds', 'summary'));
    }

    public function create()
    {
        return view('training.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_name' => 'required|string|max:255',
            'course_lat' => 'nullable|numeric|between:-90,90',
            'course_lon' => 'nullable|numeric|between:-180,180',
            'course_locality' => 'nullable|string|max:255',
            'holes_count' => 'required|integer|min:1|max:36',
            'holes_data' => 'nullable|string',
            'player_ids' => 'nullable|array',
            'player_ids.*' => 'integer|exists:users,id',
        ]);

        $round = TrainingRound::create([
            'user_id' => Auth::id(),
            'course_name' => $validated['course_name'],
            'course_lat' => $validated['course_lat'] ?? null,
            'course_lon' => $validated['course_lon'] ?? null,
            'course_locality' => $validated['course_locality'] ?? null,
            'holes_count' => $validated['holes_count'],
            'status' => 'in_progress',
        ]);

        $playerIds = array_values(array_unique(array_merge([Auth::id()], $validated['player_ids'] ?? [])));
        $round->players()->attach($playerIds);

        $layoutHoles = $this->parseLayoutHoles($validated['holes_data'] ?? null);

        $holes = [];
        for ($number = 1; $number <= $validated['holes_count']; $number++) {
            $holeData = $layoutHoles->get($number);
            $holes[] = [
                'training_round_id' => $round->id,
                'number' => $number,
                'par' => $holeData['par'] ?? 3,
                'distance_m' => $holeData['distance_m'] ?? 100,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        TrainingRoundHole::insert($holes);

        return redirect()->route('training.show', $round->id)
            ->with('success', 'Training round started! Log your shots hole by hole.');
    }

    private function parseLayoutHoles(?string $holesData): \Illuminate\Support\Collection
    {
        if (!$holesData) {
            return collect();
        }

        $decoded = json_decode($holesData, true);
        if (!is_array($decoded)) {
            return collect();
        }

        return collect($decoded)
            ->filter(fn ($hole) => is_array($hole) && isset($hole['number'], $hole['par']) && is_numeric($hole['number']) && is_numeric($hole['par']))
            ->mapWithKeys(fn (array $hole) => [
                (int) $hole['number'] => [
                    'par' => max(1, min(10, (int) $hole['par'])),
                    'distance_m' => isset($hole['distance_m']) && is_numeric($hole['distance_m'])
                        ? max(1, min(2000, (int) $hole['distance_m']))
                        : 100,
                ],
            ]);
    }

    public function show(TrainingRound $round)
    {
        $this->abortUnlessParticipant($round);
        $payload = $this->scoringPayload($round);

        return view('training.show', [
            'round' => $round,
            'payload' => $payload,
            'canManage' => $round->isManagedBy(Auth::id()),
            'openHoles' => $round->openHoleCount(),
        ]);
    }

    /** Polled by the scorekeeper so throws logged on another phone show up. */
    public function data(TrainingRound $round)
    {
        $this->abortUnlessParticipant($round);

        return response()->json($this->scoringPayload($round) + [
            'scoringOpen' => !$round->isCompleted(),
        ]);
    }

    private function scoringPayload(TrainingRound $round): array
    {
        $round->load(['players', 'holes.shots']);

        return [
            'players' => $round->players->map(fn (User $player) => [
                'id' => $player->id,
                'name' => $player->name,
                'division' => '',
                'avatarUrl' => $player->avatar ? asset('storage/' . $player->avatar) : null,
                'isMe' => $player->id === Auth::id(),
                'profileUrl' => route('profile.show', $player),
            ])->values(),
            'holes' => $round->holes->map(fn (TrainingRoundHole $hole) => [
                'id' => $hole->id,
                'number' => $hole->number,
                'par' => $hole->par,
                'distanceM' => $hole->distance_m,
                'shots' => $hole->shots->groupBy('user_id')->map(fn ($shots) => $shots->map(fn (TrainingRoundShot $shot) => [
                    'shotNumber' => $shot->shot_number,
                    'result' => $shot->result,
                    'strokes' => $shot->strokes,
                    'obLie' => $shot->ob_lie,
                    'distanceM' => $shot->distance_m,
                    'scoreOnly' => (bool) $shot->score_only,
                ])->values()),
            ])->values(),
        ];
    }

    public function searchPlayers(Request $request)
    {
        $query = trim((string) $request->query('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json(['players' => []]);
        }

        $players = User::withoutGuests()
            ->where('id', '!=', Auth::id())
            ->where('name', 'like', '%' . $query . '%')
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'avatar']);

        return response()->json([
            'players' => $players->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
            ]),
        ]);
    }

    public function addShot(Request $request, TrainingRound $round, TrainingRoundHole $hole)
    {
        $this->abortUnlessParticipant($round);
        abort_unless($hole->training_round_id === $round->id, 404);
        abort_if($round->isCompleted(), 422, 'This round is already completed.');

        $validated = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('training_round_players', 'user_id')->where('training_round_id', $round->id)],
            'result' => ['required', Rule::in(array_keys(TrainingRoundShot::RESULTS))],
            'ob_lie' => ['nullable', 'required_if:result,out_of_bounds', Rule::in(array_keys(TrainingRoundShot::OB_LIES))],
            'distance_m' => ['nullable', 'integer', 'min:1', 'max:300'],
        ]);

        // The check, the numbering and the insert happen under one lock, like tournament scoring
        $shot = $this->writeHole($hole, function () use ($hole, $validated) {
            $shots = $hole->shots()->where('user_id', $validated['user_id'])->get();
            abort_if($shots->contains('result', 'in_basket'), 422, 'This hole is already finished for that player.');

            return $hole->shots()->create([
                'user_id' => $validated['user_id'],
                'shot_number' => $shots->count() + 1,
                'result' => $validated['result'],
                'ob_lie' => $validated['result'] === 'out_of_bounds' ? $validated['ob_lie'] : null,
                'strokes' => TrainingRoundShot::RESULTS[$validated['result']]['strokes'],
                'distance_m' => TrainingRoundShot::throwInDistance($validated['result'], $validated['distance_m'] ?? null, $shots->last()),
            ]);
        });

        return response()->json([
            'shot' => [
                'id' => $shot->id,
                'shot_number' => $shot->shot_number,
                'result' => $shot->result,
                'ob_lie' => $shot->ob_lie,
                'distance_m' => $shot->distance_m,
                'label' => $shot->label,
                'strokes' => $shot->strokes,
            ],
            'hole_strokes' => (int) $hole->shots()->where('user_id', $validated['user_id'])->sum('strokes'),
            'holed_out' => $validated['result'] === 'in_basket',
        ]);
    }

    public function undoShot(Request $request, TrainingRound $round, TrainingRoundHole $hole)
    {
        $this->abortUnlessParticipant($round);
        abort_unless($hole->training_round_id === $round->id, 404);
        abort_if($round->isCompleted(), 422, 'This round is already completed.');

        $validated = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('training_round_players', 'user_id')->where('training_round_id', $round->id)],
        ]);

        $this->writeHole($hole, fn () => $hole->shots()->where('user_id', $validated['user_id'])->reorder('shot_number', 'desc')->first()?->delete());

        return response()->json([
            'hole_strokes' => (int) $hole->shots()->where('user_id', $validated['user_id'])->sum('strokes'),
        ]);
    }

    /**
     * Scores a whole hole as one number (UDisc-style) for a player whose throws
     * aren't tracked shot by shot. An empty score clears the hole again.
     */
    public function setScore(Request $request, TrainingRound $round, TrainingRoundHole $hole)
    {
        $this->abortUnlessParticipant($round);
        abort_unless($hole->training_round_id === $round->id, 404);
        abort_if($round->isCompleted(), 422, 'This round is already completed.');

        $validated = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('training_round_players', 'user_id')->where('training_round_id', $round->id)],
            'strokes' => ['nullable', 'integer', 'min:1', 'max:' . TrainingRoundShot::MAX_HOLE_SCORE],
        ]);
        $userId = (int) $validated['user_id'];
        $strokes = $validated['strokes'] ?? null;

        $shot = $this->writeHole($hole, function () use ($hole, $userId, $strokes) {
            $shots = $hole->shots()->where('user_id', $userId)->get();
            abort_if($shots->contains(fn (TrainingRoundShot $shot) => !$shot->score_only), 422, 'This hole is being tracked shot by shot for that player.');

            $hole->shots()->where('user_id', $userId)->delete();

            return $strokes ? $hole->shots()->create([
                'user_id' => $userId,
                'shot_number' => 1,
                'result' => 'in_basket',
                'strokes' => $strokes,
                'score_only' => true,
            ]) : null;
        });

        return response()->json([
            'shot' => $shot ? [
                'shot_number' => $shot->shot_number,
                'result' => $shot->result,
                'strokes' => $shot->strokes,
                'score_only' => true,
            ] : null,
        ]);
    }

    /** Scorecard editor: course, par and every player's score per hole, open to anyone who played. */
    public function edit(TrainingRound $round)
    {
        $this->abortUnlessParticipant($round);
        $round->load(['players', 'holes.shots']);

        return view('training.edit', [
            'round' => $round,
            'players' => $round->players->map(fn (User $player) => ['id' => $player->id, 'name' => $player->name])->values(),
            'scores' => $round->holes->mapWithKeys(fn (TrainingRoundHole $hole) => [
                $hole->id => $round->players->mapWithKeys(fn (User $player) => [$player->id => $round->holeScore($hole, $player->id)])->all(),
            ])->all(),
        ]);
    }

    public function update(Request $request, TrainingRound $round)
    {
        $this->abortUnlessParticipant($round);
        $round->load(['players', 'holes.shots']);

        $validated = $request->validate([
            'course_name' => 'required|string|max:255',
            'holes' => 'required|array',
            'holes.*.par' => 'required|integer|min:1|max:10',
            'holes.*.distance_m' => 'nullable|integer|min:1|max:2000',
            'scores' => 'nullable|array',
            'scores.*' => 'array',
            'scores.*.*' => 'nullable|integer|min:1|max:' . TrainingRoundShot::MAX_HOLE_SCORE,
        ]);

        DB::transaction(function () use ($round, $validated) {
            $round->update(['course_name' => $validated['course_name']]);

            foreach ($round->holes as $hole) {
                $hole->update([
                    'par' => $validated['holes'][$hole->id]['par'] ?? $hole->par,
                    'distance_m' => $validated['holes'][$hole->id]['distance_m'] ?? $hole->distance_m,
                ]);

                foreach ($round->players as $player) {
                    $strokes = $validated['scores'][$hole->id][$player->id] ?? null;
                    $strokes = $strokes === null ? null : (int) $strokes;

                    // Untouched holes keep their shot-by-shot log (and the stats that come from it)
                    if ($strokes === $round->holeScore($hole, $player->id)) {
                        continue;
                    }

                    $hole->shots()->where('user_id', $player->id)->delete();
                    if ($strokes) {
                        $hole->shots()->create([
                            'user_id' => $player->id,
                            'shot_number' => 1,
                            'result' => 'in_basket',
                            'strokes' => $strokes,
                            'score_only' => true,
                        ]);
                    }
                }
            }
        });

        return redirect()->route('training.show', $round->id)->with('success', 'Round updated.');
    }

    /**
     * Deletes the round from your list. Anyone else who played it keeps it until
     * they delete it too; once nobody has it left, it is gone for good.
     */
    public function destroy(TrainingRound $round)
    {
        $this->abortUnlessParticipant($round);

        $others = $round->otherActivePlayers(Auth::id());
        $round->removeFor(Auth::id());

        return redirect()->route('training.index')->with('success', $others->isEmpty()
            ? 'Training round deleted.'
            : 'Training round removed from your list. ' . $others->pluck('name')->join(', ', ' and ') . ' can still see it.');
    }

    /**
     * Closes the round. Only the player who started it may, so one invited player can't lock
     * everyone else's scorecard. A round with open holes needs an explicit "finish early",
     * and an empty one can't be finished at all (delete it instead).
     */
    public function complete(Request $request, TrainingRound $round)
    {
        $this->abortUnlessParticipant($round);
        abort_unless($round->isManagedBy(Auth::id()), 403, 'Only the player who started the round can finish it.');

        $round->load(['players', 'holes.shots']);
        $back = redirect()->route('training.show', $round->id);

        if ($round->isCompleted()) {
            return $back;
        }
        if (!$round->hasScores()) {
            return $back->with('error', 'Nothing has been scored yet. Delete the round if you are not playing it.');
        }
        $open = $round->openHoleCount();
        if ($open > 0 && !$request->boolean('finish_early')) {
            return $back->with('error', $open . ' ' . Str::plural('hole', $open) . ' still open. Use "Finish early" to close the round anyway.');
        }

        $round->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return $back->with('success', 'Round completed. Nice work out there!');
    }

    /** Opens a finished round again, e.g. after finishing early by mistake. */
    public function reopen(TrainingRound $round)
    {
        $this->abortUnlessParticipant($round);
        abort_unless($round->isManagedBy(Auth::id()), 403, 'Only the player who started the round can reopen it.');

        $round->update([
            'status' => 'in_progress',
            'completed_at' => null,
        ]);

        return redirect()->route('training.show', $round->id)->with('success', 'Round reopened. Keep scoring.');
    }

    /**
     * Runs one change to a hole's scores. Every write on the hole waits for the hole row, so
     * two phones can't both take the next shot number, even on an empty hole. If the database
     * still reports a duplicate, the scorer gets a clear 409 instead of a server error.
     */
    private function writeHole(TrainingRoundHole $hole, \Closure $write): mixed
    {
        try {
            return DB::transaction(function () use ($hole, $write) {
                TrainingRoundHole::whereKey($hole->id)->lockForUpdate()->first();

                return $write();
            });
        } catch (UniqueConstraintViolationException) {
            abort(409, 'That throw was already saved from another phone. Your scorecard will refresh.');
        }
    }
}
