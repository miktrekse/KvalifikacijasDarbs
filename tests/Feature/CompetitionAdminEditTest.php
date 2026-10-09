<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\User;
use App\Support\CompetitionGrouping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompetitionAdminEditTest extends TestCase
{
    use RefreshDatabase;

    /** The edit form as the admin would submit it, unchanged. */
    private function form(Competition $competition, array $changes = []): array
    {
        return $changes + [
            'name' => $competition->name,
            'event_date' => $competition->event_date->toDateString(),
            'start_time' => substr($competition->start_time, 0, 5),
            'course_name' => $competition->course_name,
            'competition_type' => $competition->competition_type,
            'format' => $competition->format,
            'division_options' => $competition->divisionsArray,
            'holes' => $competition->holes,
            'status' => $competition->status,
            'is_approved' => 1,
            'is_public' => 1,
        ];
    }

    private function drawn(): Competition
    {
        $competition = $this->competition();
        foreach (range(1, 4) as $i) {
            $this->registerPlayer($competition, User::factory()->create());
        }
        CompetitionGrouping::assign($competition);

        return $competition->refresh();
    }

    public function test_round_defining_fields_are_locked_once_groups_are_drawn(): void
    {
        $competition = $this->drawn();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put("/competitions/{$competition->id}", $this->form($competition, ['holes' => 9]))->assertSessionHasErrors('holes');
        $this->actingAs($admin)->put("/competitions/{$competition->id}", $this->form($competition, ['course_name' => 'Another Park']))->assertSessionHasErrors('course_name');
        $this->actingAs($admin)->put("/competitions/{$competition->id}", $this->form($competition, ['start_time' => '12:00']))->assertSessionHasErrors('start_time');
        $this->assertSame(3, $competition->fresh()->holes);
        $this->assertSame(3, $competition->courseHoles()->count());

        // Everything else can still be edited
        $this->actingAs($admin)->put("/competitions/{$competition->id}", $this->form($competition, ['name' => 'Riga Open 2026']))->assertSessionHasNoErrors();
        $this->assertSame('Riga Open 2026', $competition->fresh()->name);
    }

    public function test_before_the_draw_the_whole_event_can_change(): void
    {
        $competition = $this->competition();

        $this->actingAs(User::factory()->admin()->create())
            ->put("/competitions/{$competition->id}", $this->form($competition, ['holes' => 9, 'start_time' => '12:00']))
            ->assertSessionHasNoErrors();

        $this->assertSame(9, $competition->fresh()->holes);
    }

    public function test_status_changes_must_match_what_happened(): void
    {
        $admin = User::factory()->admin()->create();
        $upcoming = $this->competition();

        $this->actingAs($admin)->put("/competitions/{$upcoming->id}", $this->form($upcoming, ['status' => 'completed']))->assertSessionHasErrors('status');
        $this->actingAs($admin)->put("/competitions/{$upcoming->id}", $this->form($upcoming, ['status' => 'ongoing']))->assertSessionHasErrors('status');
        $this->assertSame('upcoming', $upcoming->fresh()->status);

        // Drawn but nobody has finished a round yet
        $drawn = $this->drawn();
        $this->actingAs($admin)->put("/competitions/{$drawn->id}", $this->form($drawn, ['status' => 'completed']))->assertSessionHasErrors('status');

        // Cancelling is always possible
        $this->actingAs($admin)->put("/competitions/{$upcoming->id}", $this->form($upcoming, ['status' => 'cancelled']))->assertSessionHasNoErrors();
    }

    public function test_custom_division_rules_may_leave_out_limits_but_not_contradict_themselves(): void
    {
        $verified = User::factory()->verifiedPlayer()->create();
        $base = [
            'name' => 'Club Day', 'event_date' => now()->addWeek()->toDateString(), 'start_time' => '10:00',
            'competition_type' => 'singles', 'format' => 'stroke_play', 'holes' => 9, 'is_public' => 1,
        ];

        // Only name and gender sent: used to crash on the missing keys
        $this->actingAs($verified)->post('/competitions', $base + ['division_rules' => [['name' => 'Club', 'gender' => 'any']]])
            ->assertSessionHasNoErrors();
        $this->assertSame(['gender' => 'any', 'min_age' => null, 'max_age' => null, 'min_rating' => null], Competition::sole()->division_rules['Club']);

        $this->actingAs($verified)->post('/competitions', $base + ['division_rules' => [['name' => 'Nobody', 'gender' => 'any', 'min_age' => 60, 'max_age' => 20]]])
            ->assertSessionHasErrors('division_rules');
        $this->assertSame(1, Competition::count());
    }

    public function test_numbers_must_fit_the_database_columns(): void
    {
        $verified = User::factory()->verifiedPlayer()->create();
        $base = [
            'name' => 'Club Day', 'event_date' => now()->addWeek()->toDateString(), 'start_time' => '10:00',
            'competition_type' => 'singles', 'format' => 'stroke_play', 'holes' => 9,
        ];

        $this->actingAs($verified)->post('/competitions', ['entry_fee' => 100000] + $base)->assertSessionHasErrors('entry_fee');
        $this->actingAs($verified)->post('/competitions', ['entry_fee' => 10.555] + $base)->assertSessionHasErrors('entry_fee');
        $this->actingAs($verified)->post('/competitions', ['max_participants' => 1001] + $base)->assertSessionHasErrors('max_participants');
        $this->actingAs($verified)->post('/competitions', ['holes' => 99] + $base)->assertSessionHasErrors('holes');
        $this->actingAs($verified)->post('/competitions', ['description' => str_repeat('a', 5001)] + $base)->assertSessionHasErrors('description');
        $this->actingAs($verified)->post('/competitions', $base + ['registration_deadline' => now()->addWeeks(2)->format('Y-m-d\TH:i')])
            ->assertSessionHasErrors('registration_deadline');

        $this->assertSame(0, Competition::count());
    }
}
