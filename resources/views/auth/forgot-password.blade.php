@extends('layouts.app')

@section('title', 'Forgot password')

@section('auth')
<div class="ds-auth__form">
    <p class="ds-eyebrow">Password reset</p>
    <h1 class="mt-3">Forgot your<br>password?</h1>
    <p class="mt-3 text-sm text-gray-500">Enter the email you signed up with and we'll send you a link to choose a new password.</p>

    @if(session('success'))
        <p class="mt-6 rounded-xl bg-green-50 px-4 py-3 text-sm font-semibold text-green-800">{{ session('success') }}</p>
    @endif

    <form class="mt-8 space-y-5" action="{{ route('password.email') }}" method="POST">
        @csrf
        <label class="ds-field" for="email">
            <span class="ds-field__label">Email</span>
            <input id="email" name="email" type="email" autocomplete="email" required
                class="ds-field__input" placeholder="you@example.com" value="{{ old('email') }}">
            @error('email')
                <p class="ds-field__error">{{ $message }}</p>
            @enderror
        </label>

        <button type="submit" class="ds-submit">Email me a reset link</button>
    </form>

    <p class="mt-8 text-center text-sm text-gray-500">
        Remembered it?
        <a href="{{ route('login') }}" class="font-bold text-flight hover:underline">Back to sign in</a>
    </p>
</div>
@endsection
