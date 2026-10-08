<?php

use App\Http\Controllers\CourseController;
use App\Models\Competition;
use App\Support\RatingEngine;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('competitions:sync', function () {
    // Only competitions happening today (or overdue) can need groups or a status change.
    Competition::whereIn('status', ['upcoming', 'ongoing'])
        ->whereDate('event_date', '<=', now()->addDay()->toDateString())
        ->get()
        ->each(fn (Competition $competition) => $competition->syncLifecycle());
})->purpose('Draw competition groups 30 minutes before the start and open scoring');

Schedule::command('competitions:sync')->everyMinute();

Artisan::command('ratings:recalculate', function () {
    // Replays every completed tournament in date order: course ratings, round ratings and player ratings
    $result = app(RatingEngine::class)->recalculate();
    $this->info("Rated {$result['rounds']} rounds across {$result['events']} tournaments; {$result['rated_players']} players now have a rating.");
})->purpose('Recalculate all course, round and player ratings from tournament results');

Artisan::command('courses:warm {countries=all : Comma-separated country codes, or "all"}', function (string $countries) {
    // Public OpenStreetMap servers are often rate-limited, so fetch course pins ahead of time
    // and let map pages read them straight from the cache.
    $list = strtolower($countries) === 'all'
        ? array_keys(CourseController::COUNTRY_BOXES)
        : array_filter(array_map('trim', explode(',', strtoupper($countries))));

    // The public servers are often busy for a few minutes at a time, so countries that fail
    // get up to two more passes after a short rest.
    $pending = array_values($list);
    for ($pass = 1; $pass <= 3 && $pending; $pass++) {
        if ($pass > 1) {
            $this->line('Retrying ' . implode(', ', $pending) . ' in a minute…');
            sleep(60);
        }

        $failed = [];
        foreach ($pending as $i => $country) {
            if ($i > 0) {
                sleep(3); // be polite to the shared public servers
            }
            $count = app(CourseController::class)->warm($country);
            if ($count !== null) {
                $this->info("{$country}: {$count} courses cached");
            } else {
                $failed[] = $country;
            }
        }
        $pending = $failed;
    }

    foreach ($pending as $country) {
        $this->warn("{$country}: OpenStreetMap busy, kept the previous cache");
    }

    $this->call('courses:name', ['countries' => $countries]);
})->purpose('Pre-load course map data from OpenStreetMap');

Artisan::command('courses:name {countries=all : Comma-separated country codes, or "all"}', function (string $countries) {
    // Unnamed OpenStreetMap courses get a name from Disc Golf Metrix, or the park or town they lie in.
    // Lookups are cached for months, so only newly mapped courses cost any requests after the first run.
    $list = strtolower($countries) === 'all'
        ? array_keys(CourseController::COUNTRY_BOXES)
        : array_filter(array_map('trim', explode(',', strtoupper($countries))));

    foreach ($list as $country) {
        $named = app(CourseController::class)->name($country);
        $this->info("{$country}: {$named} unnamed courses named");
    }
})->purpose('Find names for courses that are unnamed on OpenStreetMap');

Schedule::command('courses:warm')->dailyAt('04:30');
