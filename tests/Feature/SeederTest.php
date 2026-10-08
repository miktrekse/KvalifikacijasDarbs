<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Exercise;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\GuestUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_install_gets_an_admin_a_guest_and_the_starter_library(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::where('role', 'admin')->count());
        $this->assertSame('guest', User::where('email', User::GUEST_EMAIL)->value('role'));
        $this->assertGreaterThan(0, Category::count());
        $this->assertGreaterThan(0, Exercise::where('is_public', true)->count());
    }

    public function test_the_admin_never_gets_a_password_from_the_repository(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('role', 'admin')->first();
        $this->assertFalse(Hash::check('admin123', $admin->password));
    }

    public function test_seeding_twice_changes_nothing_and_does_not_fail(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('role', 'admin')->first();
        $counts = [User::count(), Category::count(), Exercise::count()];

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($counts, [User::count(), Category::count(), Exercise::count()]);
        $this->assertSame($admin->password, $admin->fresh()->password);
    }

    public function test_demo_accounts_are_not_created_in_production(): void
    {
        $this->app['env'] = 'production';

        // Called directly: `db:seed` would stop to ask for confirmation in production
        $this->app->make(DatabaseSeeder::class)->setContainer($this->app)->__invoke();

        $this->assertDatabaseMissing('users', ['email' => 'player@discstats.com']);
        $this->assertDatabaseMissing('users', ['email' => 'verified@discstats.com']);
    }

    public function test_the_guest_address_is_taken_back_from_a_normal_account(): void
    {
        $taken = User::factory()->create(['email' => User::GUEST_EMAIL, 'password' => Hash::make('attacker-pass')]);

        $this->seed(GuestUserSeeder::class);

        $guest = $taken->fresh();
        $this->assertTrue($guest->isGuest());
        $this->assertFalse(Hash::check('attacker-pass', $guest->password));
    }
}
