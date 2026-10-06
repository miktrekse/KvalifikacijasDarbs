<?php

namespace App\Support;

use App\Models\Competition;
use App\Models\Course;
use App\Models\RoundRating;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Player and course ratings, modelled on PDGA / Disc Golf Metrix.
 *
 * Round rating: every course layout has a par rating (starts at 950) and each stroke
 * under/over par is worth 7 points. Par = 950, −3 = 971, +2 = 936.
 *
 * Course rating: after each tournament the rated players ("propagators") tell us how
 * hard the course played. A 950 player shooting +3 implies par is worth ~971 there.
 * The course moves a small, damped step towards the field's (trimmed) opinion, so one
 * strange day can't swing it.
 *
 * Player rating: none until 3 rated tournament rounds, then their average. With more
 * rounds it's a rolling average over the last 12 months (max 20 rounds); with 7+
 * rounds clear outliers are dropped, and with 9+ the most recent quarter counts double.
 *
 * Everything is replayed from scratch in date order, so corrections, re-opened or
 * deleted tournaments always leave every later rating consistent.
 */
class RatingEngine
{
    public const ROUNDS_FOR_RATING = 3;

    public const MIN_PROPAGATORS = 3;

    /** Damping: the field gets weight n / (n + 20), halved. 5 rated players ≈ 10 %, 30 ≈ 30 %. */
    public const PROPAGATOR_DAMPING = 20;

    /** A single tournament never moves a course more than this many points. */
    public const MAX_COURSE_STEP = 10.0;

    public const WINDOW_MONTHS = 12;

    public const MAX_ROUNDS = 20;

    /** @return array{events: int, rounds: int, rated_players: int} */
    public function recalculate(): array
    {
        return DB::transaction(function () {
            RoundRating::query()->delete();
            Course::query()->update(['par_rating' => Course::BASE_PAR_RATING, 'rated_rounds' => 0, 'rated_events' => 0]);
            Competition::query()->whereNotNull('rated_at')->update([
                'course_rating_before' => null,
                'course_rating_after' => null,
                'rated_at' => null,
            ]);

            $history = [];   // user id => list of [Carbon playedAt, int roundRating]
            $ratings = [];   // user id => current rating (null while provisional)
            $events = 0;
            $rounds = 0;

            $competitions = Competition::where('status', 'completed')
                ->with(['courseHoles.shots', 'registrations'])
                ->get()
                ->sortBy(fn (Competition $competition) => $competition->startsAt()->getTimestamp())
                ->values();

            foreach ($competitions as $competition) {
                $rated = $this->rateCompetition($competition, $history, $ratings);
                if ($rated) {
                    $events++;
                    $rounds += $rated;
                }
            }

            // Ratings are computed, not entered: anyone without 3 rated rounds is unrated.
            User::query()->whereNotNull('rating')->update(['rating' => null]);
            foreach ($history as $userId => $rounds_) {
                // As of today, or the latest round if the clock is behind it
                $latest = collect($rounds_)->max(fn (array $round) => $round[0]);
                $current = $this->playerRating($rounds_, now()->max($latest));
                if ($current !== null) {
                    User::whereKey($userId)->update(['rating' => $current['rating']]);
                }
            }

            return [
                'events' => $events,
                'rounds' => $rounds,
                'rated_players' => User::whereNotNull('rating')->count(),
            ];
        });
    }

    /** Rates one finished tournament. Returns the number of rated rounds (0 if nothing to rate). */
    private function rateCompetition(Competition $competition, array &$history, array &$ratings): int
    {
        $holes = $competition->courseHoles->each->useOfficialShots();
        if ($holes->isEmpty()) {
            return 0;
        }

        $par = (int) $holes->sum('par');
        $results = $this->finishedRounds($competition, $holes);
        if ($results->isEmpty()) {
            return 0;
        }

        $name = $competition->course_name ?: $competition->name;
        $course = Course::firstOrCreate(
            ['name_key' => Course::keyFor($name), 'holes' => $holes->count(), 'par' => $par],
            ['name' => $name, 'par_rating' => Course::BASE_PAR_RATING, 'points_per_stroke' => Course::POINTS_PER_STROKE]
        );

        $before = $course->par_rating;
        $propagators = $results
            ->filter(fn (array $result) => ($ratings[$result['user_id']] ?? null) !== null)
            ->map(fn (array $result) => $result + ['rating' => $ratings[$result['user_id']]]);
        $after = $this->adjustCourse($before, $course->points_per_stroke, $par, $propagators);

        $course->update([
            'par_rating' => $after,
            'rated_rounds' => $course->rated_rounds + $results->count(),
            'rated_events' => $course->rated_events + 1,
        ]);

        $playedAt = $competition->startsAt()->utc();
        foreach ($results as $result) {
            $userId = $result['user_id'];
            $roundRating = $course->ratingFor($result['strokes'], $after);
            $history[$userId][] = [$playedAt, $roundRating];

            $ratingBefore = $ratings[$userId] ?? null;
            $current = $this->playerRating($history[$userId], $playedAt);
            $ratings[$userId] = $current['rating'] ?? null;

            RoundRating::create([
                'competition_id' => $competition->id,
                'user_id' => $userId,
                'course_id' => $course->id,
                'strokes' => $result['strokes'],
                'par' => $par,
                'round_rating' => $roundRating,
                'rating_before' => $ratingBefore,
                'rating_after' => $ratings[$userId],
                'rating_change' => $ratingBefore !== null && $ratings[$userId] !== null ? $ratings[$userId] - $ratingBefore : null,
                'rounds_counted' => $current['rounds'] ?? count($history[$userId]),
                'is_propagator' => $ratingBefore !== null,
                'played_at' => $playedAt,
            ]);
        }

        $competition->forceFill([
            'course_id' => $course->id,
            'course_rating_before' => $before,
            'course_rating_after' => $after,
            'rated_at' => now(),
        ])->saveQuietly();

        return $results->count();
    }

    /** Players who holed out on every hole, with their total strokes. */
    private function finishedRounds(Competition $competition, Collection $holes): Collection
    {
        return $competition->registrations
            ->map(function ($registration) use ($holes) {
                $strokes = 0;
                foreach ($holes as $hole) {
                    $shots = $hole->shots->where('user_id', $registration->user_id)->sortBy('shot_number');
                    if ($shots->isEmpty() || $shots->last()->result !== 'in_basket') {
                        return null;
                    }
                    $strokes += $shots->sum('strokes');
                }

                return ['user_id' => $registration->user_id, 'strokes' => $strokes];
            })
            ->filter()
            ->values();
    }

    /**
     * Moves the course's par rating slightly towards what the rated field implies.
     * Each propagator implies: their rating + points × (their strokes − par).
     */
    public function adjustCourse(float $before, float $pointsPerStroke, int $par, Collection $propagators): float
    {
        $count = $propagators->count();
        if ($count < self::MIN_PROPAGATORS) {
            return $before;
        }

        $implied = $propagators
            ->map(fn (array $p) => $p['rating'] + $pointsPerStroke * ($p['strokes'] - $par))
            ->sort()
            ->values();

        // Ignore the most extreme 10 % at each end once the field is big enough
        $trim = intdiv($count, 10);
        $target = $implied->slice($trim, $count - 2 * $trim)->avg();

        $weight = $count / ($count + self::PROPAGATOR_DAMPING) / 2;
        $step = max(-self::MAX_COURSE_STEP, min(self::MAX_COURSE_STEP, $weight * ($target - $before)));

        return round($before + $step, 1);
    }

    /**
     * A player's rating as of a date, from their rated rounds ([Carbon, rating] pairs).
     *
     * @return array{rating: int, rounds: int}|null null until ROUNDS_FOR_RATING rounds are played
     */
    public function playerRating(array $rounds, Carbon $asOf): ?array
    {
        $played = collect($rounds)
            ->filter(fn (array $round) => $round[0]->lte($asOf))
            ->sortBy(fn (array $round) => $round[0]->getTimestamp())
            ->values();

        if ($played->count() < self::ROUNDS_FOR_RATING) {
            return null;
        }

        $window = $played->filter(fn (array $round) => $round[0]->gte($asOf->copy()->subMonths(self::WINDOW_MONTHS)));
        if ($window->count() < self::ROUNDS_FOR_RATING) {
            $window = $played->slice(-self::ROUNDS_FOR_RATING);
        }
        $values = $window->slice(-self::MAX_ROUNDS)->pluck(1)->values();

        // Drop rounds far below the player's norm (PDGA: >100 points or 2.5 SD below average)
        if ($values->count() >= 7) {
            $average = $values->avg();
            $sd = sqrt($values->map(fn ($v) => ($v - $average) ** 2)->avg());
            $cutoff = $average - min(100, 2.5 * $sd);
            $kept = $values->filter(fn ($v) => $v >= $cutoff)->values();
            if ($kept->count() >= self::ROUNDS_FOR_RATING) {
                $values = $kept;
            }
        }

        // Most recent quarter counts double once there are enough rounds
        $doubleFrom = $values->count() >= 9 ? $values->count() - (int) ceil($values->count() / 4) : PHP_INT_MAX;
        $weighted = $values->map(fn ($v, $i) => [$v, $i >= $doubleFrom ? 2 : 1]);

        return [
            'rating' => (int) round($weighted->sum(fn ($w) => $w[0] * $w[1]) / $weighted->sum(fn ($w) => $w[1])),
            'rounds' => $values->count(),
        ];
    }
}
