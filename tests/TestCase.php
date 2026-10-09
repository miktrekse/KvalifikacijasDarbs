<?php

namespace Tests;

use App\Models\Competition;
use App\Models\CompetitionRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Pages render without a compiled front-end build (npm run build)
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        // Tests that travel in time never leak a frozen clock into the next test
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** An approved, public 3-hole stroke-play event a week from now (registration open). */
    protected function competition(array $attributes = []): Competition
    {
        return Competition::forceCreate($attributes + [
            'user_id' => $attributes['user_id'] ?? User::factory()->verifiedPlayer()->create()->id,
            'name' => 'Riga Open',
            'event_date' => now(config('app.competition_timezone'))->addWeek()->toDateString(),
            'start_time' => '10:00',
            'course_name' => 'Test Park',
            'format' => 'stroke_play',
            'competition_type' => 'singles',
            'divisions' => json_encode(['MPO']),
            'division_rules' => [],
            'holes' => 3,
            'status' => 'upcoming',
            'is_approved' => true,
            'is_public' => true,
        ]);
    }

    protected function registerPlayer(Competition $competition, User $player, string $division = 'MPO'): CompetitionRegistration
    {
        return CompetitionRegistration::create([
            'competition_id' => $competition->id,
            'user_id' => $player->id,
            'division' => $division,
            'phone' => '+371 20000000',
            'rating' => $player->rating,
        ]);
    }

    /** Freezes the clock this many minutes before (negative: after) the event's start. */
    protected function travelToMinutesBeforeStart(Competition $competition, int $minutes): void
    {
        Carbon::setTestNow($competition->startsAt()->subMinutes($minutes));
    }
}
