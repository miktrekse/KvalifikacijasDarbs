<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * The migration chain must run on the test database (SQLite) as well as on MySQL: the role
 * migrations used to contain MySQL-only SQL and stopped every database test from running.
 */
class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_role_can_be_stored(): void
    {
        foreach (['admin', 'verified', 'user', 'guest'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->assertSame($role, $user->fresh()->role);
        }
    }

    public function test_new_accounts_default_to_the_user_role(): void
    {
        $user = User::forceCreate(['name' => 'Plain', 'email' => 'plain@example.com', 'password' => 'secret-pass']);

        $this->assertSame('user', $user->fresh()->role);
    }

    public function test_unknown_roles_are_rejected_by_the_database(): void
    {
        $this->expectException(QueryException::class);

        User::factory()->create(['role' => 'superuser']);
    }

    public function test_migrations_can_be_rolled_back_and_run_again(): void
    {
        $this->assertSame(0, Artisan::call('migrate:rollback', ['--step' => 100]));
        $this->assertSame(0, Artisan::call('migrate'));

        $this->assertSame('guest', User::factory()->guest()->create()->fresh()->role);
    }
}
