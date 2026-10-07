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
                <a href="{{ route('training.edit', $round->id) }}" class="ds-btn ds-btn--ghost !py-2">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                    Edit
                </a>
                @php
                    $othersKeeping = $round->players->where('id', '!=', Auth::id())->whereNull('pivot.removed_at');
                    $deleteConfirm = $othersKeeping->isEmpty()
                        ? 'Delete this round for good? Its scores and stats are removed and this can\'t be undone.'
                        : 'Remove this round from your list and stats? ' . $othersKeeping->pluck('name')->join(', ', ' and ') . ' will still see it until they delete it too.';
                @endphp
                <form method="POST" action="{{ route('training.destroy', $round->id) }}" onsubmit="return confirm(@js($deleteConfirm));">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="ds-btn ds-btn--ghost !py-2 hover:!border-red-400/60 hover:!bg-red-500/20" title="{{ $othersKeeping->isEmpty() ? 'Delete round' : 'Remove from my rounds' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>
                        {{ $othersKeeping->isEmpty() ? 'Delete' : 'Remove' }}
                    </button>
                </form>
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
