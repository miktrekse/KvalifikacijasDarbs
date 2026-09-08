<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CourseController extends Controller
{
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
        $countries = [
            'GB', 'US', 'CA', 'AU', 'DE', 'SE', 'FI', 'NL', 'NZ',
            'NO', 'DK', 'AT', 'CH', 'FR', 'ES', 'IT', 'IE', 'LV',
        ];

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
            if ($isNearbySearch) {
                $latitudeDelta = 150 / 111.32;
                $longitudeDelta = 150 / (111.32 * max(cos(deg2rad((float) $latitude)), 0.1));
                $nearbyBounds = ((float) $latitude - $latitudeDelta) . ',' . ((float) $longitude - $longitudeDelta) . ','
                    . ((float) $latitude + $latitudeDelta) . ',' . ((float) $longitude + $longitudeDelta);
            }

            $chunks = $isNearbySearch ? [null] : [
                'US' => [
                    '24.3,-125,37,-96', '24.3,-96,37,-66.9',
                    '37,-125,49.4,-96', '37,-96,49.4,-66.9',
                ],
                'CA' => [
                    '41.7,-141,55,-100', '41.7,-100,55,-52.6',
                    '55,-141,83.2,-100', '55,-100,83.2,-52.6',
                ],
                'AU' => [
                    '-43.7,113,-25,133', '-43.7,133,-25,153.7',
                    '-25,113,-10.7,133', '-25,133,-10.7,153.7',
                ],
            ][$country] ?? [null];

            $elements = collect();
            $queries = collect($chunks)->map(function (?string $chunk) use ($isNearbySearch, $nearbyBounds, $country) {
                if ($isNearbySearch) {
                    return '[out:json][timeout:35];(nwr["leisure"="disc_golf_course"](' . $nearbyBounds . ');nwr["sport"="disc_golf"](' . $nearbyBounds . '););out center;';
                }

                $area = $chunk ? '(area.searchArea)(' . $chunk . ')' : '(area.searchArea)';
                return '[out:json][timeout:35];area["ISO3166-1"="' . $country . '"]["boundary"="administrative"]->.searchArea;(nwr["leisure"="disc_golf_course"]' . $area . ';nwr["sport"="disc_golf"]' . $area . ';);out center;';
            });

            if (!$isNearbySearch && $queries->count() > 1) {
                $responses = Http::pool(function (Pool $pool) use ($queries) {
                    return $queries->map(fn (string $query) => $pool
                        ->withHeaders(['User-Agent' => 'DiscStats/1.0'])
                        ->acceptJson()
                        ->timeout(40)
                        ->get('https://overpass.kumi.systems/api/interpreter', ['data' => $query]))->all();
                });

                foreach ($responses as $response) {
                    if ($response->successful()) {
                        $elements = $elements->merge($response->json('elements', []));
                        $successfulQuery = true;
                    }
                }
            } else {
                foreach ($queries as $query) {
                    $endpoints = ['https://overpass.kumi.systems/api/interpreter', 'https://overpass-api.de/api/interpreter', 'https://overpass.private.coffee/api/interpreter'];
                    foreach ($endpoints as $endpoint) {
                        try {
                            $response = Http::withHeaders(['User-Agent' => 'DiscStats/1.0'])
                                ->acceptJson()
                                ->timeout(35)
                                ->get($endpoint, ['data' => $query])
                                ->throw();
                            $elements = $elements->merge($response->json('elements', []));
                            $successfulQuery = true;
                            break;
                        } catch (RequestException|ConnectionException $exception) {
                            continue;
                        }
                    }
                }
            }

            if (!$successfulQuery) {
                return response()->json([
                    'courses' => [],
                    'total' => 0,
                    'source' => 'OpenStreetMap',
                    'message' => 'OpenStreetMap is temporarily unavailable. Please try Near me again.',
                ], 502);
            }

            $courses = $elements
                ->map(function (array $element) use ($country, $isNearbySearch) {
                    $tags = $element['tags'] ?? [];
                    $coordinates = isset($element['lat'], $element['lon'])
                        ? [$element['lat'], $element['lon']]
                        : [$element['center']['lat'] ?? null, $element['center']['lon'] ?? null];

                    return [
                        'id' => $element['id'] ?? null,
                        'osm_type' => $element['type'] ?? 'node',
                        'name' => $tags['name'] ?? 'Unnamed disc golf course',
                        'lat' => $coordinates[0],
                        'lon' => $coordinates[1],
                        'locality' => $tags['addr:city'] ?? $tags['addr:town'] ?? null,
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
                        'country_code' => $isNearbySearch ? ($tags['addr:country'] ?? 'Nearby') : $country,
                        'osm_url' => isset($element['type'], $element['id'])
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
                ->sortBy(fn (array $course) => $course['distance_km'] ?? PHP_INT_MAX)
                ->values();

            return response()->json([
                'courses' => $courses,
                'total' => $courses->count(),
                'source' => 'OpenStreetMap',
                'radius_km' => $isNearbySearch ? 150 : null,
            ]);
        } catch (RequestException|ConnectionException $exception) {
            return response()->json([
                'message' => 'OpenStreetMap course data is temporarily unavailable.',
            ], 502);
        }
    }
}