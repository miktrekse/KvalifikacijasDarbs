@extends('layouts.dashboard')

@section('title', 'Exercises')

@section('content')
@php
    $isFiltering = collect($filters)->except('sort')->filter()->isNotEmpty();
@endphp

<div class="space-y-6">
    <section class="ds-hero">
        <div class="flex flex-wrap items-end justify-between gap-6">
            <div>
                <p class="ds-eyebrow">Drill library</p>
                <h1 class="mt-4">Find your<br><em>next drill.</em></h1>
                <p class="mt-4 max-w-md text-sm sm:text-base">{{ $totalPublic }} practice exercises for putting, driving, approaches and scrambling — filter for exactly what you want to work on.</p>
            </div>
            @unless(Auth::user()->isGuest())
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('exercises.saved') }}" class="ds-btn ds-btn--ghost">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                        My saved
                    </a>
                    <a href="{{ route('exercises.create') }}" class="ds-btn ds-btn--flight">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                        Create exercise
                    </a>
                </div>
            @endunless
        </div>
    </section>

    @include('exercises.partials.filters', [
        'action' => route('exercises.index'),
        'noun' => 'drill',
        'showSavedToggle' => !Auth::user()->isGuest(),
        'sorts' => ['newest' => 'Newest', 'popular' => 'Most saved', 'easiest' => 'Easiest first', 'hardest' => 'Hardest first', 'shortest' => 'Shortest first'],
    ])

    @if($exercises->count() > 0)
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach($exercises as $exercise)
                @include('exercises.partials.card', ['isSaved' => in_array($exercise->id, $savedIds, true)])
            @endforeach
        </div>

        <div>{{ $exercises->links() }}</div>
    @else
        <div class="ds-card px-6 py-14 text-center">
            <p class="font-display text-2xl font-extrabold text-ink">{{ $isFiltering ? 'No drills match those filters' : 'No public exercises yet' }}</p>
            <p class="mt-2 text-sm text-gray-500">{{ $isFiltering ? 'Try removing a filter or searching for something broader.' : 'Be the first to create an exercise and share it with the community!' }}</p>
            @if($isFiltering)
                <a href="{{ route('exercises.index') }}" class="ds-btn ds-btn--line mt-6">Clear all filters</a>
            @endif
        </div>
    @endif
</div>
@endsection
