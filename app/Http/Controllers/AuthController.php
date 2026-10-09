<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Rules\NotReservedEmail;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** Failed sign-ins allowed per email + IP before a one-minute lockout. */
    public const MAX_LOGIN_ATTEMPTS = 5;

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        // Guessing passwords for one account is limited per email + IP (the route also limits per IP)
        $throttleKey = 'login:' . Str::lower($credentials['email']) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => ['Too many sign-in attempts. Try again in ' . RateLimiter::availableIn($throttleKey) . ' seconds.'],
            ]);
        }

        // The shared guest account never signs in with a password, even one somebody knows
        $signedIn = Auth::attemptWhen($credentials, fn (User $user) => !$user->isGuest(), $request->boolean('remember'));

        if (!$signedIn) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return redirect()->intended('/dashboard');
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users', new NotReservedEmail()],
            'gender' => ['required', 'in:male,female'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'gender' => $validated['gender'],
            'date_of_birth' => $validated['date_of_birth'],
            'password' => Hash::make($validated['password']),
            'role' => 'user',
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/dashboard');
    }

    /**
     * Signs in to the shared read-only guest account so visitors can look around without
     * registering. The installer creates the account (GuestUserSeeder) on a domain nobody can
     * register, and only a row that really has the guest role is ever used: if anything else
     * owns the address, guest access is refused instead of signing visitors in to it.
     */
    public function guest(Request $request)
    {
        // createOrFirst: two first visitors at the same moment still share one row
        $guest = User::createOrFirst(
            ['email' => User::GUEST_EMAIL],
            ['name' => 'Guest', 'password' => Hash::make(Str::random(64)), 'role' => 'guest']
        );

        if (!$guest->isGuest()) {
            report(new \RuntimeException('The guest address belongs to an account without the guest role; guest access is disabled. Run php artisan db:seed --class=GuestUserSeeder.'));

            return redirect()->route('login')->with('error', 'Guest access is not available right now.');
        }

        Auth::login($guest);
        $request->session()->regenerate();

        return redirect('/dashboard')->with('success', 'You are browsing as a guest. Create a free account to save rounds, drills and registrations.');
    }

    public function logout(Request $request)
    {
        $wasGuest = $request->user()?->isGuest();

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Guests leaving to sign up land straight on the registration form
        if ($wasGuest && $request->input('then') === 'register') {
            return redirect()->route('register');
        }

        return redirect('/login');
    }

    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    /**
     * Emails a password reset link. The answer is the same whether or not the address has an
     * account, so the form can't be used to find out who is registered.
     */
    public function sendResetLink(Request $request)
    {
        $email = $request->validate(['email' => ['required', 'email', 'max:255']])['email'];

        $user = User::where('email', $email)->first();
        if ($user && !$user->isGuest()) {
            Password::sendResetLink(['email' => $email]);
        }

        return back()->with('success', 'If an account exists for that address, a password reset link is on its way.');
    }

    public function showResetPassword(Request $request, string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return redirect()->route('login')->with('success', 'Your password has been changed. You can sign in now.');
    }
}
