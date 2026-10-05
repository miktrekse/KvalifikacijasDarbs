@extends('layouts.dashboard')

@section('title', 'My Saved Exercises')

@section('content')
@php
    $isFiltering = collect($filters)->except('sort')->filter()->isNotEmpty();
    $hours = intdiv($summary['minutes'], 60);
    $practiceTime = $summary['minutes'] ? ($hours ? $hours . 'h ' : '') . ($summary['minutes'] % 60) . 'm' : '—';
@endphp

<div class="space-y-6">
    <section class="ds-hero">
        <div class="grid gap-8 lg:grid-cols-[1.1fr_1fr] lg:items-end">
            <div>
                <p class="ds-eyebrow">Your practice plan</p>
                <h1 class="mt-4">My saved<br><em>drills.</em></h1>
                <p class="mt-4 max-w-md text-sm sm:text-base">Everything you've bookmarked to practise — filter it down to what fits today's session.</p>
                <a href="{{ route('exercises.index') }}" class="ds-btn ds-btn--flight mt-6">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    Browse the library
                </a>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="ds-stat" style="--accent: #f26b3a">
                    <p class="ds-stat__label">Saved drills</p>
                    <p class="ds-stat__value">{{ $summary['saved'] }}</p>
                    <span class="ds-stat__ring"></span>
                </div>
                <div class="ds-stat" style="--accent: #3fbd82">
                    <p class="ds-stat__label">Practice time</p>
                    <p class="ds-stat__value">{{ $practiceTime }}</p>
                    <span class="ds-stat__ring"></span>
                </div>
                <div class="ds-stat" style="--accent: #c6f36b">
                    <p class="ds-stat__label">Categories</p>
                    <p class="ds-stat__value">{{ $summary['categories'] }}<span class="text-lg text-white/40">/{{ $categories->count() }}</span></p>
                    <span class="ds-stat__ring"></span>
                </div>
                <div class="ds-stat" style="--accent: #7cc9ff">
                    <p class="ds-stat__label">Your own</p>
                    <p class="ds-stat__value">{{ $summary['own'] }}</p>
                    <span class="ds-stat__ring"></span>
                </div>
            </div>
        </div>
    </section>

    @if($summary['saved'] === 0)
        <div class="ds-card px-6 py-14 text-center">
            <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-flight-soft text-flight">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
            </span>
            <p class="mt-4 font-display text-2xl font-extrabold text-ink">No saved drills yet</p>
            <p class="mt-2 text-sm text-gray-500">Tap the bookmark on any drill in the library and it will show up here.</p>
            <a href="{{ route('exercises.index') }}" class="ds-btn ds-btn--flight mt-6">Find drills to save</a>
        </div>
    @else
        @include('exercises.partials.filters', [
            'action' => route('exercises.saved'),
            'noun' => 'saved drill',
            'showSavedToggle' => false,
            'sorts' => ['recent' => 'Recently saved', 'newest' => 'Newest drill', 'popular' => 'Most saved', 'easiest' => 'Easiest first', 'hardest' => 'Hardest first', 'shortest' => 'Shortest first'],
        ])

        @if($exercises->count() > 0)
            {{-- Un-saving here dims the card instead of removing it, so a mis-tap is one click to undo --}}
            <div class="ds-saved-grid grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach($exercises as $exercise)
                    @include('exercises.partials.card', ['isSaved' => true])
                @endforeach
            </div>

            <div>{{ $exercises->links() }}</div>
        @else
            <div class="ds-card px-6 py-14 text-center">
                <p class="font-display text-2xl font-extrabold text-ink">No saved drills match those filters</p>
                <p class="mt-2 text-sm text-gray-500">Try removing a filter, or find more drills in the library.</p>
                <div class="mt-6 flex flex-wrap justify-center gap-3">
                    <a href="{{ route('exercises.saved') }}" class="ds-btn ds-btn--line">Clear all filters</a>
                    <a href="{{ route('exercises.index', request()->except(['page', 'sort'])) }}" class="ds-btn ds-btn--flight">Search the library</a>
                </div>
            </div>
        @endif
    @endif
</div>
@endsection
