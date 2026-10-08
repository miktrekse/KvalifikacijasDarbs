<?php

namespace App\Http\Controllers;

use App\Support\CourseNamer;
use App\Support\CuratedCourses;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CourseController extends Controller
{
    /**
     * Public Overpass servers in the order we try them, and whether each can run
     * "inside this country's border" (area) queries. Servers that fail or say they
     * are busy are skipped for a while.
     */
    private const OVERPASS_ENDPOINTS = [
        'https://overpass-api.de/api/interpreter' => true,
        'https://overpass.openstreetmap.fr/api/interpreter' => false,
        'https://overpass.kumi.systems/api/interpreter' => true,
        'https://overpass.private.coffee/api/interpreter' => true,
    ];

    /**
     * Bounding boxes (south,west,north,east) for each supported country; big countries
     * are split into chunks so each query stays small. Used for the rectangle queries
     * that work on every server, and to narrow area queries for the chunked countries.
     */
    public const COUNTRY_BOXES = [
        'LV' => ['55.6,20.9,58.1,28.3'],
        'GB' => ['49.8,-8.7,60.9,1.8'],
        'IE' => ['51.4,-10.7,55.4,-5.9'],
        'DE' => ['47.2,5.8,55.1,15.1'],
        'NL' => ['50.7,3.3,53.6,7.3'],
        'DK' => ['54.5,8.0,57.8,15.2'],
        'SE' => ['55.3,10.9,69.1,24.2'],
        'NO' => ['57.9,4.5,71.2,31.2'],
        'FI' => ['59.7,20.5,70.1,31.6'],
        'AT' => ['46.3,9.5,49.1,17.2'],
        'CH' => ['45.8,5.9,47.9,10.5'],
        'FR' => ['41.3,-5.2,51.1,9.6'],
        'ES' => ['35.9,-9.4,43.8,4.4'],
        'IT' => ['36.6,6.6,47.1,18.6'],
        'NZ' => ['-47.4,166.3,-34.3,178.6'],
        'US' => [
            '24.3,-125,33,-110', '24.3,-110,33,-96', '24.3,-96,33,-84', '24.3,-84,33,-66.9',
            '33,-125,41,-110', '33,-110,41,-96', '33,-96,41,-84', '33,-84,41,-66.9',
            '41,-125,49.4,-110', '41,-110,49.4,-96', '41,-96,49.4,-84', '41,-84,49.4,-66.9',
        ],
        'CA' => [
            '41.7,-141,50,-110', '41.7,-110,50,-85', '41.7,-85,50,-52.6',
            '50,-141,83.2,-110', '50,-110,83.2,-85', '50,-85,83.2,-52.6',
        ],
        'AU' => [
            '-43.7,113,-30,133', '-43.7,133,-35,153.7', '-35,140,-30,153.7',
            '-30,113,-10.7,133', '-30,133,-10.7,153.7', '-35,133,-30,140',
        ],
    ];

    // Course pins rarely change, so cached results are reused for hours
    private const COUNTRY_TTL = 12 * 3600;
    private const NEARBY_TTL = 6 * 3600;
    private const HOLES_TTL = 24 * 3600;

    /**
     * Seconds a visitor may wait on OpenStreetMap. Picking a country is a deliberate search,
     * so it may wait longer; "near me" and hole lookups fall back to cached data sooner.
     */
    private const COUNTRY_BUDGET = 35;
    private const NEARBY_BUDGET = 12;

    private int $budgetSeconds = self::NEARBY_BUDGET;

    /** The cache warmer refetches even when a fresh copy exists. */
    private bool $refresh = false;

    /**
     * Pre-loads a country's courses into the cache (used by the daily courses:warm job),
     * with a more patient budget than a visitor gets. Returns how many courses were cached,
     * or null if OpenStreetMap could not be reached.
     */
    public function warm(string $country): ?int
    {
        $this->budgetSeconds = 120;
        $this->refresh = true;

        $response = $this->data(Request::create('/courses/data', 'GET', ['country' => $country]));

        return $response->getStatusCode() === 200 && ($response->getData(true)['source'] ?? null) === 'OpenStreetMap'
            ? (int) $response->getData(true)['total']
            : null;
    }

    /**
     * Looks up names for a country's unnamed courses (Disc Golf Metrix, then the park or
     * town they lie in) and caches them for map requests. Returns how many were named.
     */
    public function name(string $country): int
    {
        $this->budgetSeconds = 120;
        $this->refresh = true;

        return app(CourseNamer::class)->resolve(
            $country,
            $this->cachedCountryElements([$country]),
            fn (string $query) => $this->runOverpassQuery(['area' => $query]),
        );
    }

    public function index()
    {
        return view('courses.index');
    }

    public function data(Request $request)
    {
        $latitude = $request->query('lat');
        $longitude = $request->query('lon');
        $isNearbySearch = is_numeric($latitude) && is_numeric($longitude)
            && $latitude >= -90 && $latitude <= 90
            && $longitude >= -180 && $longitude <= 180;
        $country = strtoupper((string) $request->query('country', 'GB'));
        $countries = array_keys(self::COUNTRY_BOXES);

        if (!$isNearbySearch && !in_array($country, $countries, true)) {
            return response()->json([
                'courses' => [],
                'total' => 0,
                'source' => 'OpenStreetMap',
                'message' => 'Choose a country to search its OpenStreetMap disc golf courses.',
            ]);
        }

            $successfulQuery = false;
        try {
            $nearbyBounds = null;
            $nearbyKey = null;
            if ($isNearbySearch) {
                // Round the centre to ~5 km so people searching from nearby spots share one cached
                // query; the box gets a 10 km margin and results are trimmed to the exact 150 km below.
                $centreLat = round((float) $latitude / 0.05) * 0.05;
                $centreLon = round((float) $longitude / 0.05) * 0.05;
                $latitudeDelta = 160 / 111.32;
                $longitudeDelta = 160 / (111.32 * max(cos(deg2rad($centreLat)), 0.1));
                $nearbyBounds = ($centreLat - $latitudeDelta) . ',' . ($centreLon - $longitudeDelta) . ','
                    . ($centreLat + $latitudeDelta) . ',' . ($centreLon + $longitudeDelta);
                $nearbyKey = sprintf('%.2f,%.2f', $centreLat, $centreLon);
            }

            $wanted = '(nwr["leisure"="disc_golf_course"]%1$s;nwr["sport"="disc_golf"]%1$s;);out center;';
            if ($isNearbySearch) {
                $queries = collect(['nearby:' . $nearbyKey => [
                    'bbox' => '[out:json][timeout:25];' . sprintf($wanted, '(' . $nearbyBounds . ')'),
                ]]);
            } else {
                $this->budgetSeconds = max($this->budgetSeconds, self::COUNTRY_BUDGET);
                $boxes = self::COUNTRY_BOXES[$country];
                $queries = collect($boxes)->mapWithKeys(fn (string $box, int $index) => ['country:' . $country . ':' . $index => [
                    // Precise: only courses inside the country's border (needs a server with area support)
                    'area' => '[out:json][timeout:25];area["ISO3166-1"="' . $country . '"]["boundary"="administrative"]->.searchArea;'
                        . sprintf($wanted, count($boxes) > 1 ? '(area.searchArea)(' . $box . ')' : '(area.searchArea)'),
                    // Fallback: the country's rectangle, which works everywhere but may include courses just over a border
                    'bbox' => '[out:json][timeout:25];' . sprintf($wanted, '(' . $box . ')'),
                ]]);
            }

            $elements = collect();

            // Country course lists are cached (and warmed daily), so a nearby search inside a covered
            // country can be answered straight from them without asking OpenStreetMap at all.
            if ($isNearbySearch && !$this->refresh) {
                $known = $this->cachedCountryElements($countries);
                $coversVisitor = $known->contains(function (array $element) use ($latitude, $longitude) {
                    $lat = $element['lat'] ?? $element['center']['lat'] ?? null;
                    $lon = $element['lon'] ?? $element['center']['lon'] ?? null;

                    return $lat !== null && CuratedCourses::distanceKm((float) $latitude, (float) $longitude, $lat, $lon) <= 150;
                });
                if ($coversVisitor) {
                    $elements = $known;
                    $successfulQuery = true;
                    $queries = collect();
                }
            }

            foreach ($queries as $cacheKey => $query) {
                $result = $this->cachedOverpass($cacheKey, $query, $isNearbySearch ? self::NEARBY_TTL : self::COUNTRY_TTL);
                if ($result !== null) {
                    $elements = $elements->merge($result);
                    $successfulQuery = true;
                }
            }

            // OpenStreetMap busy and nothing cached yet: show the hand-curated courses rather than nothing
            $usedFallback = false;
            if (!$successfulQuery) {
                $elements = $this->curatedElements($isNearbySearch ? null : $country);
                $usedFallback = true;
            }

            if ($elements->isEmpty() && $usedFallback) {
                return response()->json([
                    'courses' => [],
                    'total' => 0,
                    'source' => 'OpenStreetMap',
                    'message' => 'OpenStreetMap is very busy right now, so these courses could not be loaded yet. Please try again in a few minutes.',
                ], 503);
            }

            $courses = app(CourseNamer::class)->prepare($elements)
                ->map(function (array $element) use ($country, $isNearbySearch) {
                    $tags = $element['tags'] ?? [];
                    $coordinates = isset($element['lat'], $element['lon'])
                        ? [$element['lat'], $element['lon']]
                        : [$element['center']['lat'] ?? null, $element['center']['lon'] ?? null];

                    return [
                        'id' => $element['id'] ?? null,
                        'osm_type' => $element['type'] ?? 'node',
                        'name' => $tags['name'] ?? CourseNamer::FALLBACK_NAME,
                        'name_source' => $element['name_source'] ?? null,
                        'lat' => $coordinates[0],
                        'lon' => $coordinates[1],
                        'locality' => $tags['addr:city'] ?? $tags['addr:town'] ?? $element['derived_locality'] ?? null,
                        'address' => collect([
                            trim(($tags['addr:housenumber'] ?? '') . ' ' . ($tags['addr:street'] ?? '')),
                            $tags['addr:postcode'] ?? null,
                            $tags['addr:city'] ?? $tags['addr:town'] ?? null,
                        ])->filter()->implode(', '),
                        'website' => $tags['website'] ?? $tags['contact:website'] ?? null,
                        'operator' => $tags['operator'] ?? $tags['brand'] ?? null,
                        'phone' => $tags['phone'] ?? $tags['contact:phone'] ?? null,
                        'opening_hours' => $tags['opening_hours'] ?? null,
                        'access' => $tags['access'] ?? null,
                        'fee' => $tags['fee'] ?? null,
                        'surface' => $tags['surface'] ?? null,
                        'wheelchair' => $tags['wheelchair'] ?? null,
                        'holes' => $tags['disc_golf:holes'] ?? null,
                        'par' => $tags['disc_golf:par'] ?? null,
                        'country_code' => $isNearbySearch ? ($tags['addr:country'] ?? 'Nearby') : $country,
                        'osm_url' => isset($element['type'], $element['id']) && $element['type'] !== 'curated'
                            ? 'https://www.openstreetmap.org/' . $element['type'] . '/' . $element['id']
                            : null,
                    ];
                })
                ->filter(fn (array $course) => $course['lat'] !== null && $course['lon'] !== null)
                ->map(function (array $course) use ($isNearbySearch, $latitude, $longitude) {
                    if ($isNearbySearch) {
                        $latitudeDifference = deg2rad($course['lat'] - $latitude);
                        $longitudeDifference = deg2rad($course['lon'] - $longitude);
                        $a = sin($latitudeDifference / 2) ** 2
                            + cos(deg2rad($latitude)) * cos(deg2rad($course['lat']))
                            * sin($longitudeDifference / 2) ** 2;
                        $course['distance_km'] = round(6371 * 2 * asin(min(1, sqrt($a))), 1);
                    }

                    return $course;
                })
                ->filter(function (array $course) use ($isNearbySearch, $latitude, $longitude) {
                    if (!$isNearbySearch) {
                        return true;
                    }

                    $earthRadius = 6371000;
                    $latitudeDifference = deg2rad($course['lat'] - $latitude);
                    $longitudeDifference = deg2rad($course['lon'] - $longitude);
                    $a = sin($latitudeDifference / 2) ** 2
                        + cos(deg2rad($latitude)) * cos(deg2rad($course['lat']))
                        * sin($longitudeDifference / 2) ** 2;

                    return $earthRadius * 2 * asin(min(1, sqrt($a))) <= 150000;
                })
                ->unique(fn (array $course) => $course['id'])
                ->map(function (array $course) {
                    $curated = CuratedCourses::findNear((float) $course['lat'], (float) $course['lon']);
                    if ($curated) {
                        $primaryLayout = $curated['layouts'][0];
                        $course['holes'] = $primaryLayout['holes_count'];
                        $course['par'] = $primaryLayout['par'];
                        $course['curated'] = true;
                        $course['layouts'] = CuratedCourses::layoutsForJson($curated);
                    }

                    return $course;
                })
                ->sortBy(fn (array $course) => $course['distance_km'] ?? PHP_INT_MAX)
                ->values();

            return response()->json([
                'courses' => $courses,
                'total' => $courses->count(),
                'source' => $usedFallback ? 'Curated' : 'OpenStreetMap',
                'message' => $usedFallback ? 'OpenStreetMap is busy, so only hand-curated courses are shown for now.' : null,
                'radius_km' => $isNearbySearch ? 150 : null,
            ]);
        } catch (RequestException|ConnectionException $exception) {
            return response()->json([
                'message' => 'OpenStreetMap course data is temporarily unavailable.',
            ], 502);
        }
    }

    public function holes(Request $request)
    {
        $latitude = $request->query('lat');
        $longitude = $request->query('lon');

        if (!is_numeric($latitude) || !is_numeric($longitude)
            || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return response()->json([
                'message' => 'A valid lat/lon is required.',
            ], 422);
        }

        $curated = CuratedCourses::findNear((float) $latitude, (float) $longitude);
        if ($curated) {
            return response()->json([
                'layouts' => CuratedCourses::layoutsForJson($curated),
                'source' => $curated['source'],
            ]);
        }

        $query = '[out:json][timeout:25];'
            . '(way["disc_golf"="hole"](around:1200,' . $latitude . ',' . $longitude . ');'
            . 'node["disc_golf"="hole"](around:1200,' . $latitude . ',' . $longitude . '););'
            . 'out tags center;';

        $elements = $this->cachedOverpass(sprintf('holes:%.4f,%.4f', $latitude, $longitude), ['bbox' => $query], self::HOLES_TTL);

        if ($elements === null) {
            return response()->json([
                'message' => 'OpenStreetMap hole data is temporarily unavailable.',
            ], 502);
        }

        $holes = collect($elements)
            ->map(function (array $element) {
                $tags = $element['tags'] ?? [];

                return [
                    'id' => $element['id'] ?? null,
                    'number' => $tags['ref'] ?? null,
                    'par' => $tags['disc_golf:par'] ?? $tags['par'] ?? null,
                    'length_m' => $tags['disc_golf:length'] ?? $tags['length'] ?? null,
                    'name' => $tags['name'] ?? null,
                ];
            })
            ->unique(fn (array $hole) => $hole['id'])
            ->sortBy(fn (array $hole) => is_numeric($hole['number']) ? (int) $hole['number'] : PHP_INT_MAX)
            ->values();

        $parredHoles = $holes->filter(fn (array $hole) => is_numeric($hole['par']));

        return response()->json([
            'holes' => $holes,
            'total' => $holes->count(),
            'total_par' => $parredHoles->isNotEmpty() ? $parredHoles->sum(fn (array $hole) => (int) $hole['par']) : null,
            'source' => 'OpenStreetMap',
        ]);
    }

    /**
     * Returns cached Overpass results, fetching them when missing. A long-lived copy
     * is kept so a later OpenStreetMap outage serves the last good data, not an error.
     */
    private function cachedOverpass(string $key, array $queries, int $ttl): ?array
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

        $elements = $this->runOverpassQuery($queries);
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

    /**
     * Runs a query against the public Overpass servers within the time budget.
     * $queries holds an 'area' (precise) and/or 'bbox' (works everywhere) version;
     * each server gets the best version it supports.
     */
    private function runOverpassQuery(array $queries): ?array
    {
        $deadline = microtime(true) + $this->budgetSeconds;

        foreach (self::OVERPASS_ENDPOINTS as $endpoint => $supportsArea) {
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

    /** Every country course list currently in the cache, merged. */
    private function cachedCountryElements(array $countries)
    {
        return collect($countries)
            ->flatMap(fn (string $country) => collect(array_keys(self::COUNTRY_BOXES[$country] ?? []))
                ->map(fn (int $chunk) => Cache::get('overpass:country:' . $country . ':' . $chunk) ?? Cache::get('overpass:country:' . $country . ':' . $chunk . ':stale'))
                ->filter()
                ->flatten(1))
            ->values();
    }

    /**
     * Hand-curated courses shaped like Overpass elements, used when OpenStreetMap
     * cannot answer. Limited to one country, or all of them for a nearby search.
     */
    private function curatedElements(?string $country)
    {
        return collect(CuratedCourses::all())
            ->filter(fn (array $course) => $country === null || ($course['country_code'] ?? null) === $country)
            ->values()
            ->map(fn (array $course, int $index) => [
                'type' => 'curated',
                'id' => 'curated-' . $index,
                'lat' => $course['lat'],
                'lon' => $course['lon'],
                'tags' => [
                    'name' => $course['name'],
                    'addr:city' => $course['locality'] ?? null,
                    'addr:street' => $course['address'] ?? null,
                ],
            ]);
    }
}
