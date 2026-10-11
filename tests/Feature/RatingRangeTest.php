<?php

namespace Tests\Feature;

use App\Models\RoundRating;
use App\Models\TrainingRoundShot;
use App\Models\User;
use App\Support\RatingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The worst legal scorecard still produces ratings the unsigned columns can store. */
class RatingRangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_highest_legal_scores_are_rated_without_breaking_the_columns(): void
    {
        $player = User::factory()->create();

        foreach (range(1, 3) as $event) {
            [$competition, $field] = $this->startedCompetition(3, ['holes' => 18]);
            $this->registerPlayer($competition, $player);
            $competition->registrations()->update(['competition_group_id' => $competition->groups()->value('id')]);

            $field->each(fn (User $other) => $this->finishRound($competition, $other));
            $this->finishRound($competition, $player, TrainingRoundShot::MAX_HOLE_SCORE);
            $competition->update(['status' => 'completed']);
        }

        app(RatingEngine::class)->recalculate();

        $rounds = RoundRating::where('user_id', $player->id)->get();
        $this->assertCount(3, $rounds);
        $this->assertSame([0, 0, 0], $rounds->pluck('round_rating')->all());
        $this->assertSame(0, $rounds->sortBy('played_at')->last()->rating_after);
        $this->assertSame(0, $player->fresh()->rating);
    }
}
