@extends('layouts.app')

@section('title', 'Reset password')

@section('auth')
<div class="ds-auth__form">
    <p class="ds-eyebrow">Password reset</p>
    <h1 class="mt-3">Choose a new<br>password.</h1>

    <form class="mt-8 space-y-5" action="{{ route('password.update') }}" method="POST">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <label class="ds-field" for="email">
            <span class="ds-field__label">Email</span>
            <input id="email" name="email" type="email" autocomplete="email" required
                class="ds-field__input" value="{{ old('email', $email) }}">
            @error('email')
                <p class="ds-field__error">{{ $message }}</p>
            @enderror
        </label>

        <label class="ds-field" for="password">
            <span class="ds-field__label">New password</span>
            <input id="password" name="password" type="password" autocomplete="new-password" required minlength="8"
                class="ds-field__input" placeholder="At least 8 characters">
            @error('password')
                <p class="ds-field__error">{{ $message }}</p>
            @enderror
        </label>

        <label class="ds-field" for="password_confirmation">
            <span class="ds-field__label">Repeat new password</span>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required
                class="ds-field__input">
        </label>

        <button type="submit" class="ds-submit">Save new password</button>
    </form>
</div>
@endsection
