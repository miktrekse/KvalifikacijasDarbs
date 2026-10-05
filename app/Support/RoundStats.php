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
                if ($inRegulation && in_array($shot->result, ['circle_1', 'in_basket'], true)) {
                    $hitC1 = true;
                }
                if ($inRegulation && in_array($shot->result, ['circle_2', 'circle_1', 'in_basket'], true)) {
                    $hitC2 = true;
                }
                if ($shot->result === 'out_of_bounds') {
                    $stats['ob']++;
                }
                if (in_array($shot->result, ['out_of_bounds', 'off_fairway'], true)) {
                    $troubled = true;
                }

                $lie = $i > 0 ? $shots[$i - 1]->result : null;
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

    /**
     * Turns a list of rounds into the three profile views.
     *
     * @param  Collection  $rounds  newest first; each ['stats' => forRound(), 'rating' => int, 'competition' => Competition, 'played_at' => Carbon]
     */
    public static function summarise(Collection $rounds): array
    {
        return [
            'all' => self::averages($rounds),
            'last5' => self::averages($rounds->take(5)),
            'best' => self::best($rounds),
        ];
    }

    private static function averages(Collection $rounds): array
    {
        $count = $rounds->count();
        $result = ['rounds' => $count, 'stats' => []];

        foreach (self::STATS as $key => [$label, $kind]) {
            if ($kind === 'percent') {
                $made = $rounds->sum(fn ($r) => $r['stats'][$key]['made']);
                $attempts = $rounds->sum(fn ($r) => $r['stats'][$key]['attempts']);
                $result['stats'][$key] = [
                    'value' => $attempts ? round($made / $attempts * 100) : null,
                    'detail' => "{$made}/{$attempts}",
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
    private static function best(Collection $rounds): array
    {
        $result = ['rounds' => $rounds->count(), 'stats' => []];

        foreach (self::STATS as $key => [$label, $kind, $higherIsBetter]) {
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
                'competition_id' => $top['round']['competition']?->id,
                'competition' => $top['round']['competition']?->name ?? 'Deleted tournament',
                'date' => $top['round']['played_at']->format('M j, Y'),
            ] : ['value' => null, 'detail' => null];
        }

        return $result;
    }
}
