<?php

namespace App\Support;

use App\Models\Competition;
use App\Models\CompetitionRegistration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Draws the cards (groups) for a competition and prepares its holes.
 *
 * Groups always have at least MIN_GROUP_SIZE players when there are enough
 * registrations: N players are split into floor(N / 4) groups whose sizes
 * differ by at most one (9 players -> 5 + 4, 7 players -> 7). Fewer than four
 * players simply form a single group. Groups get shotgun starting holes spread
 * evenly around the course.
 */
class CompetitionGrouping
{
    public const MIN_GROUP_SIZE = 4;

    /** Words that say nothing about which course it is ("disku golfa parks" etc). */
    private const NAME_NOISE = ['disku', 'disc', 'golfa', 'golfs', 'golf', 'laukums', 'parks', 'park', 'course', 'dgc', 'the'];

    /**
     * @return int[] group sizes, largest first
     */
    public static function groupSizes(int $players): array
    {
        if ($players <= 0) {
            return [];
        }

        $groups = max(1, intdiv($players, self::MIN_GROUP_SIZE));
        $base = intdiv($players, $groups);
        $extra = $players % $groups;

        return array_map(fn (int $i) => $base + ($i < $extra ? 1 : 0), range(0, $groups - 1));
    }

    public static function startingHole(int $groupIndex, int $groupCount, int $holes): int
    {
        return 1 + intdiv($groupIndex * max(1, $holes), max(1, $groupCount));
    }

    /**
     * Assigns every registered player to a group. Safe to call repeatedly and
     * concurrently: the competition row is locked and the draw happens once.
     */
    public static function assign(Competition $competition): bool
    {
        return DB::transaction(function () use ($competition) {
            $locked = Competition::whereKey($competition->id)->lockForUpdate()->first();

            if (!$locked || $locked->groups_assigned_at !== null) {
                return false;
            }

            self::ensureHoles($locked);

            $registrations = self::orderForDraw($locked, $locked->registrations()->get());
            $sizes = self::groupSizes($registrations->count());
            $offset = 0;

            foreach ($sizes as $index => $size) {
                $group = $locked->groups()->create([
                    'number' => $index + 1,
                    'starting_hole' => self::startingHole($index, count($sizes), $locked->holes),
                ]);

                CompetitionRegistration::whereIn('id', $registrations->slice($offset, $size)->pluck('id'))
                    ->update(['competition_group_id' => $group->id]);

                $offset += $size;
            }

            $locked->forceFill(['groups_assigned_at' => now()])->save();
            $competition->setRawAttributes($locked->getAttributes(), true);

            return true;
        });
    }

    /**
     * Similar players share a card: by division (in the order the organizer
     * listed them), then by rating, highest first.
     */
    private static function orderForDraw(Competition $competition, Collection $registrations): Collection
    {
        $divisionOrder = array_flip($competition->divisionsArray);

        return $registrations->sort(function (CompetitionRegistration $a, CompetitionRegistration $b) use ($divisionOrder) {
            return [$divisionOrder[$a->division] ?? PHP_INT_MAX, -($a->rating ?? -1), $a->created_at]
                <=> [$divisionOrder[$b->division] ?? PHP_INT_MAX, -($b->rating ?? -1), $b->created_at];
        })->values();
    }

    /**
     * Creates the competition's holes, taking par and length from the curated
     * UDisc layout when the course name matches one, otherwise par 3.
     */
    public static function ensureHoles(Competition $competition): void
    {
        if ($competition->courseHoles()->exists()) {
            return;
        }

        $layoutHoles = collect(self::matchLayout($competition)['holes'] ?? [])->keyBy('number');
        $now = now();

        $competition->courseHoles()->insert(collect(range(1, max(1, $competition->holes)))
            ->map(fn (int $number) => [
                'competition_id' => $competition->id,
                'number' => $number,
                'par' => $layoutHoles[$number]['par'] ?? 3,
                'distance_m' => isset($layoutHoles[$number]['length_ft']) ? (int) round($layoutHoles[$number]['length_ft'] * 0.3048) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
    }

    private static function matchLayout(Competition $competition): ?array
    {
        $wanted = self::nameTokens((string) $competition->course_name);
        if (!$wanted) {
            return null;
        }

        foreach (CuratedCourses::all() as $course) {
            if (!array_intersect($wanted, self::nameTokens($course['name']))) {
                continue;
            }

            return collect($course['layouts'])->firstWhere('holes_count', $competition->holes);
        }

        return null;
    }

    private static function nameTokens(string $name): array
    {
        $tokens = preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii($name)), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_filter($tokens, fn (string $token) => strlen($token) > 2 && !in_array($token, self::NAME_NOISE, true)));
    }
}
