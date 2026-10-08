<?php

namespace Tests\Unit;

use App\Support\CompetitionGrouping;
use PHPUnit\Framework\TestCase;

class CompetitionGroupingTest extends TestCase
{
    public function test_cards_have_at_least_four_players_and_differ_by_at_most_one(): void
    {
        $this->assertSame([], CompetitionGrouping::groupSizes(0));
        $this->assertSame([3], CompetitionGrouping::groupSizes(3));
        $this->assertSame([7], CompetitionGrouping::groupSizes(7));
        $this->assertSame([4, 4], CompetitionGrouping::groupSizes(8));
        $this->assertSame([5, 4], CompetitionGrouping::groupSizes(9));
        $this->assertSame([5, 5, 4], CompetitionGrouping::groupSizes(14));
    }

    public function test_every_player_gets_a_card(): void
    {
        foreach (range(1, 60) as $players) {
            $sizes = CompetitionGrouping::groupSizes($players);
            $this->assertSame($players, array_sum($sizes));
            if ($players >= CompetitionGrouping::MIN_GROUP_SIZE) {
                $this->assertGreaterThanOrEqual(CompetitionGrouping::MIN_GROUP_SIZE, min($sizes));
            }
            $this->assertLessThanOrEqual(1, max($sizes) - min($sizes));
        }
    }

    public function test_shotgun_starts_are_spread_around_the_course(): void
    {
        $this->assertSame(1, CompetitionGrouping::startingHole(0, 1, 18));
        $this->assertSame([1, 10], [CompetitionGrouping::startingHole(0, 2, 18), CompetitionGrouping::startingHole(1, 2, 18)]);
        $this->assertSame([1, 7, 13], array_map(fn ($i) => CompetitionGrouping::startingHole($i, 3, 18), [0, 1, 2]));
    }
}
