@extends('layouts.dashboard')

@section('title', 'Dashboard')

@section('content')
@php
    $user = Auth::user();
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
@endphp
<div class="space-y-6">
    <section class="ds-hero">
        <div class="grid gap-8 lg:grid-cols-[1.3fr_1fr] lg:items-end">
            <div>
                <p class="ds-eyebrow">{{ $greeting }}</p>
                <h1 class="mt-4">Hey {{ strtok($user->name, ' ') }},<br><em>let's go throw.</em></h1>
                <p class="mt-4 max-w-md text-sm sm:text-base">Manage your disc golf training exercises, log rounds shot by shot and track your progress.</p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="{{ route('training.create') }}" class="ds-btn ds-btn--flight">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                        Start a round
                    </a>
                    <a href="/exercises/create" class="ds-btn ds-btn--ghost">Create exercise</a>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="ds-stat" style="--accent: #3fbd82">
                    <p class="ds-stat__label">Created</p>
                    <p class="ds-stat__value">{{ $myExercises->count() }}</p>
                    <span class="ds-stat__ring"></span>
                </div>
                <div class="ds-stat" style="--accent: #f26b3a">
                    <p class="ds-stat__label">Saved</p>
                    <p class="ds-stat__value">{{ $addedExercises->count() }}</p>
                    <span class="ds-stat__ring"></span>
                </div>
                <div class="ds-stat" style="--accent: #c6f36b">
                    <p class="ds-stat__label">Rating</p>
                    <p class="ds-stat__value">{{ $user->rating ?? '—' }}</p>
                    <span class="ds-stat__ring"></span>
                </div>
                <div class="ds-stat" style="--accent: #7cc9ff">
                    <p class="ds-stat__label">Events played</p>
                    <p class="ds-stat__value">{{ $user->completedCompetitionsCount() }}</p>
                    <span class="ds-stat__ring"></span>
                </div>
            </div>
        </div>
    </section>

    <section>
        <div class="mb-3 flex items-end justify-between">
            <h2 class="font-display text-xl font-extrabold text-ink">Quick actions</h2>
            <span class="font-mono text-[0.65rem] uppercase tracking-[0.2em] text-gray-400">Pick your line</span>
        </div>
        <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
            <a href="{{ route('training.index') }}" class="ds-tile" style="--accent: #22a268">
                <span class="ds-tile__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/></svg></span>
                <span><span class="ds-tile__title block">Training rounds</span><span class="ds-tile__text block">Score every throw, hole by hole.</span></span>
                <span class="ds-tile__arrow">↗</span>
            </a>
            <a href="/exercises/index" class="ds-tile" style="--accent: #f26b3a">
                <span class="ds-tile__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5zM4 19.5A2.5 2.5 0 0 0 6.5 22H20v-5"/></svg></span>
                <span><span class="ds-tile__title block">Browse drills</span><span class="ds-tile__text block">Public exercises from the community.</span></span>
                <span class="ds-tile__arrow">↗</span>
            </a>
            <a href="/competitions" class="ds-tile" style="--accent: #8b5cf6">
                <span class="ds-tile__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0zM17 5h3v2a3 3 0 0 1-3 3M7 5H4v2a3 3 0 0 0 3 3"/></svg></span>
                <span><span class="ds-tile__title block">Competitions</span><span class="ds-tile__text block">Find events and register.</span></span>
                <span class="ds-tile__arrow">↗</span>
            </a>
            <a href="{{ route('courses.index') }}" class="ds-tile" style="--accent: #0ea5e9">
                <span class="ds-tile__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M9 3 3 6v15l6-3 6 3 6-3V3l-6 3zM9 3v15M15 6v15"/></svg></span>
                <span><span class="ds-tile__title block">Course map</span><span class="ds-tile__text block">Discover courses near you.</span></span>
                <span class="ds-tile__arrow">↗</span>
            </a>
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
        <section class="ds-card">
            <div class="ds-card__head">
                <h2 class="ds-card__title">Your latest exercises</h2>
                <a href="/exercises/saved" class="text-sm font-bold text-indigo-600 hover:text-indigo-800">My saved →</a>
            </div>
            <div class="ds-card__body">
                @forelse($myExercises->take(5) as $exercise)
                    <a href="{{ route('exercises.view', $exercise->id) }}" class="ds-row group">
                        <span class="flex min-w-0 items-center gap-3">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-indigo-50 font-mono text-xs font-bold text-indigo-700">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="truncate font-semibold text-ink group-hover:text-indigo-700">{{ $exercise->title }}</span>
                        </span>
                        <span class="shrink-0 font-mono text-[0.68rem] uppercase tracking-wider text-gray-400">{{ $exercise->created_at->diffForHumans(short: true) }}</span>
                    </a>
                @empty
                    <div class="rounded-2xl border border-dashed border-gray-300 px-4 py-8 text-center">
                        <p class="text-sm text-gray-500">You haven't created any exercises yet.</p>
                        <a href="/exercises/create" class="mt-2 inline-block text-sm font-bold text-flight hover:underline">Create your first drill →</a>
                    </div>
                @endforelse
            </div>
        </section>

        <section class="ds-card">
            <div class="ds-card__head">
                <h2 class="ds-card__title">Categories</h2>
                <span class="font-mono text-xs text-gray-400">{{ $categories->count() }}</span>
            </div>
            <div class="ds-card__body">
                <p class="mb-4 text-sm text-gray-500">Browse exercises by category.</p>
                @if($categories->count() > 0)
                    <div class="flex flex-wrap gap-2">
                        @foreach($categories as $category)
                            <span class="ds-pill" style="background-color: {{ $category->color }}1f; color: {{ $category->color }}">
                                {{ $category->name }}
                            </span>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-500">No categories yet.</p>
                @endif
            </div>
        </section>
    </div>
</div>
@endsection
