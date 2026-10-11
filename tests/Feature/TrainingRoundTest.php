<?php

namespace Tests\Feature;

use App\Models\TrainingRound;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Shared practice rounds: who may close them, when, and safe shot numbering. */
class TrainingRoundTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $friend;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->friend = User::factory()->create();
    }

    private function startRound(): TrainingRound
    {
        $this->actingAs($this->owner)->post('/training', [
            'course_name' => 'Test Park',
            'holes_count' => 3,
            'player_ids' => [$this->friend->id],
        ]);

        return TrainingRound::with('holes')->sole();
    }

    private function score(TrainingRound $round, User $player, array $holeNumbers): void
    {
        foreach ($round->holes->whereIn('number', $holeNumbers) as $hole) {
            $this->actingAs($this->owner)
                ->postJson("/training/{$round->id}/holes/{$hole->id}/score", ['user_id' => $player->id, 'strokes' => 3])
                ->assertOk();
        }
    }

    public function test_an_invited_player_cannot_close_the_round_for_everyone(): void
    {
        $round = $this->startRound();
        $this->score($round, $this->owner, [1, 2, 3]);
        $this->score($round, $this->friend, [1, 2, 3]);

        $this->actingAs($this->friend)->post("/training/{$round->id}/complete")->assertForbidden();
        $this->assertSame('in_progress', $round->fresh()->status);
    }

    public function test_only_the_owner_sees_the_finish_button(): void
    {
        $round = $this->startRound();
        $this->score($round, $this->owner, [1]);

        $this->actingAs($this->owner)->get("/training/{$round->id}")->assertOk()->assertSee('Finish early');
        $this->actingAs($this->friend)->get("/training/{$round->id}")->assertOk()->assertDontSee('Finish early')->assertDontSee('Finish round');
    }

    public function test_an_empty_round_cannot_be_finished(): void
    {
        $round = $this->startRound();

        $this->actingAs($this->owner)->post("/training/{$round->id}/complete", ['finish_early' => 1])->assertSessionHas('error');
        $this->assertSame('in_progress', $round->fresh()->status);
    }

    public function test_open_holes_need_an_explicit_finish_early(): void
    {
        $round = $this->startRound();
        $this->score($round, $this->owner, [1, 2, 3]);
        $this->score($round, $this->friend, [1]);

        $this->actingAs($this->owner)->post("/training/{$round->id}/complete")->assertSessionHas('error', '2 holes still open. Use "Finish early" to close the round anyway.');
        $this->assertSame('in_progress', $round->fresh()->status);

        $this->actingAs($this->owner)->post("/training/{$round->id}/complete", ['finish_early' => 1])->assertSessionHas('success');
        $this->assertSame('completed', $round->fresh()->status);

        // Finished early still counts as unfinished for the player who didn't hole out everywhere
        $round = $round->fresh()->load('holes.shots');
        $this->assertTrue($round->isFinishedBy($this->owner->id));
        $this->assertFalse($round->isFinishedBy($this->friend->id));
    }

    public function test_a_fully_scored_round_finishes_and_can_be_reopened_by_its_owner(): void
    {
        $round = $this->startRound();
        $this->score($round, $this->owner, [1, 2, 3]);
        $this->score($round, $this->friend, [1, 2, 3]);

        $this->actingAs($this->owner)->post("/training/{$round->id}/complete")->assertSessionHas('success');
        $this->assertSame('completed', $round->fresh()->status);

        $this->actingAs($this->friend)->post("/training/{$round->id}/reopen")->assertForbidden();
        $this->actingAs($this->owner)->post("/training/{$round->id}/reopen")->assertSessionHas('success');
        $this->assertSame('in_progress', $round->fresh()->status);
        $this->assertNull($round->fresh()->completed_at);
    }

    public function test_when_the_owner_leaves_the_round_the_others_can_finish_it(): void
    {
        $round = $this->startRound();
        $this->score($round, $this->friend, [1, 2, 3]);

        $this->actingAs($this->owner)->delete("/training/{$round->id}");
        $this->actingAs($this->friend)->post("/training/{$round->id}/complete")->assertSessionHas('success');

        $this->assertSame('completed', $round->fresh()->status);
    }

    public function test_shots_are_numbered_in_order_and_a_duplicate_tap_gets_a_conflict(): void
    {
        $round = $this->startRound();
        $hole = $round->holes->first();
        $url = "/training/{$round->id}/holes/{$hole->id}/shots";

        $this->actingAs($this->owner)->postJson($url, ['user_id' => $this->owner->id, 'result' => 'fairway'])->assertOk();
        $this->actingAs($this->owner)->postJson($url, ['user_id' => $this->owner->id, 'result' => 'circle_1'])->assertOk();
        $this->assertSame([1, 2], $hole->shots()->pluck('shot_number')->all());

        // Another phone already holds the number this tap is about to take
        $hole->shots()->create(['user_id' => $this->friend->id, 'shot_number' => 2, 'result' => 'fairway', 'strokes' => 1]);
        $this->actingAs($this->friend)->postJson($url, ['user_id' => $this->friend->id, 'result' => 'fairway'])
            ->assertStatus(409);
    }
}
