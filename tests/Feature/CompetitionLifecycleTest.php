<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Groups are drawn by the scheduler 30 minutes before the start, and only for approved events. */
class CompetitionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_scheduler_draws_groups_thirty_minutes_before_the_start(): void
    {
        $competition = $this->competition();
        foreach (range(1, 5) as $i) {
            $this->registerPlayer($competition, User::factory()->create());
        }

        $this->travelToMinutesBeforeStart($competition, 31);
        $this->artisan('competitions:sync');
        $this->assertFalse($competition->fresh()->hasGroups());

        $this->travelToMinutesBeforeStart($competition, 29);
        $this->artisan('competitions:sync');
        $competition->refresh();
        $this->assertTrue($competition->hasGroups());
        $this->assertSame(3, $competition->courseHoles()->count());
        $this->assertSame(5, $competition->registrations()->whereNotNull('competition_group_id')->count());

        $this->travelToMinutesBeforeStart($competition, -1);
        $this->artisan('competitions:sync');
        $this->assertSame('ongoing', $competition->fresh()->status);
    }

    public function test_no_groups_are_drawn_for_an_event_waiting_for_approval(): void
    {
        $competition = $this->competition(['is_approved' => false]);

        $this->travelToMinutesBeforeStart($competition, 10);
        $this->artisan('competitions:sync');

        $competition->refresh();
        $this->assertFalse($competition->hasGroups());
        $this->assertSame('upcoming', $competition->status);
    }

    public function test_an_event_approved_before_the_draw_is_open_for_registration(): void
    {
        $competition = $this->competition(['is_approved' => false]);
        $this->travelToMinutesBeforeStart($competition, 60 * 24);
        $this->artisan('competitions:sync');

        $this->actingAs(User::factory()->admin()->create())->post("/competitions/{$competition->id}/approve");

        $this->actingAs(User::factory()->create())
            ->post("/competitions/{$competition->id}/register", ['division' => 'MPO', 'phone' => '+371 20000000'])
            ->assertSessionHas('success');
    }

    public function test_opening_pages_never_changes_a_competition(): void
    {
        $competition = $this->competition();
        $this->registerPlayer($competition, $player = User::factory()->create());
        $this->travelToMinutesBeforeStart($competition, -5);

        $this->actingAs($player)->get('/competitions')->assertOk();
        $this->actingAs($player)->get("/competitions/view/{$competition->id}")->assertOk();
        $this->actingAs($player)->get("/competitions/{$competition->id}/score");

        $competition->refresh();
        $this->assertFalse($competition->hasGroups());
        $this->assertSame('upcoming', $competition->status);
    }
}
