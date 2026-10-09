<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\User;
use App\Support\CompetitionGrouping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A tournament is played (and later rated) on the layout picked when it was created. */
class CompetitionCourseLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_layout_picked_on_the_map_is_stored_and_used_for_the_holes(): void
    {
        $layout = [
            ['number' => 1, 'par' => 3, 'distance_m' => 70],
            ['number' => 2, 'par' => 4, 'distance_m' => 160],
            ['number' => 3, 'par' => 5, 'distance_m' => 240],
        ];

        $this->actingAs(User::factory()->admin()->create())->post('/competitions', [
            'name' => 'Layout Cup', 'event_date' => now()->addWeek()->toDateString(), 'start_time' => '10:00',
            'course_name' => 'Some OSM Park', 'course_lat' => 56.95, 'course_lon' => 24.1,
            'holes_data' => json_encode($layout), 'holes' => 3,
            'competition_type' => 'singles', 'format' => 'stroke_play', 'division_options' => ['MPO'],
        ])->assertSessionHasNoErrors();

        $competition = Competition::sole();
        $this->assertSame($layout, $competition->course_layout);
        $this->assertEqualsWithDelta(56.95, $competition->course_lat, 0.0001);

        CompetitionGrouping::assign($competition);

        $this->assertSame([3, 4, 5], $competition->courseHoles()->pluck('par')->all());
        $this->assertSame([70, 160, 240], $competition->courseHoles()->pluck('distance_m')->all());
    }

    public function test_a_course_picked_by_coordinates_uses_its_curated_layout(): void
    {
        // Priekuļi curated course, 18 holes: hole 5 is a par 4
        $competition = $this->competition(['course_name' => 'Priekuļi', 'course_lat' => 57.3117, 'course_lon' => 25.3711, 'holes' => 18]);

        CompetitionGrouping::assign($competition);

        $this->assertSame(4, $competition->courseHoles()->where('number', 5)->value('par'));
        $this->assertSame(59, (int) $competition->courseHoles()->sum('par'));
    }

    public function test_a_course_name_that_only_shares_a_word_with_a_curated_course_is_not_matched(): void
    {
        // Used to pick up the 18-hole "Ventspils Disku Golfa Parks" layout (par 61) just from the word "Ventspils"
        $competition = $this->competition(['course_name' => 'Ventspils Open Field', 'holes' => 18]);

        CompetitionGrouping::assign($competition);

        $this->assertSame(54, (int) $competition->courseHoles()->sum('par'));
    }

    public function test_an_exact_curated_course_name_still_finds_its_layout(): void
    {
        $competition = $this->competition(['course_name' => 'Ventspils disku golfa parks', 'holes' => 18]);

        CompetitionGrouping::assign($competition);

        $this->assertSame(61, (int) $competition->courseHoles()->sum('par'));
    }
}
