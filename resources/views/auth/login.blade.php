@extends('layouts.app')

@section('title', 'Login')

@section('auth')
<div class="ds-auth__form">
    <div class="mb-8 flex items-center gap-3 lg:hidden">
        <img src="{{ asset('images/logo.png') }}" alt="" class="h-12 w-12 rounded-2xl shadow-md">
        <span class="font-display text-2xl font-extrabold text-ink">Disc<span class="text-indigo-600">Stats</span></span>
    </div>

    <p class="ds-eyebrow">Welcome back</p>
    <h1 class="mt-3">Step up to<br>the tee pad.</h1>
    <p class="mt-3 text-sm text-gray-500">Sign in to pick up your rounds, drills and tournaments where you left off.</p>

    <form class="mt-8 space-y-5" action="{{ route('login') }}" method="POST">
        @csrf
        <label class="ds-field" for="email">
            <span class="ds-field__label">Email</span>
            <input id="email" name="email" type="email" autocomplete="email" required
                class="ds-field__input" placeholder="you@example.com" value="{{ old('email') }}">
            @error('email')
                <p class="ds-field__error">{{ $message }}</p>
            @enderror
        </label>

        <label class="ds-field" for="password">
            <span class="ds-field__label">Password</span>
            <input id="password" name="password" type="password" autocomplete="current-password" required
                class="ds-field__input" placeholder="••••••••">
            @error('password')
                <p class="ds-field__error">{{ $message }}</p>
            @enderror
        </label>

        <label for="remember" class="flex cursor-pointer items-center gap-2 text-sm font-medium text-gray-600">
            <input id="remember" name="remember" type="checkbox" class="h-4 w-4 rounded">
            Remember me
        </label>

        <button type="submit" class="ds-submit">
            Sign in
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </button>
    </form>

    <p class="mt-8 text-center text-sm text-gray-500">
        New to DiscStats?
        <a href="{{ route('register') }}" class="font-bold text-flight hover:underline">Create an account</a>
    </p>
</div>
@endsection
