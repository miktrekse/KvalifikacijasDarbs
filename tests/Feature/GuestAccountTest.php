<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** "Continue as guest" must always mean the shared read-only account, never a real one. */
class GuestAccountTest extends TestCase
{
    use RefreshDatabase;

    private function registration(string $email): array
    {
        return [
            'name' => 'Someone',
            'email' => $email,
            'gender' => 'male',
            'date_of_birth' => '1990-01-01',
            'password' => 'long-enough-1',
            'password_confirmation' => 'long-enough-1',
        ];
    }

    public function test_the_guest_address_cannot_be_registered(): void
    {
        $this->post('/register', $this->registration(User::GUEST_EMAIL))->assertSessionHasErrors('email');
        $this->post('/register', $this->registration('admin@DiscStats.Local'))->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_continue_as_guest_signs_in_to_a_read_only_account(): void
    {
        $this->post('/guest')->assertRedirect('/dashboard');

        $this->assertTrue(auth()->user()->isGuest());

        // Read-only: writes are turned away
        $this->post('/exercises', ['title' => 'Putting ladder', 'difficulty' => 'beginner']);
        $this->assertDatabaseCount('exercises', 0);
    }

    public function test_every_visitor_shares_the_one_guest_account(): void
    {
        $this->post('/guest');
        $this->post('/logout');
        $this->post('/guest');

        $this->assertSame(1, User::where('email', User::GUEST_EMAIL)->count());
    }

    public function test_continue_as_guest_never_signs_in_to_a_normal_account_on_the_guest_address(): void
    {
        // e.g. registered on an install from before the address was reserved
        User::factory()->create(['email' => User::GUEST_EMAIL, 'role' => 'user', 'password' => Hash::make('attacker-pass')]);

        $this->post('/guest')->assertRedirect(route('login'))->assertSessionHas('error');

        $this->assertGuest();
    }

    public function test_the_guest_account_cannot_sign_in_with_a_password(): void
    {
        User::factory()->guest()->create(['email' => User::GUEST_EMAIL, 'password' => Hash::make('known-password')]);

        $this->post('/login', ['email' => User::GUEST_EMAIL, 'password' => 'known-password'])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_admins_cannot_give_out_the_guest_address_or_change_the_guest_account(): void
    {
        $admin = User::factory()->admin()->create();
        $guest = User::factory()->guest()->create(['email' => User::GUEST_EMAIL]);
        $player = User::factory()->create();

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Fake', 'email' => 'fake@discstats.local', 'password' => 'long-enough-1',
            'password_confirmation' => 'long-enough-1', 'role' => 'admin',
        ])->assertSessionHasErrors('email');

        $this->actingAs($admin)->put("/admin/users/{$player->id}", [
            'name' => $player->name, 'email' => User::GUEST_EMAIL, 'role' => 'user',
        ])->assertSessionHasErrors('email');

        // The guest account itself is not reachable from the user admin
        $this->actingAs($admin)->get("/admin/users/{$guest->id}/edit")->assertNotFound();
        $this->actingAs($admin)->put("/admin/users/{$guest->id}", [
            'name' => 'Guest', 'email' => 'guest2@example.com', 'role' => 'admin',
        ])->assertNotFound();
        $this->assertSame('guest', $guest->fresh()->role);
    }
}
