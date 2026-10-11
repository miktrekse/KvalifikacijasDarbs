<?php

namespace App\Jobs;

use App\Models\User;
use App\Support\RatingEngine;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Replays every rating (rounds, courses, players) in the background, so the last putt of
 * a tournament or an admin edit never waits for the whole history to be recalculated.
 *
 * Only one copy waits in the queue at a time: a replay always reads the latest results,
 * so ten finished tournaments in a row need one pending job, not ten. The engine itself
 * holds a lock, so two workers (or the artisan command) never replay at the same time.
 */
class RecalculateRatings implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    /** @return array{events: int, rounds: int, rated_players: int} */
    public function handle(RatingEngine $engine): array
    {
        $result = $engine->recalculate();

        // Verification counts rated (fully played) tournament rounds, so it follows the ratings
        User::where('role', 'user')
            ->whereHas('roundRatings')
            ->get()
            ->each(fn (User $user) => $user->syncVerification());

        return $result;
    }
}
