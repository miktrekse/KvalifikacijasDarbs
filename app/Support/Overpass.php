<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Client for the public OpenStreetMap Overpass servers: failover between servers, a time
 * budget per request, a cache with a long-lived stale copy, and a cap on how often visitor
 * requests may go out to the servers at all.
 *
 * The cap matters because cache keys depend on coordinates: anyone (even the shared guest)
 * could otherwise send endless new lat/lon pairs, each holding a PHP worker for up to the
 * whole budget while hammering the public servers. Past the cap a visitor gets cached or
 * curated data instead; the background warm-up (patient()) is never capped.
 */
class Overpass
{
    /**
     * Public Overpass servers in the order we try them, and whether each can run
     * "inside this country's border" (area) queries. Servers that fail or say they
     * are busy are skipped for a while.
     */
    private const ENDPOINTS = [
        'https://overpass-api.de/api/interpreter' => true,
        'https://overpass.openstreetmap.fr/api/interpreter' => false,
        'https://overpass.kumi.systems/api/interpreter' => true,
        'https://overpass.private.coffee/api/interpreter' => true,
    ];

    /** Live lookups one visitor may trigger per window, and all visitors together. */
    public const VISITOR_LOOKUPS = 10;

    public const TOTAL_LOOKUPS = 120;

    public const LOOKUP_WINDOW_SECONDS = 600;

    /** The background warm-up waits this long, and refetches even when a fresh copy exists. */
    private const PATIENT_BUDGET = 120;

    private int $budgetSeconds = 12;

    private bool $refresh = false;

    /** Rate-limit key for whoever made the request: the account, or the IP for the shared guest. */
    public static function visitorKey(Request $request): string
    {
        $user = $request->user();

        return $user && !$user->isGuest() ? 'user:' . $user->id : 'ip:' . $request->ip();
    }

    /** Seconds a visitor may wait on OpenStreetMap in this request (only ever raised). */
    public function allowSeconds(int $seconds): static
    {
        $this->budgetSeconds = max($this->budgetSeconds, $seconds);

        return $this;
    }

    /** Settings for the scheduled warm-up: a long budget, fresh data, no visitor cap. */
    public function patient(): static
    {
        $this->budgetSeconds = self::PATIENT_BUDGET;
        $this->refresh = true;

        return $this;
    }

    public function isRefreshing(): bool
    {
        return $this->refresh;
    }

    /**
     * Returns cached Overpass results, fetching them when missing. A long-lived copy
     * is kept so a later OpenStreetMap outage serves the last good data, not an error.
     * $visitor (see visitorKey) counts the fetch against that visitor's cap.
     */
    public function cached(string $key, array $queries, int $ttl, ?string $visitor = null): ?array
    {
        $key = 'overpass:' . $key;

        $cached = $this->refresh ? null : Cache::get($key);
        if ($cached !== null) {
            return $cached;
        }

        // A lookup that just failed isn't retried for 5 minutes, so nobody waits on it twice
        if (!$this->refresh && Cache::has($key . ':failed')) {
            return Cache::get($key . ':stale');
        }

        if (!$this->refresh && !$this->takeLookup($visitor)) {
            return Cache::get($key . ':stale');
        }

        $elements = $this->run($queries);
        // A busy server can answer a country query with nothing at all; don't let that wipe out a good list
        if ($elements === [] && str_starts_with($key, 'overpass:country:') && !empty(Cache::get($key . ':stale'))) {
            $elements = null;
        }
        if ($elements !== null) {
            Cache::put($key, $elements, $ttl);
            Cache::put($key . ':stale', $elements, now()->addDays(90));
            Cache::forget($key . ':failed');

            return $elements;
        }

        Cache::put($key . ':failed', true, now()->addMinutes(5));

        return Cache::get($key . ':stale');
    }

    /** Counts one live lookup, or says no once the visitor or everyone together used up the window. */
    private function takeLookup(?string $visitor): bool
    {
        $limits = ['overpass-live:all' => self::TOTAL_LOOKUPS];
        if ($visitor) {
            $limits['overpass-live:' . $visitor] = self::VISITOR_LOOKUPS;
        }

        foreach ($limits as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                return false;
            }
        }
        foreach (array_keys($limits) as $key) {
            RateLimiter::hit($key, self::LOOKUP_WINDOW_SECONDS);
        }

        return true;
    }

    /**
     * Runs a query against the public Overpass servers within the time budget.
     * $queries holds an 'area' (precise) and/or 'bbox' (works everywhere) version;
     * each server gets the best version it supports.
     */
    public function run(array $queries): ?array
    {
        $deadline = microtime(true) + $this->budgetSeconds;

        foreach (self::ENDPOINTS as $endpoint => $supportsArea) {
            $query = ($supportsArea ? ($queries['area'] ?? null) : null) ?? $queries['bbox'] ?? null;
            $downKey = 'overpass:down:' . md5($endpoint);
            // Visitors skip servers marked busy; the background warm-up is patient and tries them anyway
            if ($query === null || (!$this->refresh && Cache::has($downKey))) {
                continue;
            }

            for ($attempt = 1; $attempt <= 2; $attempt++) {
                $remaining = $deadline - microtime(true);
                if ($remaining < 3) {
                    return null;
                }

                $timeout = (int) min($this->refresh ? 100 : 30, $remaining);
                $startedAt = microtime(true);

                try {
                    $response = Http::withHeaders(['User-Agent' => 'DiscStats/1.0'])
                        ->acceptJson()
                        ->connectTimeout(4)
                        ->timeout($timeout)
                        ->get($endpoint, ['data' => $this->refresh ? str_replace('[timeout:25]', '[timeout:90]', $query) : $query])
                        ->throw();

                    // Overloaded servers can answer 200 with an error remark and no elements;
                    // that is a failure, not "this country has no courses".
                    $remark = (string) $response->json('remark', '');
                    if ($remark !== '' && preg_match('/error|timed out|out of memory|too busy/i', $remark)) {
                        Cache::put($downKey, true, now()->addMinutes(5));
                        continue 2;
                    }

                    return $response->json('elements', []);
                } catch (ConnectionException $exception) {
                    // Couldn't connect: skip this server for an hour. No answer after 10+ seconds: skip it
                    // for 10 minutes. A short attempt cut off by our own time budget says nothing about it.
                    $couldNotConnect = microtime(true) - $startedAt < $timeout - 1;
                    if ($couldNotConnect) {
                        Cache::put($downKey, true, now()->addHour());
                    } elseif ($timeout >= 10) {
                        Cache::put($downKey, true, now()->addMinutes(10));
                    }
                    continue 2;
                } catch (RequestException $exception) {
                    $status = $exception->response->status();
                    // Rate limited: one short retry. Overloaded (504) or rate limited twice: rest it for a few minutes.
                    if ($status === 429 && $attempt === 1) {
                        usleep(1_500_000);
                        continue;
                    }
                    if (in_array($status, [429, 504], true)) {
                        Cache::put($downKey, true, now()->addMinutes($status === 504 ? 5 : 2));
                    }
                    continue 2;
                }
            }
        }

        return null;
    }
}
