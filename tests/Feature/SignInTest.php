<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Sign-in, sign-up limits and password reset. */
class SignInTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_player_can_sign_in_and_out(): void
    {
        $user = User::factory()->create(['password' => Hash::make('right-password')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'right-password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_remember_me_keeps_the_player_signed_in(): void
    {
        $user = User::factory()->create(['password' => Hash::make('right-password'), 'remember_token' => null]);

        $this->post('/login', ['email' => $user->email, 'password' => 'right-password', 'remember' => '1']);

        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_sign_in_locks_after_five_wrong_passwords(): void
    {
        $user = User::factory()->create(['password' => Hash::make('right-password')]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
                ->assertSessionHasErrors(['email' => 'The provided credentials do not match our records.']);
        }

        // Even the right password is refused during the lockout
        $response = $this->post('/login', ['email' => $user->email, 'password' => 'right-password']);
        $this->assertStringStartsWith('Too many sign-in attempts.', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_the_login_route_is_rate_limited_per_ip(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->post('/login', ['email' => "user{$i}@example.com", 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => 'another@example.com', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_registration_is_rate_limited_per_ip(): void
    {
        foreach (range(1, 5) as $i) {
            $this->post('/register', [
                'name' => "Player {$i}", 'email' => "p{$i}@example.com", 'gender' => 'male', 'date_of_birth' => '1990-01-01',
                'password' => 'long-enough-1', 'password_confirmation' => 'long-enough-1',
            ])->assertRedirect('/dashboard');
            $this->post('/logout');
        }

        $this->post('/register', [
            'name' => 'Player 6', 'email' => 'p6@example.com', 'gender' => 'male', 'date_of_birth' => '1990-01-01',
            'password' => 'long-enough-1', 'password_confirmation' => 'long-enough-1',
        ])->assertStatus(429);
        $this->assertDatabaseMissing('users', ['email' => 'p6@example.com']);
    }

    public function test_a_player_can_reset_a_forgotten_password(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'forgot@example.com']);

        $this->get('/forgot-password')->assertOk();
        $this->post('/forgot-password', ['email' => 'forgot@example.com'])->assertSessionHas('success');

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->get("/reset-password/{$token}?email=forgot@example.com")->assertOk();
        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'forgot@example.com',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('brand-new-pass', $user->fresh()->password));
    }

    public function test_a_wrong_reset_token_changes_nothing(): void
    {
        $user = User::factory()->create(['email' => 'forgot@example.com', 'password' => Hash::make('old-password')]);

        $this->post('/reset-password', [
            'token' => 'made-up', 'email' => 'forgot@example.com',
            'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_reset_requests_do_not_reveal_who_is_registered_and_skip_the_guest(): void
    {
        Notification::fake();
        $guest = User::factory()->guest()->create(['email' => User::GUEST_EMAIL]);
        $message = 'If an account exists for that address, a password reset link is on its way.';

        $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertSessionHas('success', $message);
        $this->post('/forgot-password', ['email' => User::GUEST_EMAIL])->assertSessionHas('success', $message);

        Notification::assertNothingSent();
    }
}
