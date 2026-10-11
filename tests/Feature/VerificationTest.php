<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\RatingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** "Verified" is earned by playing three tournaments to the end, not by registering for them. */
class VerificationTest extends TestCase
{
    use RefreshDatabase;

    /** A completed tournament in which only the given players holed out on every hole. */
    private function playTournament(User $player, bool $finishes): void
    {
        [$competition, $field] = $this->startedCompetition(3);
        $this->registerPlayer($competition, $player);
        $competition->registrations()->update(['competition_group_id' => $competition->groups()->value('id')]);

        $field->each(fn (User $other) => $this->finishRound($competition, $other));
        if ($finishes) {
            $this->finishRound($competition, $player);
        }

        $competition->update(['status' => 'completed']);
        app(RatingEngine::class)->recalculate();
        $player->refresh()->syncVerification();
    }

    public function test_three_finished_tournaments_make_a_player_verified(): void
    {
        $player = User::factory()->create();

        $this->playTournament($player, true);
        $this->playTournament($player, true);
        $this->assertSame('user', $player->fresh()->role);

        $this->playTournament($player, true);
        $this->assertSame('verified', $player->fresh()->role);
    }

    public function test_registering_without_finishing_does_not_count(): void
    {
        $player = User::factory()->create();

        $this->playTournament($player, false);
        $this->playTournament($player, false);
        $this->playTournament($player, true);

        $this->assertSame(1, $player->completedCompetitionsCount());
        $this->assertSame('user', $player->fresh()->role);
    }
}
