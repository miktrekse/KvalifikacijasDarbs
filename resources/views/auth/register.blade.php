@extends('layouts.app')

@section('title', 'Register')

@section('auth')
<div class="ds-auth__form">
    <div class="mb-8 flex items-center gap-3 lg:hidden">
        <img src="{{ asset('images/logo.png') }}" alt="" class="h-12 w-12 rounded-2xl shadow-md">
        <span class="font-display text-2xl font-extrabold text-ink">Disc<span class="text-indigo-600">Stats</span></span>
    </div>

    <p class="ds-eyebrow">Join the card</p>
    <h1 class="mt-3">Start tracking<br>your game.</h1>
    <p class="mt-3 text-sm text-gray-500">One account for training rounds, drills and tournament registrations.</p>

    <form class="mt-8 space-y-5" action="{{ route('register') }}" method="POST">
        @csrf
        <label class="ds-field" for="name">
            <span class="ds-field__label">Full name</span>
            <input id="name" name="name" type="text" autocomplete="name" required
                class="ds-field__input" placeholder="Paul McBeth" value="{{ old('name') }}">
            @error('name')
                <p class="ds-field__error">{{ $message }}</p>
            @enderror
        </label>

        <label class="ds-field" for="email">
            <span class="ds-field__label">Email</span>
            <input id="email" name="email" type="email" autocomplete="email" required
                class="ds-field__input" placeholder="you@example.com" value="{{ old('email') }}">
            @error('email')
                <p class="ds-field__error">{{ $message }}</p>
            @enderror
        </label>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <label class="ds-field" for="gender">
                <span class="ds-field__label">Gender</span>
                <select id="gender" name="gender" required class="ds-field__input">
                    <option value="">Select…</option>
                    <option value="female" {{ old('gender') === 'female' ? 'selected' : '' }}>Female</option>
                    <option value="male" {{ old('gender') === 'male' ? 'selected' : '' }}>Male</option>
                </select>
                @error('gender')
                    <p class="ds-field__error">{{ $message }}</p>
                @enderror
            </label>

            <label class="ds-field" for="date_of_birth">
                <span class="ds-field__label">Date of birth</span>
                <input id="date_of_birth" name="date_of_birth" type="date" max="{{ now()->toDateString() }}" required
                    class="ds-field__input" value="{{ old('date_of_birth') }}">
                @error('date_of_birth')
                    <p class="ds-field__error">{{ $message }}</p>
                @enderror
            </label>
        </div>
        <p class="-mt-2 rounded-xl bg-flight-soft/60 px-3 py-2 text-xs text-gray-600">
            Your gender and age help determine eligibility for female, masters, and junior divisions.
        </p>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <label class="ds-field" for="password">
                <span class="ds-field__label">Password</span>
                <input id="password" name="password" type="password" autocomplete="new-password" required
                    class="ds-field__input" placeholder="Min 8 characters">
                @error('password')
                    <p class="ds-field__error">{{ $message }}</p>
                @enderror
            </label>

            <label class="ds-field" for="password_confirmation">
                <span class="ds-field__label">Confirm</span>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required
                    class="ds-field__input" placeholder="Repeat password">
            </label>
        </div>

        <button type="submit" class="ds-submit">
            Create account
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </button>
    </form>

    <p class="mt-8 text-center text-sm text-gray-500">
        Already have an account?
        <a href="{{ route('login') }}" class="font-bold text-flight hover:underline">Sign in</a>
    </p>
</div>
@endsection
