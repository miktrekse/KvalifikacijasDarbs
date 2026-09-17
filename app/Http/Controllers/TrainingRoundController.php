<?php

namespace App\Http\Controllers;

use App\Models\TrainingRound;
use App\Models\TrainingRoundHole;
use App\Models\TrainingRoundShot;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $rounds = TrainingRound::with(['user', 'players'])
            ->whereHas('players', fn ($query) => $query->where('user_id', Auth::id()))
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('training.index', compact('rounds'));
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

        $holes = [];
        for ($number = 1; $number <= $validated['holes_count']; $number++) {
            $holes[] = [
                'training_round_id' => $round->id,
                'number' => $number,
                'par' => 3,
                'distance_m' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        TrainingRoundHole::insert($holes);

        return redirect()->route('training.show', $round->id)
            ->with('success', 'Training round started! Log your shots hole by hole.');
    }

    public function show(TrainingRound $round)
    {
        $this->abortUnlessParticipant($round);

        $round->load(['players', 'holes.shots.user']);

        return view('training.show', compact('round'));
    }

    public function searchPlayers(Request $request)
    {
        $query = trim((string) $request->query('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json(['players' => []]);
        }

        $players = User::where('id', '!=', Auth::id())
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
            'strokes' => $resultMeta['strokes'],
        ]);

        return response()->json([
            'shot' => [
                'id' => $shot->id,
                'shot_number' => $shot->shot_number,
                'result' => $shot->result,
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

        $lastShot = $hole->shots()->where('user_id', $validated['user_id'])->orderByDesc('shot_number')->first();
        $lastShot?->delete();

        return response()->json([
            'hole_strokes' => (int) $hole->shots()->where('user_id', $validated['user_id'])->sum('strokes'),
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
