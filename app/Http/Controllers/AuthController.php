<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            return redirect()->intended('/dashboard');
        }

        throw ValidationException::withMessages([
            'email' => ['The provided credentials do not match our records.'],
        ]);
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'gender' => ['required', 'in:male,female'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
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

        return redirect('/dashboard');
    }

    /**
     * Signs in to the shared read-only guest account so visitors can look
     * around without registering. The account cannot log in with a password.
     */
    public function guest(Request $request)
    {
        $guest = User::firstOrCreate(
            ['email' => User::GUEST_EMAIL],
            ['name' => 'Guest', 'password' => Hash::make(Str::random(64)), 'role' => 'guest']
        );

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
}
