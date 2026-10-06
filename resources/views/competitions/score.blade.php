@extends('layouts.dashboard')

@section('title', 'Scoring · ' . $competition->name)

@section('content')
<div class="mx-auto max-w-5xl space-y-5" id="scoring-app">
    <a href="{{ route('competitions.view', $competition->id) }}" class="inline-flex items-center gap-1 text-sm font-bold text-indigo-700 hover:text-indigo-900">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M15 19l-7-7 7-7"/></svg>
        {{ $competition->name }}
    </a>

    <section class="ds-hero !py-6 sm:!py-7">
        <div class="flex flex-wrap items-end justify-between gap-5">
            <div>
                <p class="ds-eyebrow">Card {{ $group->number }} · Tee off on hole {{ $group->starting_hole }}</p>
                <h1 class="mt-3 !text-[clamp(1.7rem,4vw,2.6rem)]">{{ $competition->course_name ?: $competition->name }}</h1>
                <p class="mt-2 text-sm">{{ $competition->startsAt()->format('l, M j · H:i') }} · {{ $competition->holes }} holes · Par <span id="course-par"></span></p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span id="live-pill" class="ds-score-live"></span>
                @if($groups->count() > 1)
                    <form method="GET" class="flex items-center gap-2">
                        <label for="group-switch" class="font-mono text-[0.65rem] uppercase tracking-widest text-white/60">Card</label>
                        <select id="group-switch" name="group" onchange="this.form.submit()" class="!rounded-full !border-white/20 !bg-white/10 !py-1.5 !text-sm !text-white">
                            @foreach($groups as $g)
                                <option value="{{ $g->number }}" class="text-ink" @selected($g->id === $group->id)>Card {{ $g->number }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif
            </div>
        </div>
    </section>

    @php
        $scoring = $payload + [
            'competitionId' => $competition->id,
            'groupId' => $group->id,
            'groupNumber' => $group->number,
            'startingHole' => $group->starting_hole,
            'scoringOpen' => $competition->scoringIsOpen(),
            'closed' => $competition->isClosed(),
            'startsAt' => $competition->startsAt()->toIso8601String(),
            'storageKey' => 'discstats:comp-' . $competition->id . ':card-' . $group->id . ':scoring-for',
            'shotUrl' => route('competitions.shots.store', ['id' => $competition->id, 'hole' => '__HOLE__']),
            'undoUrl' => route('competitions.shots.undo', ['id' => $competition->id, 'hole' => '__HOLE__']),
            'scoreUrl' => route('competitions.shots.score', ['id' => $competition->id, 'hole' => '__HOLE__']),
            'acceptUrl' => route('competitions.shots.accept', ['id' => $competition->id, 'hole' => '__HOLE__']),
            'closedMessage' => 'This competition is finished — the scorecard is read-only.',
            'dataUrl' => route('competitions.score.data', ['id' => $competition->id, 'group' => $group->number]),
        ];
    @endphp
    @include('partials.scorekeeper', ['scoring' => $scoring])
</div>
@endsection
