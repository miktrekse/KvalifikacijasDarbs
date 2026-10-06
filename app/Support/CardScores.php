<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Turns every scorer's entries for a competition hole into the official result
 * (PDGA Live style). Each scorer on a card keeps their own copy of a player's
 * hole; the hole counts once it is finished and every finished copy agrees on
 * the strokes. Disagreeing copies are a conflict and count for nothing until a
 * scorer changes theirs.
 */
class CardScores
{
    public const OPEN = 'open';           // nobody has finished the hole yet
    public const SINGLE = 'single';       // one scorer finished it
    public const CONFIRMED = 'confirmed'; // two or more scorers finished it with the same score
    public const CONFLICT = 'conflict';   // finished copies disagree

    /**
     * One entry per scorer for a single player on a single hole.
     *
     * @param  Collection  $shots  that player's shots on the hole, from every scorer
     * @return Collection<int, array{scorer: ?int, shots: Collection, finished: bool, strokes: int, score_only: bool}>
     */
    public static function entries(Collection $shots): Collection
    {
        return $shots->groupBy(fn ($shot) => (int) $shot->recorded_by)
            ->map(function (Collection $own) {
                $own = $own->sortBy('shot_number')->values();

                return [
                    'scorer' => $own->first()->recorded_by,
                    'shots' => $own,
                    'finished' => $own->last()->result === 'in_basket',
                    'strokes' => (int) $own->sum('strokes'),
                    'score_only' => (bool) $own->first()->score_only,
                ];
            })
            ->values();
    }

    /**
     * @return array{status: string, strokes: ?int, shots: Collection}
     *   shots: the copy that counts (a shot-by-shot one when there is one, for stats), empty unless settled
     */
    public static function resolve(Collection $shots): array
    {
        $finished = self::entries($shots)->where('finished', true);

        if ($finished->isEmpty()) {
            return ['status' => self::OPEN, 'strokes' => null, 'shots' => collect()];
        }
        if ($finished->pluck('strokes')->unique()->count() > 1) {
            return ['status' => self::CONFLICT, 'strokes' => null, 'shots' => collect()];
        }

        $official = $finished->sortBy(fn ($entry) => $entry['score_only'])->first();

        return [
            'status' => $finished->count() > 1 ? self::CONFIRMED : self::SINGLE,
            'strokes' => $official['strokes'],
            'shots' => $official['shots'],
        ];
    }

    /**
     * Keeps only the shots that count from a hole's shots (all players, all scorers),
     * so code that sums a player's shots sees one settled copy per finished hole.
     */
    public static function officialShots(Collection $holeShots): Collection
    {
        return $holeShots->groupBy('user_id')
            ->flatMap(fn (Collection $shots) => self::resolve($shots)['shots'])
            ->values();
    }
}
