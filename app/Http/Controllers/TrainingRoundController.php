<?php

namespace App\Http\Controllers;

use App\Models\TrainingRound;
use App\Models\TrainingRoundHole;
use App\Models\TrainingRoundShot;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TrainingRoundController extends Controller
{
    private function abortUnlessParticipant(TrainingRound $round): void
    {
        $isParticipant = $round->user_id === Auth::id()
            || $round->players()->where('user_id', Auth::id())->exists();

        abort_unless($isParticipant, 403);
    }

    public function index()
    {
        $mine = fn ($query) => $query->where('user_id', Auth::id());

        $rounds = TrainingRound::with(['user', 'players', 'holes.shots'])
            ->whereHas('players', $mine)
            ->orderByDesc('created_at')
            ->paginate(10);

        // Career numbers across every round, counting only the current player's throws
        $allRounds = TrainingRound::with(['players' => $mine, 'holes.shots' => $mine])
            ->whereHas('players', $mine)
            ->get();
        $myTotals = $allRounds->map(fn (TrainingRound $round) => ['round' => $round] + ($round->playerTotals()->get(Auth::id()) ?? ['thru' => 0]));
        $fullRounds = $myTotals->filter(fn ($t) => $t['thru'] > 0 && $t['thru'] === $t['round']->holes_count);

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

        return view('training.show', [
            'round' => $round,
            'payload' => $this->scoringPayload($round),
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

        $alreadyHoledOut = $hole->shots()
            ->where('user_id', $validated['user_id'])
            ->where('result', 'in_basket')
            ->exists();

        abort_if($alreadyHoledOut, 422, 'This hole is already finished for that player.');

        $nextShotNumber = $hole->shots()->where('user_id', $validated['user_id'])->count() + 1;
        $resultMeta = TrainingRoundShot::RESULTS[$validated['result']];

        $shot = $hole->shots()->create([
            'user_id' => $validated['user_id'],
            'shot_number' => $nextShotNumber,
            'result' => $validated['result'],
            'ob_lie' => $validated['result'] === 'out_of_bounds' ? $validated['ob_lie'] : null,
            'strokes' => $resultMeta['strokes'],
            'distance_m' => TrainingRoundShot::throwInDistance(
                $validated['result'],
                $validated['distance_m'] ?? null,
                $hole->shots()->where('user_id', $validated['user_id'])->reorder('shot_number', 'desc')->first()
            ),
        ]);

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

        $lastShot = $hole->shots()->where('user_id', $validated['user_id'])->reorder('shot_number', 'desc')->first();
        $lastShot?->delete();

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

        $shot = DB::transaction(function () use ($hole, $userId, $strokes) {
            $shots = $hole->shots()->where('user_id', $userId)->lockForUpdate()->get();
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

    public function complete(TrainingRound $round)
    {
        $this->abortUnlessParticipant($round);

        $round->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return redirect()->route('training.show', $round->id)
            ->with('success', 'Round completed. Nice work out there!');
    }
}
