<?php

namespace App\Http\Controllers;

use App\Models\CompetitionHole;
use App\Models\User;
use App\Support\RatingEngine;
use App\Support\RoundStats;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show(User $user)
    {
        $user->load([
            'competitionRegistrations' => fn ($query) => $query->with('competition')->latest(),
            'roundRatings' => fn ($query) => $query->with(['competition', 'course']),
        ]);

        $registeredCompetitions = $user->competitionRegistrations;
        $playedCompetitions = $registeredCompetitions
            ->filter(fn ($registration) => $registration->competition?->status === 'completed');

        // Tournament log, newest first (Metrix-style), and the same rounds oldest-first for the chart
        $log = $user->roundRatings;
        $chronological = $log->sortBy(fn ($round) => [$round->played_at->getTimestamp(), $round->id])->values();
        $lastRated = $log->firstWhere('rating_after', '!==', null);

        $ratingStats = [
            'rating' => $user->rating,
            'change' => $log->first()?->rating_change,
            'rounds' => $log->count(),
            'rounds_needed' => max(0, RatingEngine::ROUNDS_FOR_RATING - $log->count()),
            'counted' => $lastRated?->rounds_counted,
            'best' => $log->max('round_rating'),
            'average' => $log->isNotEmpty() ? (int) round($log->avg('round_rating')) : null,
        ];

        $chart = $chronological->map(fn ($round) => [
            'date' => $round->played_at->format('M j, Y'),
            'event' => $round->competition?->name ?? 'Deleted tournament',
            'round' => $round->round_rating,
            'rating' => $round->rating_after,
            'score' => $round->relativeToPar(),
        ])->values();

        // Shot-by-shot stats for every rated tournament round (newest first), in three views
        $holesByCompetition = CompetitionHole::whereIn('competition_id', $log->pluck('competition_id'))
            ->with(['shots' => fn ($query) => $query->where('user_id', $user->id)])
            ->orderBy('number')
            ->get()
            ->groupBy('competition_id');
        $statRounds = $log->map(fn ($round) => [
            'stats' => RoundStats::forRound($holesByCompetition->get($round->competition_id, collect()), $user->id),
            'rating' => $round->round_rating,
            'competition' => $round->competition,
            'played_at' => $round->played_at,
        ]);
        $statModes = $statRounds->isNotEmpty() ? RoundStats::summarise($statRounds) : null;

        return view('profile.show', compact('user', 'registeredCompetitions', 'playedCompetitions', 'log', 'ratingStats', 'chart', 'statModes'));
    }

    public function update(Request $request)
    {
        $user = $request->user();
        // Rating is earned from rated tournament rounds, so it is not editable here
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:male,female'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $validated['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($validated);

        return redirect()->route('profile.show', $user)->with('success', 'Profile updated successfully.');
    }
}
