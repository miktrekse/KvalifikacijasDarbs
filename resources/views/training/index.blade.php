@extends('layouts.dashboard')

@section('title', 'Training Rounds')

@section('content')
@php
    $fmtRel = fn (int $rel) => $rel === 0 ? 'E' : ($rel > 0 ? '+' . $rel : (string) $rel);
    $relClass = fn (int $rel) => $rel < 0 ? 'is-under' : ($rel > 0 ? 'is-over' : 'is-even');
    $scoreClass = fn (int $strokes, int $par) => $strokes === 1 ? 'is-ace' : match (true) {
        $strokes - $par <= -2 => 'is-eagle',
        $strokes - $par === -1 => 'is-birdie',
        $strokes - $par === 0 => 'is-par',
        $strokes - $par === 1 => 'is-bogey',
        default => 'is-double',
    };
@endphp
<div class="space-y-6">
    <section class="ds-hero">
        <div class="grid gap-8 lg:grid-cols-[1.1fr_1fr] lg:items-end">
            <div>
                <p class="ds-eyebrow">Practice makes pars</p>
                <h1 class="mt-4">Training<br><em>rounds.</em></h1>
                <p class="mt-4 max-w-md text-sm sm:text-base">Track practice rounds shot by shot, UDisc-style — solo or with friends.</p>
                <a href="{{ route('training.create') }}" class="ds-btn ds-btn--flight mt-6">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    Start a round
                </a>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="ds-stat" style="--accent: #3fbd82">
                    <p class="ds-stat__label">Rounds</p>
                    <p class="ds-stat__value">{{ $summary['rounds'] }}</p>
                    <span class="ds-stat__ring"></span>
                </div>
                <div class="ds-stat" style="--accent: #f26b3a">
                    <p class="ds-stat__label">Holes played</p>
                    <p class="ds-stat__value">{{ $summary['holes'] }}</p>
                    <span class="ds-stat__ring"></span>
                </div>
                <div class="ds-stat" style="--accent: #c6f36b">
                    <p class="ds-stat__label">Best round</p>
                    <p class="ds-stat__value">{{ $summary['best'] ? $fmtRel($summary['best']['relative']) : '—' }}</p>
                    @if($summary['best'])
                        <p class="mt-1 truncate text-xs text-white/60">{{ $summary['best']['round']->course_name }}</p>
                    @endif
                    <span class="ds-stat__ring"></span>
                </div>
                <div class="ds-stat" style="--accent: #7cc9ff">
                    <p class="ds-stat__label">Avg score</p>
                    <p class="ds-stat__value">{{ $summary['average'] !== null ? $fmtRel((int) round($summary['average'])) : '—' }}</p>
                    @if($summary['in_progress'])
                        <p class="mt-1 text-xs text-white/60">{{ $summary['in_progress'] }} in progress</p>
                    @endif
                    <span class="ds-stat__ring"></span>
                </div>
            </div>
        </div>
    </section>

    @if($rounds->isEmpty())
        <div class="ds-card px-6 py-14 text-center">
            <p class="font-display text-2xl font-extrabold text-ink">No rounds yet</p>
            <p class="mt-2 text-sm text-gray-500">Head out to a course and log your first round throw by throw.</p>
            <a href="{{ route('training.create') }}" class="ds-btn ds-btn--flight mt-6">Start your first round</a>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            @foreach($rounds as $round)
                @php
                    $totals = $round->playerTotals();
                    $me = $totals->get(Auth::id()) ?? ['strokes' => 0, 'relative' => 0, 'thru' => 0];
                    $progress = $round->holes_count ? round($me['thru'] / $round->holes_count * 100) : 0;
                    $started = $totals->filter(fn ($t) => $t['thru'] > 0);
                    $leader = $started->sortBy([['relative', 'asc'], ['thru', 'desc']])->first();
                    $live = !$round->isCompleted();
                @endphp
                <a href="{{ route('training.show', $round->id) }}" class="ds-round">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-mono text-[0.65rem] font-bold uppercase tracking-[0.16em] text-gray-400">
                                {{ $round->created_at->format('D, M j') }}
                                @if($round->course_locality) · {{ $round->course_locality }} @endif
                            </p>
                            <h2 class="mt-1 truncate font-display text-xl font-extrabold tracking-tight text-ink">{{ $round->course_name }}</h2>
                            <p class="mt-0.5 text-sm text-gray-500">{{ $round->holes_count }} holes · Par {{ $round->par() }}</p>
                        </div>
                        @if($live)
                            <span class="ds-pill shrink-0 bg-indigo-100 text-indigo-800">In progress</span>
                        @else
                            <span class="ds-pill shrink-0 bg-gray-100 text-gray-600">Final</span>
                        @endif
                    </div>

                    <div class="mt-5 flex items-end justify-between gap-4">
                        <div>
                            <p class="ds-stat__label">Your score</p>
                            <div class="mt-1 flex items-baseline gap-2">
                                @if($me['thru'])
                                    <span class="ds-rel ds-rel--lg {{ $relClass($me['relative']) }}">{{ $fmtRel($me['relative']) }}</span>
                                    <span class="text-sm font-semibold text-gray-500">{{ $me['strokes'] }} throws</span>
                                @else
                                    <span class="font-display text-2xl font-extrabold text-gray-300">—</span>
                                    <span class="text-sm font-semibold text-gray-400">Not teed off</span>
                                @endif
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="ds-stat__label">{{ $me['thru'] === $round->holes_count ? 'Finished' : 'Thru' }}</p>
                            <p class="mt-1 font-display text-2xl font-extrabold text-ink">{{ $me['thru'] }}<span class="text-base text-gray-400">/{{ $round->holes_count }}</span></p>
                        </div>
                    </div>

                    {{-- Mini scorecard: one cell per hole, coloured like the full scorecard --}}
                    <div class="ds-minicard" style="--holes: {{ max(1, $round->holes_count) }}">
                        @foreach($round->holes as $hole)
                            @php $score = $round->holeScore($hole, Auth::id()); @endphp
                            <span class="{{ $score ? $scoreClass($score, $hole->par) : '' }}" title="Hole {{ $hole->number }} · Par {{ $hole->par }}{{ $score ? ' · ' . $score : '' }}"></span>
                        @endforeach
                    </div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-100">
                        <span class="block h-full rounded-full {{ $live ? 'bg-flight' : 'bg-indigo-500' }}" style="width: {{ $progress }}%"></span>
                    </div>

                    <div class="mt-4 flex items-center justify-between gap-3 border-t border-dashed border-line pt-4">
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="flex -space-x-2">
                                @foreach($round->players->take(5) as $player)
                                    <span class="ds-avatar ring-2 ring-[var(--color-chalk)]" style="width: 2rem; height: 2rem;" title="{{ $player->name }}">
                                        @if($player->avatar)
                                            <img src="{{ asset('storage/' . $player->avatar) }}" alt="">
                                        @else
                                            <span>{{ strtoupper(substr($player->name, 0, 1)) }}</span>
                                        @endif
                                    </span>
                                @endforeach
                                @if($round->players->count() > 5)
                                    <span class="ds-avatar ring-2 ring-[var(--color-chalk)]" style="width: 2rem; height: 2rem;"><span>+{{ $round->players->count() - 5 }}</span></span>
                                @endif
                            </div>
                            <span class="truncate text-sm font-semibold text-gray-600">
                                {{ $round->players->count() === 1 ? 'Solo round' : $round->players->count() . ' players' }}
                            </span>
                        </div>
                        @if($round->players->count() > 1 && $leader)
                            <span class="flex shrink-0 items-center gap-1.5 text-xs font-semibold text-gray-500">
                                <svg class="h-4 w-4 text-flight" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0z"/></svg>
                                {{ $leader['player']->id === Auth::id() ? 'You lead' : strtok($leader['player']->name, ' ') }}
                                <span class="ds-rel {{ $relClass($leader['relative']) }}">{{ $fmtRel($leader['relative']) }}</span>
                            </span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>

        <div>
            {{ $rounds->links() }}
        </div>
    @endif
</div>
@endsection
