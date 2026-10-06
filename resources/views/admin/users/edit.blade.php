@extends('layouts.dashboard')

@section('title', 'Edit User')

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <a href="{{ route('admin.users') }}" class="ds-back">
        <svg fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M15 19l-7-7 7-7"/></svg>
        Users
    </a>

    <section class="ds-hero ds-hero--form">
        <p class="ds-eyebrow">Admin · Edit user</p>
        <h1 class="mt-4">{{ $user->name }}</h1>
        <p class="mt-3 max-w-lg text-sm sm:text-base">{{ $user->email }}</p>
    </section>

    <form action="{{ route('admin.users.update', $user->id) }}" method="POST" class="ds-formstack">
        @csrf
        @method('PUT')
        @include('admin.users.partials.form-fields', ['editing' => $user])

        <div class="ds-formbar">
            <p class="ds-formbar__note">Fields marked <span class="font-bold text-flight">*</span> are required.</p>
            <div class="ds-formbar__actions">
                <a href="{{ route('admin.users') }}" class="ds-btn ds-btn--line">Cancel</a>
                <button type="submit" class="ds-btn ds-btn--flight">Save changes</button>
            </div>
        </div>
    </form>
</div>
@endsection
