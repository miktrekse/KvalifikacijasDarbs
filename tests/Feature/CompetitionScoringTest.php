<?php

namespace Tests\Feature;

use App\Jobs\RecalculateRatings;
use App\Models\Competition;
use App\Models\RoundRating;
use App\Models\User;
use App\Support\RatingEngine;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

/** Score entry under load: duplicate taps, simultaneous last putts and the rating replay. */
class CompetitionScoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_duplicate_throw_gets_a_clear_conflict_instead_of_a_server_error(): void
    {
        [$competition, $field] = $this->startedCompetition();
        $scorer = $field->first();
        $hole = $competition->courseHoles->first();

        // A row the database already holds under the number this tap is about to take
        $hole->shots()->create([
            'user_id' => $scorer->id, 'recorded_by' => $scorer->id, 'shot_number' => 2,
            'result' => 'fairway', 'strokes' => 1,
        ]);

        $this->actingAs($scorer)
            ->postJson("/competitions/{$competition->id}/holes/{$hole->id}/shots", ['user_id' => $scorer->id, 'result' => 'fairway'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'That throw was already saved from another tap. Your scorecard will refresh.');
    }

    public function test_shots_are_numbered_in_order(): void
    {
        [$competition, $field] = $this->startedCompetition();
        $scorer = $field->first();
        $hole = $competition->courseHoles->first();

        foreach (['fairway', 'circle_1', 'in_basket'] as $result) {
            $this->actingAs($scorer)
                ->postJson("/competitions/{$competition->id}/holes/{$hole->id}/shots", ['user_id' => $scorer->id, 'result' => $result])
                ->assertOk();
        }

        $this->assertSame([1, 2, 3], $hole->shots()->pluck('shot_number')->all());
    }

    public function test_the_last_putt_completes_the_event_and_queues_the_ratings(): void
    {
        Queue::fake();
        [$competition, $field] = $this->startedCompetition();
        $last = $field->pop();
        $field->each(fn (User $player) => $this->finishRound($competition, $player));

        $holes = $competition->courseHoles;
        foreach ($holes->slice(0, -1) as $hole) {
            $this->actingAs($last)->postJson("/competitions/{$competition->id}/holes/{$hole->id}/score", ['user_id' => $last->id, 'strokes' => 3])
                ->assertJsonPath('finished', false);
        }
        $this->actingAs($last)->postJson("/competitions/{$competition->id}/holes/{$holes->last()->id}/score", ['user_id' => $last->id, 'strokes' => 3])
            ->assertJsonPath('finished', true);

        // The request only flips the status; the replay runs on the queue
        $this->assertSame('completed', $competition->fresh()->status);
        $this->assertSame(0, RoundRating::count());
        Queue::assertPushed(RecalculateRatings::class, 1);

        app()->call([new RecalculateRatings(), 'handle']);
        $this->assertSame(4, RoundRating::where('competition_id', $competition->id)->count());
    }

    public function test_two_simultaneous_last_putts_rate_the_event_once(): void
    {
        Queue::fake();
        [$competition, $field] = $this->startedCompetition();
        $field->each(fn (User $player) => $this->finishRound($competition, $player));

        // Both requests loaded the event while it was still ongoing
        $first = Competition::find($competition->id);
        $second = Competition::find($competition->id);

        $this->assertTrue($first->finishIfComplete());
        $this->assertTrue($second->finishIfComplete());

        Queue::assertPushed(RecalculateRatings::class, 1);
    }

    public function test_the_rating_replay_runs_inside_the_global_lock(): void
    {
        $lock = Mockery::mock(Lock::class);
        $lock->shouldReceive('block')->once()->andReturnUsing(fn (int $seconds, \Closure $replay) => $replay());
        Cache::shouldReceive('lock')->once()->with('ratings:recalculate', 600)->andReturn($lock);

        $this->assertSame(['events' => 0, 'rounds' => 0, 'rated_players' => 0], app(RatingEngine::class)->recalculate());
    }
}
