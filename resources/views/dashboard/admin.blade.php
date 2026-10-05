@extends('layouts.dashboard')

@section('title', 'Admin Dashboard')

@section('content')
<div class="space-y-6">
    <section class="ds-hero">
        <div class="grid gap-8 lg:grid-cols-[1fr_1.3fr] lg:items-end">
            <div>
                <p class="ds-eyebrow">Control tower</p>
                <h1 class="mt-4">Admin<br><em>panel.</em></h1>
                <p class="mt-4 max-w-md text-sm sm:text-base">Welcome, {{ Auth::user()->name }}! You have full control over the platform.</p>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="ds-stat" style="--accent: #3fbd82">
                    <p class="ds-stat__label">Users</p>
                    <p class="ds-stat__value">{{ $totalUsers }}</p>
                    <span class="ds-stat__ring"></span>
                </div>
                <div class="ds-stat" style="--accent: #f26b3a">
                    <p class="ds-stat__label">Exercises</p>
                    <p class="ds-stat__value">{{ $totalExercises }}</p>
                    <span class="ds-stat__ring"></span>
                </div>
                <div class="ds-stat" style="--accent: #c6f36b">
                    <p class="ds-stat__label">Public</p>
                    <p class="ds-stat__value">{{ $publicExercises }}</p>
                    <span class="ds-stat__ring"></span>
                </div>
                <div class="ds-stat" style="--accent: #7cc9ff">
                    <p class="ds-stat__label">Comments</p>
                    <p class="ds-stat__value">{{ $totalComments }}</p>
                    <span class="ds-stat__ring"></span>
                </div>
            </div>
        </div>
    </section>

    <section>
        <div class="mb-3 flex items-end justify-between">
            <h2 class="font-display text-xl font-extrabold text-ink">Quick actions</h2>
            <span class="font-mono text-[0.65rem] uppercase tracking-[0.2em] text-gray-400">Manage content &amp; users</span>
        </div>
        <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
            <a href="{{ route('admin.users') }}" class="ds-tile" style="--accent: #22a268">
                <span class="ds-tile__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/><circle cx="9" cy="7" r="4"/></svg></span>
                <span><span class="ds-tile__title block">Manage users</span><span class="ds-tile__text block">Roles, accounts, verification.</span></span>
                <span class="ds-tile__arrow">↗</span>
            </a>
            <a href="{{ route('admin.exercises') }}" class="ds-tile" style="--accent: #f26b3a">
                <span class="ds-tile__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5zM4 19.5A2.5 2.5 0 0 0 6.5 22H20v-5"/></svg></span>
                <span><span class="ds-tile__title block">Manage exercises</span><span class="ds-tile__text block">Edit or remove any drill.</span></span>
                <span class="ds-tile__arrow">↗</span>
            </a>
            <a href="{{ route('admin.categories') }}" class="ds-tile" style="--accent: #eab308">
                <span class="ds-tile__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82zM7 7h.01"/></svg></span>
                <span><span class="ds-tile__title block">Categories</span><span class="ds-tile__text block">Organise the drill library.</span></span>
                <span class="ds-tile__arrow">↗</span>
            </a>
            <a href="{{ route('admin.comments') }}" class="ds-tile" style="--accent: #0ea5e9">
                <span class="ds-tile__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span>
                <span><span class="ds-tile__title block">Comments</span><span class="ds-tile__text block">Moderate the conversation.</span></span>
                <span class="ds-tile__arrow">↗</span>
            </a>
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="ds-card">
            <div class="ds-card__head">
                <h2 class="ds-card__title">Recent users</h2>
                <a href="{{ route('admin.users') }}" class="text-sm font-bold text-indigo-600 hover:text-indigo-800">View all →</a>
            </div>
            <div class="ds-card__body">
                @forelse($recentUsers as $user)
                    <div class="ds-row">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="ds-avatar" style="width: 2.4rem; height: 2.4rem;">
                                @if($user->avatar)
                                    <img src="{{ asset('storage/' . $user->avatar) }}" alt="">
                                @else
                                    <span>{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                                @endif
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold text-ink">{{ $user->name }}</p>
                                <p class="truncate text-xs text-gray-500">{{ $user->email }}</p>
                            </div>
                        </div>
                        @if($user->isAdmin())
                            <span class="ds-pill bg-flight-soft text-[#b4401a]">Admin</span>
                        @elseif($user->isVerified())
                            <span class="ds-pill bg-indigo-100 text-indigo-800">Verified</span>
                        @else
                            <span class="ds-pill bg-gray-100 text-gray-600">User</span>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No users yet.</p>
                @endforelse
            </div>
        </section>

        <section class="ds-card">
            <div class="ds-card__head">
                <h2 class="ds-card__title">Recent exercises</h2>
                <a href="{{ route('admin.exercises') }}" class="text-sm font-bold text-indigo-600 hover:text-indigo-800">View all →</a>
            </div>
            <div class="ds-card__body">
                @forelse($recentExercises as $exercise)
                    <div class="ds-row">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-bold text-ink">{{ $exercise->title }}</p>
                            <p class="text-xs text-gray-500">By {{ $exercise->user->name ?? 'Unknown' }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            @if($exercise->is_public)
                                <span class="ds-pill bg-indigo-100 text-indigo-800">Public</span>
                            @else
                                <span class="ds-pill bg-gray-100 text-gray-600">Private</span>
                            @endif
                            <a href="{{ route('admin.exercises.edit', $exercise->id) }}" class="grid h-8 w-8 place-items-center rounded-full text-gray-400 transition hover:bg-indigo-50 hover:text-indigo-600" title="Edit">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                            </a>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No exercises yet.</p>
                @endforelse
            </div>
        </section>
    </div>
</div>
@endsection
