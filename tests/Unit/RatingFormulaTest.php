<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Support\RatingEngine;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/** The rating maths on its own, without a database. */
class RatingFormulaTest extends TestCase
{
    private function course(): Course
    {
        return new Course(['par' => 54, 'par_rating' => 950, 'points_per_stroke' => 7]);
    }

    public function test_round_rating_is_worth_seven_points_per_stroke_around_par(): void
    {
        $course = $this->course();

        $this->assertSame(950, $course->ratingFor(54));
        $this->assertSame(971, $course->ratingFor(51));
        $this->assertSame(936, $course->ratingFor(56));
    }

    public function test_players_are_unrated_until_three_rounds(): void
    {
        $engine = new RatingEngine();
        $day = Carbon::parse('2026-06-01');

        $this->assertNull($engine->playerRating([[$day, 900], [$day->copy()->addDay(), 920]], $day->copy()->addDays(2)));
        $this->assertSame(
            ['rating' => 910, 'rounds' => 3],
            $engine->playerRating([[$day, 900], [$day->copy()->addDay(), 920], [$day->copy()->addDays(2), 910]], $day->copy()->addDays(3))
        );
    }

    public function test_rounds_after_the_rating_date_do_not_count(): void
    {
        $engine = new RatingEngine();
        $day = Carbon::parse('2026-06-01');
        $rounds = [[$day, 900], [$day->copy()->addDay(), 900], [$day->copy()->addDays(2), 900], [$day->copy()->addDays(10), 1000]];

        $this->assertSame(900, $engine->playerRating($rounds, $day->copy()->addDays(5))['rating']);
    }

    public function test_a_course_needs_three_rated_players_to_move(): void
    {
        $engine = new RatingEngine();
        $twoPlayers = collect([['rating' => 950, 'strokes' => 60], ['rating' => 950, 'strokes' => 60]]);

        $this->assertSame(950.0, $engine->adjustCourse(950.0, 7, 54, $twoPlayers));
    }

    public function test_one_tournament_moves_a_course_by_at_most_ten_points(): void
    {
        $engine = new RatingEngine();
        // Thirty 1000-rated players all shooting 20 over: the field says par is worth far more
        $field = collect(array_fill(0, 30, ['rating' => 1000, 'strokes' => 74]));

        $this->assertSame(950.0 + RatingEngine::MAX_COURSE_STEP, $engine->adjustCourse(950.0, 7, 54, $field));
    }
}
