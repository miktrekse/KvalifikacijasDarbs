<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CompetitionRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private array $form = ['division' => 'MPO', 'phone' => '+371 20000000'];

    public function test_a_player_can_register_and_change_their_registration(): void
    {
        $competition = $this->competition(['divisions' => json_encode(['MPO', 'MA1'])]);
        $player = User::factory()->create();

        $this->actingAs($player)->post("/competitions/{$competition->id}/register", $this->form)->assertSessionHas('success');
        $this->actingAs($player)->post("/competitions/{$competition->id}/register", ['division' => 'MA1'] + $this->form);

        $this->assertSame('MA1', $competition->registrations()->sole()->division);
    }

    public function test_hidden_competitions_cannot_be_registered_for_by_id(): void
    {
        $pending = $this->competition(['is_approved' => false]);
        $private = $this->competition(['is_public' => false]);
        $player = User::factory()->create();

        foreach ([$pending, $private] as $competition) {
            $this->actingAs($player)->get("/competitions/view/{$competition->id}")->assertNotFound();
            $this->actingAs($player)->post("/competitions/{$competition->id}/register", $this->form)->assertNotFound();
        }

        $this->assertDatabaseCount('competition_registrations', 0);
    }

    public function test_registration_closes_at_the_deadline(): void
    {
        $competition = $this->competition(['registration_deadline' => now()->subMinute()]);

        $this->actingAs(User::factory()->create())->post("/competitions/{$competition->id}/register", $this->form)
            ->assertSessionHasErrors(['division' => 'The registration deadline has passed.']);

        $this->assertDatabaseCount('competition_registrations', 0);
    }

    public function test_the_deadline_is_read_as_local_course_time(): void
    {
        // Deadline typed as 18:00 in Riga (UTC+3 in summer) passes at 15:00 UTC
        $competition = $this->competition(['event_date' => '2026-07-10', 'registration_deadline' => '2026-07-05 18:00:00']);

        Carbon::setTestNow(Carbon::parse('2026-07-05 15:30:00', 'UTC'));
        $this->assertSame('The registration deadline has passed.', $competition->fresh()->registrationClosedReason());

        Carbon::setTestNow(Carbon::parse('2026-07-05 14:30:00', 'UTC'));
        $this->assertNull($competition->fresh()->registrationClosedReason());
    }

    public function test_a_full_competition_takes_no_new_players_but_registered_ones_can_still_change(): void
    {
        $competition = $this->competition(['max_participants' => 1, 'divisions' => json_encode(['MPO', 'MA1'])]);
        $first = User::factory()->create();

        $this->actingAs($first)->post("/competitions/{$competition->id}/register", $this->form)->assertSessionHas('success');
        $this->actingAs(User::factory()->create())->post("/competitions/{$competition->id}/register", $this->form)
            ->assertSessionHasErrors(['division' => 'This competition is full.']);
        $this->actingAs($first)->post("/competitions/{$competition->id}/register", ['division' => 'MA1'] + $this->form)
            ->assertSessionHas('success');

        $this->assertSame(1, $competition->registrations()->count());
    }

    public function test_registration_closes_at_draw_time_even_before_the_scheduler_runs(): void
    {
        $competition = $this->competition();
        $this->travelToMinutesBeforeStart($competition, 20);

        $this->actingAs(User::factory()->create())->post("/competitions/{$competition->id}/register", $this->form)
            ->assertSessionHasErrors('division');

        $this->assertDatabaseCount('competition_registrations', 0);
    }

    public function test_a_player_can_withdraw_before_the_draw_but_not_after(): void
    {
        $competition = $this->competition();
        $player = User::factory()->create();
        $this->registerPlayer($competition, $player);

        $this->actingAs($player)->get("/competitions/view/{$competition->id}")->assertSee('Withdraw');
        $this->actingAs($player)->delete("/competitions/{$competition->id}/register")->assertSessionHas('success');
        $this->assertDatabaseCount('competition_registrations', 0);

        $this->registerPlayer($competition, $player);
        $competition->forceFill(['groups_assigned_at' => now()])->save();

        $this->actingAs($player)->delete("/competitions/{$competition->id}/register")->assertSessionHas('error');
        $this->assertDatabaseCount('competition_registrations', 1);
    }

    public function test_the_page_says_why_registration_is_closed(): void
    {
        $competition = $this->competition(['registration_deadline' => now()->subHour()]);

        $this->actingAs(User::factory()->create())->get("/competitions/view/{$competition->id}")
            ->assertSee('The registration deadline has passed.')
            ->assertDontSee('Register for this competition');
    }
}
