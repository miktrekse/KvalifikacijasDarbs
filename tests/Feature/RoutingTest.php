<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The app's entry points: who lands where before and after signing in. */
class RoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_sends_visitors_to_the_login_page(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_login_and_registration_pages_load(): void
    {
        $this->get('/login')->assertOk()->assertSee('Continue as guest');
        $this->get('/register')->assertOk();
    }

    public function test_app_pages_require_signing_in(): void
    {
        foreach (['/dashboard', '/competitions', '/training', '/exercises/index', '/courses', '/players'] as $page) {
            $this->get($page)->assertRedirect('/login');
        }
    }

    public function test_signed_in_players_reach_the_dashboard_and_skip_the_login_page(): void
    {
        $player = User::factory()->create();

        $this->actingAs($player)->get('/dashboard')->assertOk();
        $this->actingAs($player)->get('/login')->assertRedirect('/dashboard');
    }

    public function test_admin_pages_are_for_admins_only(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->verifiedPlayer()->create())->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk();
    }
}
