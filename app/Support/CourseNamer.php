<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Cleans up OpenStreetMap course pins and gives names to the ones mappers left unnamed.
 *
 * Many "courses" returned by the disc golf queries are really single holes, tees, baskets
 * or shops, and a single course is often mapped twice (an area plus a pin). Those are
 * dropped first. Courses still without a name are then named, in order of trust, from:
 *
 *   1. other OSM name tags (official_name, name:en, loc_name, alt_name, ...)
 *   2. a hand-curated course at the same spot
 *   3. the nearest Disc Golf Metrix course (their course list is public, per country)
 *   4. the named park, school or area the course lies in (Overpass is_in)
 *   5. the town or municipality it lies in (Overpass, or Nominatim as a fallback)
 *
 * Steps 3-5 call outside services, so they only run in the daily courses:warm job and
 * their answers are cached per OSM element; map requests just read that cache.
 */
class CourseNamer
{
    public const FALLBACK_NAME = 'Unnamed disc golf course';

    /** disc_golf=* values that mark part of a course rather than the course itself */
    private const COURSE_PARTS = ['hole', 'tee', 'basket', 'target', 'putting', 'fairway', 'out_of_bounds',
        'island', 'practice_basket', 'pin', 'green', 'dropzone', 'mando', 'start', 'pad'];

    /** Names like "Bahn 2", "Hole 7", "Väylä 12" or just "5" belong to a single hole */
    private const HOLE_NAME = '/^\s*(hole|bahn|korb|basket|tee|fairway|väylä|vayla|korg|hål|hal|bane|kurv|piste|trou|hoyo|buca|grozs|groz|bedre|kori|cesta|loch|#)?\s*#?\s*\d{1,2}[a-z]?\s*$/iu';

    /** Name already says it is a disc golf course (in any of the supported languages), so no suffix is added */
    private const DISC_GOLF_WORDS = '/disc\s*-?\s*golf|discgolf|frisbee\s*-?\s*golf|frisbeegolf|disku\s*golf|diskgolf|frolf|\bdgp\b|\bdgc\b|\bparcours\b|\bbane\b|\bbanan?\b|\bbaan\b|\brata\b|\blaukums\b/iu';

    /** Metrix entries named after one event or season rather than the course */
    private const EVENT_WORDS = '/\b(open|cup|round|runde|tour|touren|amatouren|meisterschaft|championships?|mästerskap|mesterskap|sm|nm|dm|fm|liga|league|series|serie|doubles|weekly|weeklies|tournament|turnering|kisa|kilpailu|challenge|camp|test|temp|temporary|winter|talvi|vinter|season|saison|säsong|kausi|layout|event|bst|pdga)\b/iu';

    private const METRIX_TTL = 7 * 86400;
    private const NAME_TTL = 120 * 86400;
    private const PLACE_TTL = 180 * 86400;
    private const PLACE_MISS_TTL = 30 * 86400;

    /** An unnamed pin this close to a course is a second pin for it; a pin with the same name, up to a kilometre away */
    private const DUPLICATE_KM = 0.25;
    private const SAME_NAME_KM = 1.0;
    private const METRIX_MATCH_KM = 0.5;

    /** Points per Overpass place lookup, and the most Nominatim lookups one warm run may make */
    private const PLACE_BATCH = 10;
    private const NOMINATIM_LIMIT = 150;

    /**
     * Drops course parts and duplicate pins, and fills in a name for every remaining
     * element: tags['name'] is always set, plus 'name_source' when the name was derived
     * and 'derived_locality' when a town was found for it.
     */
    public function prepare(Collection $elements): Collection
    {
        $courses = $this->withoutDuplicates($elements
            ->map(fn (array $element) => $this->withCoordinates($element))
            ->filter(fn (array $element) => $element['lat'] !== null && $this->isCourse($element['tags'] ?? [])));

        $unnamed = $courses->filter(fn (array $element) => $this->tagName($element['tags'] ?? []) === null);
        $stored = $unnamed->isEmpty() ? [] : Cache::many($unnamed->map(fn (array $element) => $this->nameKey($element))->values()->all());

        $named = $courses->map(function (array $element) use ($stored) {
            $tags = $element['tags'] ?? [];
            if (!empty($tags['name'])) {
                return $element;
            }

            $resolved = $this->tagName($tags) !== null
                ? ['name' => $this->tagName($tags), 'source' => 'osm_tags']
                : $this->curatedName($element) ?? ($stored[$this->nameKey($element)] ?? null);

            $element['tags']['name'] = $resolved['name'] ?? self::FALLBACK_NAME;
            $element['name_source'] = isset($resolved['name']) ? $resolved['source'] : null;
            $element['derived_locality'] = $resolved['locality'] ?? null;

            return $element;
        });

        // A pin inside a named course's mapped area is a second pin for that course
        $osmNames = $named->whereNull('name_source')->map(fn (array $element) => $this->normalise($element['tags']['name']))->flip();
        $named = $named->reject(fn (array $element) => ($element['name_source'] ?? null) === 'osm_area'
            && $osmNames->has($this->normalise($element['tags']['name'])));

        return $this->numberRepeatedNames($named)->values();
    }

    /**
     * Works out names for the unnamed courses in a country's pins and caches them.
     * $overpass runs one Overpass query and returns its elements, or null when busy.
     * Returns how many courses now have a derived name.
     */
    public function resolve(string $country, Collection $elements, Closure $overpass): int
    {
        $metrix = $this->metrixCourses($country);

        $unnamed = $elements
            ->map(fn (array $element) => $this->withCoordinates($element))
            ->filter(fn (array $element) => $element['lat'] !== null && $this->isCourse($element['tags'] ?? []));
        $unnamed = $this->withoutDuplicates($unnamed)
            ->filter(fn (array $element) => empty($element['tags']['name']) && $this->tagName($element['tags'] ?? []) === null
                && $this->curatedName($element) === null)
            ->values();

        // Metrix names are the best guess, so only the rest need a park/town lookup
        $matches = $unnamed->mapWithKeys(fn (array $element) => [$this->nameKey($element) => $this->metrixMatch($element, $metrix)]);
        $places = $this->places($unnamed->filter(fn (array $element) => $matches[$this->nameKey($element)] === null), $overpass);

        $resolved = 0;
        foreach ($unnamed as $element) {
            $place = $places[$this->placeKey($element)] ?? [];
            $match = $matches[$this->nameKey($element)];

            $name = match (true) {
                $match !== null => ['name' => $this->courseNameFor($match['name']), 'source' => 'metrix'],
                !empty($place['course']) => ['name' => $place['course'], 'source' => 'osm_area'],
                !empty($place['park']) => ['name' => $this->courseNameFor($place['park']), 'source' => 'park'],
                !empty($element['tags']['operator']) && !$this->looksLikeCompany($element['tags']['operator'])
                    => ['name' => $this->courseNameFor($element['tags']['operator']), 'source' => 'operator'],
                !empty($place['locality']) => ['name' => $this->courseNameFor($place['locality']), 'source' => 'locality'],
                default => null,
            };

            if ($name === null) {
                continue;
            }

            Cache::put($this->nameKey($element), $name + ['locality' => $place['locality'] ?? $match['city'] ?? null], self::NAME_TTL);
            $resolved++;
        }

        return $resolved;
    }

    /**
     * Whether the tags describe a whole course rather than one hole or a disc shop. Info
     * boards are kept, as some courses are only mapped by their sign, but they give way
     * to a proper course pin nearby.
     */
    public function isCourse(array $tags): bool
    {
        if (isset($tags['disc_golf']) && in_array($tags['disc_golf'], self::COURSE_PARTS, true)) {
            return false;
        }

        if (isset($tags['shop']) || isset($tags['box']) || isset($tags['craft']) || isset($tags['office'])) {
            return false;
        }

        if (isset($tags['name']) && preg_match(self::HOLE_NAME, $tags['name'])) {
            return false;
        }

        // A numbered pitch or track without a name is one hole of a bigger course
        if (in_array($tags['leisure'] ?? null, ['pitch', 'track'], true) && isset($tags['ref']) && empty($tags['name'])) {
            return false;
        }

        return true;
    }

    private function withCoordinates(array $element): array
    {
        $element['lat'] ??= $element['center']['lat'] ?? null;
        $element['lon'] ??= $element['center']['lon'] ?? null;

        return $element;
    }

    /**
     * Keeps one pin per course: an unnamed pin near any kept course is dropped, and so is a
     * second pin with the same name. Named courses and mapped areas are kept first.
     */
    private function withoutDuplicates(Collection $elements): Collection
    {
        $ordered = $elements->sortBy(fn (array $element) => [
            empty($element['tags']['name']) ? 1 : 0,
            isset($element['tags']['information']) || ($element['tags']['tourism'] ?? null) === 'information' ? 1 : 0,
            ($element['tags']['leisure'] ?? null) === 'disc_golf_course' ? 0 : 1,
            ($element['type'] ?? 'node') === 'node' ? 1 : 0,
        ]);

        $grid = [];
        $kept = [];
        foreach ($ordered as $element) {
            $name = $this->normalise($element['tags']['name'] ?? '');
            $cellLat = (int) floor($element['lat'] / 0.01);
            $cellLon = (int) floor($element['lon'] / 0.01);

            // Grid cells are 0.01° (~1.1 km of latitude), so two cells either way covers the radius
            $duplicate = false;
            for ($dLat = -2; $dLat <= 2 && !$duplicate; $dLat++) {
                for ($dLon = -2; $dLon <= 2 && !$duplicate; $dLon++) {
                    foreach ($grid[($cellLat + $dLat) . ':' . ($cellLon + $dLon)] ?? [] as $other) {
                        $distance = CuratedCourses::distanceKm($element['lat'], $element['lon'], $other['lat'], $other['lon']);
                        if (($name === '' && $distance <= self::DUPLICATE_KM) || ($name !== '' && $name === $other['name'] && $distance <= self::SAME_NAME_KM)) {
                            $duplicate = true;
                            break;
                        }
                    }
                }
            }

            if (!$duplicate) {
                $grid[$cellLat . ':' . $cellLon][] = ['lat' => $element['lat'], 'lon' => $element['lon'], 'name' => $name];
                $kept[] = $element;
            }
        }

        return collect($kept);
    }

    /** A name from OSM tags other than name=* */
    private function tagName(array $tags): ?string
    {
        if (!empty($tags['name'])) {
            return $tags['name'];
        }

        foreach (['official_name', 'name:en', 'int_name', 'loc_name', 'short_name', 'reg_name', 'alt_name'] as $key) {
            if (!empty($tags[$key])) {
                return trim(explode(';', $tags[$key])[0]);
            }
        }

        foreach ($tags as $key => $value) {
            if (str_starts_with($key, 'name:') && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function curatedName(array $element): ?array
    {
        $curated = CuratedCourses::findNear((float) $element['lat'], (float) $element['lon']);

        return $curated ? ['name' => $curated['name'], 'source' => 'curated', 'locality' => $curated['locality'] ?? null] : null;
    }

    /** Courses that share a derived name get a number so they can be told apart */
    private function numberRepeatedNames(Collection $elements): Collection
    {
        $counts = $elements->whereNotNull('name_source')->countBy(fn (array $element) => $element['tags']['name']);
        $seen = [];

        return $elements->sortBy(fn (array $element) => ($element['type'] ?? '') . ($element['id'] ?? ''))
            ->map(function (array $element) use ($counts, &$seen) {
                $name = $element['tags']['name'];
                if (($element['name_source'] ?? null) !== null && ($counts[$name] ?? 0) > 1) {
                    $seen[$name] = ($seen[$name] ?? 0) + 1;
                    if ($seen[$name] > 1) {
                        $element['tags']['name'] = $name . ' ' . $seen[$name];
                    }
                }

                return $element;
            });
    }

    /** "Wickham Park" → "Wickham Park Disc Golf Course"; names that already say disc golf stay as they are */
    private function courseNameFor(string $place): string
    {
        $place = trim(preg_replace('/\s*\([^)]*\)/u', '', $place)) ?: trim($place);

        return preg_match(self::DISC_GOLF_WORDS, $place) ? $place : $place . ' Disc Golf Course';
    }

    private function looksLikeCompany(string $operator): bool
    {
        return (bool) preg_match('/\b(e\.\s?v\.|gmbh|ltd|inc|llc|ab|oy|as|a\/s|aps|ry|sv|tus|verein|club|klubb|klub|seura|department|dept|county|city of|council|kommune|kommun|gemeinde|stadt)\b/iu', $operator);
    }

    private function normalise(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($name)));
    }

    private function nameKey(array $element): string
    {
        return 'course-name:' . ($element['type'] ?? 'node') . ':' . ($element['id'] ?? '');
    }

    private function placeKey(array $element): string
    {
        return 'course-place:' . ($element['type'] ?? 'node') . ':' . ($element['id'] ?? '');
    }

    /**
     * The country's Disc Golf Metrix courses that have a location, each with a clean
     * course name (layouts are mapped to their parent course).
     */
    private function metrixCourses(string $country): array
    {
        $key = 'metrix:courses:' . $country;
        $courses = Cache::get($key);
        if ($courses === null) {
            try {
                $response = Http::withHeaders(['User-Agent' => 'DiscStats/1.0'])
                    ->acceptJson()
                    ->connectTimeout(5)
                    ->timeout(60)
                    ->get('https://discgolfmetrix.com/api.php', ['content' => 'courses_list', 'country_code' => $country])
                    ->throw();
                $courses = $response->json('courses');
            } catch (Throwable) {
                $courses = null;
            }

            if (is_array($courses)) {
                Cache::put($key, $courses, self::METRIX_TTL);
                Cache::put($key . ':stale', $courses, now()->addDays(90));
            } else {
                $courses = Cache::get($key . ':stale', []);
            }
        }

        $byId = collect($courses)->keyBy('ID');

        return collect($courses)
            ->filter(fn (array $course) => is_numeric($course['X'] ?? null) && is_numeric($course['Y'] ?? null))
            ->map(function (array $course) use ($byId) {
                $parent = $course['ParentID'] ? $byId->get($course['ParentID']) : null;
                $name = $this->cleanMetrixName($parent['Name'] ?? $course['Name'] ?? '');

                return [
                    'lat' => (float) $course['X'],
                    'lon' => (float) $course['Y'],
                    'name' => $name,
                    'city' => html_entity_decode(trim((string) ($course['City'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?: null,
                    'ended' => !empty($course['Enddate']) && ($parent === null || !empty($parent['Enddate'])),
                ];
            })
            ->filter(fn (array $course) => $course['name'] !== '' && !preg_match(self::EVENT_WORDS, $course['name']))
            ->values()
            ->all();
    }

    /** Drops layout and score notes such as "(18/63)", "(9 basket course)" or a trailing year */
    private function cleanMetrixName(string $name): string
    {
        $name = html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $name = trim(explode('→', $name)[0]);
        $name = preg_replace('/\s*\([^)]*\d[^)]*\)/u', '', $name);
        $name = preg_replace('/[\s,\-–]+(\d+\s*\/\s*\d+|\d+\s*(holes?|baskets?|bahnen|väylää|korgar|kurver|grozi)|(19|20)\d{2})\s*$/iu', '', $name);

        // Layout tags such as "18 Rouge", "Blue 9" or a trailing hole count
        $name = preg_replace('/\s+(\d+\s+)?(rouge|bleu|blanc|jaune|noir|vert|red|blue|white|yellow|gold|green|black|orange|pro|am|short|long)(\s+\d+)?\s*$/iu', '', $name);
        $name = preg_replace('/\s+\d{1,2}\s*$/u', '', $name);
        $name = trim(preg_replace('/\s+/u', ' ', $name), " \t\n\r\0\x0B-–,");

        // "ALLAN" → "Allan"; short all-caps names like "DGC" stay as they are
        return mb_strlen($name) > 4 && mb_strtoupper($name) === $name ? mb_convert_case(mb_strtolower($name), MB_CASE_TITLE) : $name;
    }

    /** The nearest suitable Metrix course (name and city), preferring active courses */
    private function metrixMatch(array $element, array $metrix): ?array
    {
        $best = null;
        $bestScore = PHP_FLOAT_MAX;
        foreach ($metrix as $course) {
            if (abs($course['lat'] - $element['lat']) > 0.01) {
                continue;
            }

            $distance = CuratedCourses::distanceKm($element['lat'], $element['lon'], $course['lat'], $course['lon']);
            $score = $distance + ($course['ended'] ? 0.2 : 0);
            if ($distance <= self::METRIX_MATCH_KM && $score < $bestScore) {
                $best = ['name' => $course['name'], 'city' => $course['city']];
                $bestScore = $score;
            }
        }

        return $best;
    }

    /**
     * Looks up the park/area and town each course lies in. Answers are cached for months,
     * so each course is only looked up once; places Overpass could not answer fall back to
     * a Nominatim town lookup.
     */
    private function places(Collection $elements, Closure $overpass): array
    {
        // Places only answered by Nominatim (a town, no park check) are tried on Overpass again
        $places = [];
        $missing = collect();
        foreach ($elements as $element) {
            $cached = Cache::get($this->placeKey($element));
            if ($cached !== null) {
                $places[$this->placeKey($element)] = $cached;
            }
            if ($cached === null || !array_key_exists('park', $cached)) {
                $missing->push($element);
            }
        }

        $unanswered = collect();
        foreach ($missing->chunk(self::PLACE_BATCH) as $batch) {
            $batch = $batch->values();
            $found = $this->overpassPlaces($batch, $overpass);
            if ($found === null) {
                $unanswered = $unanswered->merge($batch);
                continue;
            }

            foreach ($batch as $index => $element) {
                $place = $found[$index] ?? [];
                $places[$this->placeKey($element)] = $place;
                Cache::put($this->placeKey($element), $place, array_filter($place) ? self::PLACE_TTL : self::PLACE_MISS_TTL);
            }
        }

        $unanswered = $unanswered->reject(fn (array $element) => isset($places[$this->placeKey($element)]))->values();
        foreach ($unanswered->take(self::NOMINATIM_LIMIT) as $i => $element) {
            if ($i > 0) {
                usleep(1_100_000); // Nominatim allows one request per second
            }
            $locality = $this->nominatimLocality($element['lat'], $element['lon']);
            if ($locality !== null) {
                // Only cached briefly, so a later run can still look for a park name
                $places[$this->placeKey($element)] = ['locality' => $locality];
                Cache::put($this->placeKey($element), ['locality' => $locality], self::PLACE_MISS_TTL);
            }
        }

        return $places;
    }

    /**
     * One Overpass query for a batch of points: the named course area, park, school or
     * nature area each one lies in (or is right next to), and its town. Null when busy.
     */
    private function overpassPlaces(Collection $batch, Closure $overpass): ?array
    {
        $query = '[out:json][timeout:90];';
        foreach ($batch as $index => $element) {
            $point = $element['lat'] . ',' . $element['lon'];
            $query .= 'make marker idx=' . $index . ';out;'
                . 'is_in(' . $point . ')->.a;('
                . 'area.a[name][leisure];'
                . 'area.a[name][amenity~"^(school|university|college)$"];'
                . 'area.a[name][landuse~"^(recreation_ground|village_green|grass|forest)$"];'
                . 'area.a[name][boundary=administrative][admin_level~"^([6-9]|10)$"];'
                . ');out tags;'
                . '(way(around:150,' . $point . ')[name][leisure~"^(park|nature_reserve|recreation_ground|disc_golf_course)$"];'
                . 'relation(around:150,' . $point . ')[name][leisure~"^(park|nature_reserve|recreation_ground|disc_golf_course)$"];'
                . ');out tags;';
        }

        $elements = $overpass($query);
        if ($elements === null) {
            return null;
        }

        $found = [];
        $current = null;
        foreach ($elements as $element) {
            $tags = $element['tags'] ?? [];
            if (($element['type'] ?? null) === 'marker') {
                $current = (int) $tags['idx'];
                $found[$current] = ['course' => null, 'park' => null, 'locality' => null, 'park_rank' => 99, 'admin_level' => 0];
                continue;
            }
            if ($current === null || empty($tags['name'])) {
                continue;
            }

            $place = &$found[$current];
            if (($tags['boundary'] ?? null) === 'administrative') {
                $level = (int) ($tags['admin_level'] ?? 0);
                if ($level > $place['admin_level']) {
                    $place['locality'] = $tags['name'];
                    $place['admin_level'] = $level;
                }
            } elseif (($tags['leisure'] ?? null) === 'disc_golf_course') {
                $place['course'] ??= $this->isCourse($tags) ? $tags['name'] : null;
            } else {
                $rank = $this->placeRank($tags);
                if ($rank < min($place['park_rank'], 98)) {
                    $place['park'] = $tags['name'];
                    $place['park_rank'] = $rank;
                }
            }
            unset($place);
        }

        return collect($found)
            ->map(fn (array $place) => array_intersect_key($place, array_flip(['course', 'park', 'locality'])))
            ->all();
    }

    /** Lower is a better name source: a park beats a school, which beats a forest */
    private function placeRank(array $tags): int
    {
        $leisure = $tags['leisure'] ?? null;

        return match (true) {
            in_array($leisure, ['park', 'recreation_ground', 'common', 'golf_course', 'sports_centre', 'garden'], true) => 1,
            isset($tags['amenity']) => 2,
            in_array($leisure, ['nature_reserve'], true) || in_array($tags['landuse'] ?? null, ['recreation_ground', 'village_green', 'grass'], true) => 3,
            ($tags['landuse'] ?? null) === 'forest' => 5,
            in_array($leisure, ['pitch', 'track', 'playground', 'dog_park', 'swimming_pool', 'marina'], true) => 98,
            default => 6,
        };
    }

    private function nominatimLocality(float $lat, float $lon): ?string
    {
        try {
            $address = Http::withHeaders(['User-Agent' => 'DiscStats/1.0 (disc golf course map)'])
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(15)
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'format' => 'jsonv2', 'lat' => $lat, 'lon' => $lon, 'zoom' => 14, 'addressdetails' => 1,
                ])
                ->throw()
                ->json('address', []);
        } catch (Throwable) {
            return null;
        }

        foreach (['village', 'town', 'city', 'suburb', 'hamlet', 'municipality', 'city_district', 'county'] as $key) {
            if (!empty($address[$key])) {
                return $address[$key];
            }
        }

        return null;
    }
}
