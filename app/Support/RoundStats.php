<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * UDisc-style stats for one player's round, from shot-by-shot results.
 * Definitions match the scorecard's Stats tab (partials/scorekeeper), so the
 * profile and the scorecard always agree.
 *
 * Percent stats are kept as made/attempts so they can be pooled across rounds
 * (18 of 20 C1 putts over a season = 90 %), instead of averaging percentages.
 */
class RoundStats
{
    /** Stat definitions: key => [label, kind, higher is better] */
    public const STATS = [
        'fairway' => ['Fairway hits', 'percent', true],
        'c1r' => ['C1 in regulation', 'percent', true],
        'c2r' => ['C2 in regulation', 'percent', true],
        'c1_putting' => ['C1 putting', 'percent', true],
        'c2_putting' => ['C2 putting', 'percent', true],
        'scramble' => ['Scramble', 'percent', true],
        'score' => ['Score', 'relative', false],
        'rating' => ['Round rating', 'number', true],
        'birdies' => ['Birdies', 'count', true],
        'ob' => ['OB throws', 'count', false],
        'throwin' => ['Longest throw-in', 'metres', true],
    ];

    private const ON_FAIRWAY = ['fairway', 'circle_2', 'circle_1', 'in_basket'];

    /**
     * @param  Collection  $holes  holes with ->par and their shots for this player (ordered by shot_number)
     * @return array<string, array{made: int, attempts: int}|int>
     */
    public static function forRound(Collection $holes, int $userId): array
    {
        $stats = [
            'fairway' => ['made' => 0, 'attempts' => 0],
            'c1r' => ['made' => 0, 'attempts' => 0],
            'c2r' => ['made' => 0, 'attempts' => 0],
            'c1_putting' => ['made' => 0, 'attempts' => 0],
            'c2_putting' => ['made' => 0, 'attempts' => 0],
            'scramble' => ['made' => 0, 'attempts' => 0],
            'birdies' => 0,
            'ob' => 0,
            'score' => 0,
            'throwin' => null,
        ];

        foreach ($holes as $hole) {
            $shots = $hole->shots->where('user_id', $userId)->sortBy('shot_number')->values();
            if ($shots->isEmpty() || $shots->last()->result !== 'in_basket') {
                continue;
            }

            $strokes = (int) $shots->sum('strokes');
            $relative = $strokes - $hole->par;
            $stats['score'] += $relative;
            if ($relative <= -1) {
                $stats['birdies']++;
            }

            $stats['fairway']['attempts']++;
            $stats['c1r']['attempts']++;
            $stats['c2r']['attempts']++;
            if (in_array($shots[0]->result, self::ON_FAIRWAY, true)) {
                $stats['fairway']['made']++;
            }

            // "In regulation" = in the circle with a putt left for birdie
            $running = 0;
            $hitC1 = $hitC2 = $troubled = false;
            foreach ($shots as $i => $shot) {
                $running += $shot->strokes;
                $inRegulation = $running <= $hole->par - 2;
                // An OB throw leaves you wherever play resumes (C1, drop zone, re-tee…), penalty included
                $position = self::position($shot);
                if ($inRegulation && in_array($position, ['circle_1', 'in_basket'], true)) {
                    $hitC1 = true;
                }
                if ($inRegulation && in_array($position, ['circle_2', 'circle_1', 'in_basket'], true)) {
                    $hitC2 = true;
                }
                if ($shot->result === 'in_basket' && $shot->distance_m) {
                    $stats['throwin'] = max($stats['throwin'] ?? 0, $shot->distance_m);
                }
                if ($shot->result === 'out_of_bounds') {
                    $stats['ob']++;
                }
                if (in_array($shot->result, ['out_of_bounds', 'off_fairway'], true)) {
                    $troubled = true;
                }

                $lie = $i > 0 ? self::position($shots[$i - 1]) : null;
                if ($lie === 'circle_1' || $lie === 'circle_2') {
                    $key = $lie === 'circle_1' ? 'c1_putting' : 'c2_putting';
                    $stats[$key]['attempts']++;
                    if ($shot->result === 'in_basket') {
                        $stats[$key]['made']++;
                    }
                }
            }
            $stats['c1r']['made'] += $hitC1 ? 1 : 0;
            $stats['c2r']['made'] += $hitC2 ? 1 : 0;
            if ($troubled) {
                $stats['scramble']['attempts']++;
                if ($relative <= 0) {
                    $stats['scramble']['made']++;
                }
            }
        }

        return $stats;
    }

    /** Where the disc ended up after a throw; for OB, where play resumes. */
    private static function position($shot): ?string
    {
        return $shot->result === 'out_of_bounds' ? $shot->ob_lie : $shot->result;
    }

    /**
     * Turns a list of rounds into the three profile views. Works for tournament and
     * practice rounds alike: each round brings its own title and link.
     *
     * @param  Collection  $rounds  newest first; each ['stats' => forRound(), 'rating' => ?int, 'title' => string, 'url' => ?string, 'played_at' => Carbon]
     * @param  string[]  $exclude  stat keys that don't apply (e.g. 'rating' for unrated practice)
     */
    public static function summarise(Collection $rounds, array $exclude = []): array
    {
        $stats = array_diff_key(self::STATS, array_flip($exclude));

        return [
            'keys' => array_keys($stats),
            'all' => self::averages($rounds, $stats),
            'last5' => self::averages($rounds->take(5), $stats),
            'best' => self::best($rounds, $stats),
        ];
    }

    private static function averages(Collection $rounds, array $statList): array
    {
        $count = $rounds->count();
        $result = ['rounds' => $count, 'stats' => []];

        foreach ($statList as $key => [$label, $kind]) {
            if ($kind === 'percent') {
                $made = $rounds->sum(fn ($r) => $r['stats'][$key]['made']);
                $attempts = $rounds->sum(fn ($r) => $r['stats'][$key]['attempts']);
                $result['stats'][$key] = [
                    'value' => $attempts ? round($made / $attempts * 100) : null,
                    'detail' => "{$made}/{$attempts}",
                ];
            } elseif ($kind === 'metres') {
                $result['stats'][$key] = [
                    'value' => $rounds->max(fn ($r) => $r['stats'][$key]),
                    'detail' => 'longest',
                ];
            } else {
                $values = $rounds->map(fn ($r) => $key === 'rating' ? $r['rating'] : $r['stats'][$key])->filter(fn ($v) => $v !== null);
                $result['stats'][$key] = [
                    'value' => $values->isNotEmpty() ? round($values->avg(), $kind === 'number' ? 0 : 1) : null,
                    'detail' => $kind === 'number' ? 'average' : 'per round',
                ];
            }
        }

        return $result;
    }

    /** Each stat's single best round, newest round wins ties. */
    private static function best(Collection $rounds, array $statList): array
    {
        $result = ['rounds' => $rounds->count(), 'stats' => []];

        foreach ($statList as $key => [$label, $kind, $higherIsBetter]) {
            $candidates = $rounds
                ->map(function ($round) use ($key, $kind) {
                    if ($kind === 'percent') {
                        $stat = $round['stats'][$key];
                        if (!$stat['attempts']) {
                            return null;
                        }

                        return ['value' => round($stat['made'] / $stat['attempts'] * 100), 'detail' => "{$stat['made']}/{$stat['attempts']}", 'round' => $round];
                    }

                    $value = $key === 'rating' ? $round['rating'] : $round['stats'][$key];

                    return $value === null ? null : ['value' => $value, 'detail' => null, 'round' => $round];
                })
                ->filter()
                ->values();

            // Rounds come newest first, so a stable sort keeps the newest of equal values on top
            $top = $candidates->sortBy(fn ($c) => $higherIsBetter ? -$c['value'] : $c['value'])->first();

            $result['stats'][$key] = $top ? [
                'value' => $top['value'],
                'detail' => $top['detail'],
                'url' => $top['round']['url'],
                'title' => $top['round']['title'],
                'date' => $top['round']['played_at']->format('M j, Y'),
            ] : ['value' => null, 'detail' => null];
        }

        return $result;
    }
}
