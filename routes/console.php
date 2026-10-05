<?php

use App\Models\Competition;
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
