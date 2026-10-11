<?php

namespace App\Providers;

use App\Support\Overpass;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        // Public forms (no account needed) are limited per visitor IP
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
        RateLimiter::for('register', fn (Request $request) => Limit::perHour(5)->by($request->ip()));
        RateLimiter::for('guest-login', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perMinute(3)->by('reset-ip:' . $request->ip()),
            Limit::perHour(5)->by('reset-email:' . strtolower((string) $request->input('email'))),
        ]);

        // Writes that add rows other players see (drills, comments), limited per account
        RateLimiter::for('content', fn (Request $request) => Limit::perMinute(10)->by('content:' . ($request->user()?->id ?? $request->ip())));

        // Course map lookups can wait on OpenStreetMap, so they are limited per account
        // (per IP for the shared guest). Overpass caps the live lookups separately.
        RateLimiter::for('courses', fn (Request $request) => Limit::perMinute(30)->by('courses:' . Overpass::visitorKey($request)));
    }
}
