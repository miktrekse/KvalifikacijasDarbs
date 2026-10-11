<?php

namespace Tests\Feature;

use App\Http\Controllers\CourseController;
use App\Models\User;
use App\Support\Overpass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** The course map can't be used to tie up the server or flood the public OpenStreetMap servers. */
class CourseLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['*' => Http::response(['elements' => []])]);
    }

    /** Hole lookups out at sea: never a curated course, so every new spot asks OpenStreetMap. */
    private function holesAt(User $user, int $i)
    {
        return $this->actingAs($user)->getJson('/courses/holes?lat=0.' . str_pad((string) $i, 3, '0', STR_PAD_LEFT) . '&lon=-30');
    }

    public function test_each_visitor_has_a_cap_on_live_openstreetmap_lookups(): void
    {
        $user = User::factory()->create();

        foreach (range(1, Overpass::VISITOR_LOOKUPS) as $i) {
            $this->holesAt($user, $i)->assertOk();
        }
        Http::assertSentCount(Overpass::VISITOR_LOOKUPS);

        // A new spot past the cap is answered without going out to the servers
        $this->holesAt($user, 999)->assertStatus(502);
        Http::assertSentCount(Overpass::VISITOR_LOOKUPS);

        // Another player still has their own allowance
        $this->holesAt(User::factory()->create(), 1)->assertOk();
    }

    public function test_cached_answers_do_not_count_against_the_cap(): void
    {
        $user = User::factory()->create();

        foreach (range(1, Overpass::VISITOR_LOOKUPS + 5) as $i) {
            $this->holesAt($user, 1)->assertOk();
        }

        Http::assertSentCount(1);
    }

    public function test_the_shared_guest_is_limited_per_ip(): void
    {
        $guest = User::factory()->guest()->create();

        foreach (range(1, 30) as $i) {
            $this->holesAt($guest, 1)->assertOk();
        }
        $this->holesAt($guest, 1)->assertStatus(429);

        // Someone else on the guest account, from another address, isn't blocked by that
        $this->actingAs($guest)->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->getJson('/courses/holes?lat=0.001&lon=-30')->assertOk();
    }

    public function test_the_scheduled_warm_up_is_never_capped(): void
    {
        $user = User::factory()->create();
        foreach (range(1, Overpass::VISITOR_LOOKUPS) as $i) {
            $this->holesAt($user, $i);
        }
        cache()->put('overpass-live:all', Overpass::TOTAL_LOOKUPS, 600);

        app(CourseController::class)->warm('LV');

        Http::assertSentCount(Overpass::VISITOR_LOOKUPS + 1);
    }
}
