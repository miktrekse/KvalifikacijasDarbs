@extends('layouts.dashboard')

@section('title', $round->course_name)

@section('content')
<div class="mx-auto max-w-5xl space-y-5">
    <a href="{{ route('training.index') }}" class="inline-flex items-center gap-1 text-sm font-bold text-indigo-700 hover:text-indigo-900">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M15 19l-7-7 7-7"/></svg>
        Training rounds
    </a>

    <section class="ds-hero !py-6 sm:!py-7">
        <div class="flex flex-wrap items-end justify-between gap-5">
            <div>
                <p class="ds-eyebrow">Training round · {{ $round->created_at->format('M j, Y') }}</p>
                <h1 class="mt-3 !text-[clamp(1.7rem,4vw,2.6rem)]">{{ $round->course_name }}</h1>
                <p class="mt-2 text-sm">
                    {{ $round->holes_count }} holes · Par <span id="course-par"></span>
                    @if($round->course_locality) · {{ $round->course_locality }} @endif
                    · {{ $round->players->count() }} {{ Str::plural('player', $round->players->count()) }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span id="live-pill" class="ds-score-live"></span>
                @if(!$round->isCompleted())
                    <form method="POST" action="{{ route('training.complete', $round->id) }}" onsubmit="return confirm('Finish this round? The scorecard becomes read-only.');">
                        @csrf
                        <button type="submit" class="ds-btn ds-btn--ghost !py-2">Finish round</button>
                    </form>
                @endif
            </div>
        </div>
    </section>

    @include('partials.scorekeeper', ['scoring' => $payload + [
        'startingHole' => 1,
        'scoringOpen' => !$round->isCompleted(),
        'closed' => $round->isCompleted(),
        'startsAt' => $round->created_at->toIso8601String(),
        'closedMessage' => 'This round is finished — the scorecard is read-only.',
        'storageKey' => 'discstats:training-' . $round->id . ':scoring-for',
        'shotUrl' => route('training.shots.store', ['round' => $round->id, 'hole' => '__HOLE__']),
        'undoUrl' => route('training.shots.undo', ['round' => $round->id, 'hole' => '__HOLE__']),
        'scoreUrl' => route('training.shots.score', ['round' => $round->id, 'hole' => '__HOLE__']),
        'dataUrl' => $round->players->count() > 1 ? route('training.data', $round->id) : null,
    ]])
</div>
@endsection
