@extends('layouts.dashboard')

@section('title', $user->name . ' - Profile')

@section('content')
@php
    $fmtRel = fn (int $rel) => $rel === 0 ? 'E' : ($rel > 0 ? '+' . $rel : (string) $rel);
    $relClass = fn (int $rel) => $rel < 0 ? 'is-under' : ($rel > 0 ? 'is-over' : 'is-even');
    $upcoming = $registeredCompetitions->filter(fn ($registration) => $registration->competition && $registration->competition->status !== 'completed');
@endphp
<div class="mx-auto max-w-6xl space-y-6">
    <section class="ds-hero">
        <div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-end">
                <span class="ds-avatar shadow-2xl" style="width: 7.5rem; height: 7.5rem; border-radius: 2rem; font-size: 2.75rem;">
                    @if($user->avatar)
                        <img src="{{ asset('storage/' . $user->avatar) }}" alt="{{ $user->name }}" style="border-radius: 2rem;">
                    @else
                        <span style="border-radius: 1.85rem; width: calc(100% - 6px); height: calc(100% - 6px);">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                    @endif
                </span>
                <div>
                    <p class="ds-eyebrow">Player card</p>
                    <h1 class="ds-profile-name mt-3">{{ $user->name }}</h1>
                    <p class="mt-2 text-sm">{{ ucfirst($user->gender) }} · Member since {{ $user->created_at->format('M Y') }}</p>
                </div>
            </div>
            @auth
                @if(Auth::id() === $user->id && !$user->isGuest())
                    <a href="#profile-settings" class="ds-btn ds-btn--flight self-start sm:self-auto">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                        Edit profile
                    </a>
                @endif
            @endauth
        </div>
    </section>

    {{-- Rating --}}
    <section class="ds-card">
        <div class="grid gap-6 p-5 sm:p-6 lg:grid-cols-[18rem_1fr]">
            <div class="flex flex-col">
                <p class="ds-stat__label">Player rating</p>
                @if($ratingStats['rating'] !== null)
                    <div class="mt-2 flex items-end gap-3">
                        <span class="font-display text-6xl font-extrabold leading-none tracking-tight text-ink">{{ $ratingStats['rating'] }}</span>
                        @if($ratingStats['change'] !== null)
                            <span class="ds-delta {{ $ratingStats['change'] > 0 ? 'is-up' : ($ratingStats['change'] < 0 ? 'is-down' : 'is-flat') }} mb-1.5">
                                {{ $ratingStats['change'] > 0 ? '▲ +' : ($ratingStats['change'] < 0 ? '▼ ' : '') }}{{ $ratingStats['change'] }}
                            </span>
                        @endif
                    </div>
                    <p class="mt-2 text-sm text-gray-500">Average of {{ $ratingStats['counted'] }} rated {{ Str::plural('round', $ratingStats['counted']) }} · last change after the latest tournament</p>
                @else
                    <p class="mt-2 font-display text-4xl font-extrabold tracking-tight text-gray-300">Unrated</p>
                    <p class="mt-2 text-sm text-gray-500">
                        A rating appears after {{ \App\Support\RatingEngine::ROUNDS_FOR_RATING }} rated tournament rounds.
                        <strong class="text-ink">{{ $ratingStats['rounds_needed'] }} more to go.</strong>
                    </p>
                    <div class="mt-3 flex gap-1.5" aria-hidden="true">
                        @for($i = 0; $i < \App\Support\RatingEngine::ROUNDS_FOR_RATING; $i++)
                            <span class="h-2 flex-1 rounded-full {{ $i < $ratingStats['rounds'] ? 'bg-indigo-500' : 'bg-gray-200' }}"></span>
                        @endfor
                    </div>
                @endif

                <dl class="mt-6 grid grid-cols-3 gap-2 border-t border-dashed border-line pt-4">
                    <div><dt class="ds-stat__label">Rounds</dt><dd class="mt-1 font-display text-xl font-extrabold text-ink">{{ $ratingStats['rounds'] }}</dd></div>
                    <div><dt class="ds-stat__label">Best</dt><dd class="mt-1 font-display text-xl font-extrabold text-ink">{{ $ratingStats['best'] ?? '—' }}</dd></div>
                    <div><dt class="ds-stat__label">Avg round</dt><dd class="mt-1 font-display text-xl font-extrabold text-ink">{{ $ratingStats['average'] ?? '—' }}</dd></div>
                </dl>
            </div>

            <div class="min-w-0">
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm font-bold text-ink">Rating history</p>
                    <div class="flex items-center gap-4 text-xs font-semibold text-gray-600">
                        <span class="flex items-center gap-1.5"><span class="inline-block h-0.5 w-4 rounded bg-[#178454]"></span>Player rating</span>
                        <span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full bg-[#7c4dde]"></span>Round rating</span>
                    </div>
                </div>
                @if($chart->count() >= 2)
                    <div class="ds-ratingchart" id="rating-chart" role="img" aria-label="Rating history: player rating and each round rating by tournament. Values are listed in the tournament log below."></div>
                @else
                    <div class="grid h-48 place-items-center rounded-2xl border border-dashed border-gray-300 px-4 text-center text-sm text-gray-500">
                        The graph appears after two rated tournament rounds.
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- Tournament stats (public) --}}
    @include('profile.partials.stat-modes', [
        'sectionId' => 'player-stats',
        'title' => 'Tournament stats',
        'modes' => $statModes,
        'captions' => $statModes ? [
            'all' => 'Averages over all ' . $statModes['all']['rounds'] . ' rated tournament ' . Str::plural('round', $statModes['all']['rounds']) . '.',
            'last5' => 'Averages over the last ' . $statModes['last5']['rounds'] . ' ' . Str::plural('tournament', $statModes['last5']['rounds']) . '.',
            'best' => 'The single best round for each stat — open the tournament it happened in with the arrow.',
        ] : [],
        'emptyTitle' => 'No tournament stats yet',
        'emptyText' => 'Fairway hits, C1 in regulation, putting and more appear after the first finished tournament round.',
        'roundNoun' => 'tournament',
        'private' => false,
    ])

    {{-- Practice stats: private, only rendered for the profile's owner --}}
    @if($isOwner && $practice)
        @include('profile.partials.stat-modes', [
            'sectionId' => 'practice-stats',
            'title' => 'Practice stats',
            'modes' => $practice['modes'],
            'captions' => $practice['modes'] ? [
                'all' => 'Averages over all ' . $practice['finished'] . ' finished practice ' . Str::plural('round', $practice['finished']) . ' (' . $practice['holes'] . ' holes).',
                'last5' => 'Averages over your last ' . $practice['modes']['last5']['rounds'] . ' finished practice ' . Str::plural('round', $practice['modes']['last5']['rounds']) . '.',
                'best' => 'Your single best practice round for each stat — open the round with the arrow.',
            ] : [],
            'emptyTitle' => 'No finished practice rounds yet',
            'emptyText' => 'Finish every hole of a training round and its fairway hits, putting and more show up here — just for you.',
            'roundNoun' => 'practice round',
            'private' => true,
            'note' => $practice['unfinished'] ? $practice['unfinished'] . ' unfinished ' . Str::plural('round', $practice['unfinished']) . ' not counted — only rounds where every hole was holed out are included.' : null,
        ])
    @endif

    {{-- Tournament log --}}
    <section class="ds-card">
        <div class="ds-card__head">
            <div>
                <h2 class="ds-card__title">Tournament log</h2>
                <p class="mt-1 text-sm text-gray-500">Every rated tournament round, with the rating it earned and how it moved the player rating.</p>
            </div>
            <span class="font-mono text-xs text-gray-400">{{ $log->count() }}</span>
        </div>
        <div class="ds-card__body">
            @forelse($log as $round)
                <article class="ds-logrow">
                    <div class="ds-logrow__date">
                        <span class="font-display text-2xl font-extrabold leading-none text-ink">{{ $round->played_at->format('j') }}</span>
                        <span class="font-mono text-[0.58rem] font-bold uppercase tracking-wider text-gray-500">{{ $round->played_at->format('M Y') }}</span>
                    </div>
                    <div class="min-w-0">
                        @if($round->competition)
                            <a href="{{ route('competitions.view', $round->competition) }}" class="block truncate font-bold text-ink hover:text-indigo-700">{{ $round->competition->name }}</a>
                        @else
                            <span class="block truncate font-bold text-gray-500">Deleted tournament</span>
                        @endif
                        <p class="truncate text-xs text-gray-500">
                            {{ $round->course?->name ?? 'Unknown course' }} · par {{ $round->par }}
                            @if($round->competition?->course_rating_after) · par worth {{ number_format($round->competition->course_rating_after, 0) }} @endif
                        </p>
                    </div>
                    <div class="text-center">
                        <span class="ds-rel {{ $relClass($round->relativeToPar()) }}">{{ $fmtRel($round->relativeToPar()) }}</span>
                        <p class="mt-1 font-mono text-[0.65rem] text-gray-500">{{ $round->strokes }} throws</p>
                    </div>
                    <div class="text-center">
                        <p class="flex items-center justify-center gap-1.5 font-display text-2xl font-extrabold leading-none text-ink"><span class="inline-block h-2 w-2 rounded-full bg-[#7c4dde]" aria-hidden="true"></span>{{ $round->round_rating }}</p>
                        <p class="mt-1 font-mono text-[0.6rem] uppercase tracking-widest text-gray-500">Round</p>
                    </div>
                    <div class="text-right">
                        @if($round->rating_after !== null)
                            <p class="font-display text-xl font-extrabold leading-none text-ink">{{ $round->rating_after }}</p>
                            @if($round->rating_change !== null)
                                <span class="ds-delta ds-delta--sm {{ $round->rating_change > 0 ? 'is-up' : ($round->rating_change < 0 ? 'is-down' : 'is-flat') }}">{{ $round->rating_change > 0 ? '+' : '' }}{{ $round->rating_change }}</span>
                            @else
                                <span class="ds-delta ds-delta--sm is-new">New rating</span>
                            @endif
                        @else
                            <p class="font-display text-xl font-extrabold leading-none text-gray-300">—</p>
                            <span class="font-mono text-[0.62rem] text-gray-500">{{ $round->rounds_counted }}/{{ \App\Support\RatingEngine::ROUNDS_FOR_RATING }} rounds</span>
                        @endif
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-gray-300 px-4 py-10 text-center">
                    <p class="font-display text-lg font-extrabold text-ink">No rated rounds yet</p>
                    <p class="mt-1 text-sm text-gray-500">Finished tournament rounds show up here with their round rating and rating change.</p>
                </div>
            @endforelse
        </div>
    </section>

    @if($upcoming->isNotEmpty())
        <section class="ds-card">
            <div class="ds-card__head">
                <h2 class="ds-card__title">Upcoming tournaments</h2>
                <span class="font-mono text-xs text-gray-400">{{ $upcoming->count() }}</span>
            </div>
            <div class="ds-card__body">
                @foreach($upcoming as $registration)
                    <div class="ds-row">
                        <div class="min-w-0">
                            <a href="{{ route('competitions.view', $registration->competition) }}" class="block truncate text-sm font-bold text-ink hover:text-indigo-700">{{ $registration->competition->name }}</a>
                            <p class="text-xs text-gray-500">{{ $registration->competition->startsAt()->format('D, M j · H:i') }} · {{ $registration->division }}</p>
                        </div>
                        <span class="ds-pill bg-indigo-100 text-indigo-800">{{ ucfirst($registration->competition->status) }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @auth
        @if(Auth::id() === $user->id && !$user->isGuest())
            <section id="profile-settings" class="ds-card p-5 sm:p-7">
                <h2 class="ds-card__title">Profile settings</h2><p class="mt-1 text-sm text-gray-500">Update your public player details and profile picture. Your rating is earned from rated tournament rounds.</p>
                <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="mt-5 grid gap-4 sm:grid-cols-2">
                    @csrf @method('PUT')
                    <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700" for="profile-name">Name</label><input id="profile-name" name="name" value="{{ old('name', $user->name) }}" required class="mt-1 w-full rounded-lg border-gray-300"><p class="mt-1 text-xs text-red-600">@error('name'){{ $message }}@enderror</p></div>
                    <div><label class="block text-sm font-medium text-gray-700" for="profile-gender">Gender</label><select id="profile-gender" name="gender" required class="mt-1 w-full rounded-lg border-gray-300"><option value="female" @selected(old('gender', $user->gender) === 'female')>Female</option><option value="male" @selected(old('gender', $user->gender) === 'male')>Male</option></select></div>
                    <div><label class="block text-sm font-medium text-gray-700" for="profile-dob">Date of birth</label><input id="profile-dob" type="date" name="date_of_birth" max="{{ now()->toDateString() }}" value="{{ old('date_of_birth', optional($user->date_of_birth)->format('Y-m-d')) }}" required class="mt-1 w-full rounded-lg border-gray-300"><p class="mt-1 text-xs text-red-600">@error('date_of_birth'){{ $message }}@enderror</p></div>
                    <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700" for="profile-avatar">Profile picture</label><input id="profile-avatar" type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full rounded-lg border border-gray-300 p-2 text-sm"><p class="mt-1 text-xs text-gray-500">JPG, PNG, or WebP up to 5 MB.</p><p class="mt-1 text-xs text-red-600">@error('avatar'){{ $message }}@enderror</p></div>
                    <div class="sm:col-span-2"><button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Save profile</button></div>
                </form>
            </section>
        @endif
    @endauth
</div>


@if($chart->count() >= 2)
@push('scripts')
<script>
(() => {
    const points = @json($chart);
    const el = document.getElementById('rating-chart');
    const RATING = '#178454', ROUND = '#7c4dde', SURFACE = '#fffdf8', GRID = '#ece5d6', INK = '#57594f';
    const ns = 'http://www.w3.org/2000/svg';
    const make = (tag, attrs) => { const n = document.createElementNS(ns, tag); for (const k in attrs) n.setAttribute(k, attrs[k]); return n; };
    const fmtRel = r => r === 0 ? 'E' : (r > 0 ? '+' + r : String(r));

    function draw() {
        el.innerHTML = '';
        const width = el.clientWidth, height = 220;
        const pad = { top: 16, right: 44, bottom: 26, left: 40 };
        const values = points.flatMap(p => [p.round, p.rating]).filter(v => v !== null);
        const step = 25;
        let lo = Math.floor((Math.min(...values) - 10) / step) * step;
        let hi = Math.ceil((Math.max(...values) + 10) / step) * step;
        if (hi - lo < step * 2) { lo -= step; hi += step; }
        const x = i => pad.left + (points.length === 1 ? 0 : i * (width - pad.left - pad.right) / (points.length - 1));
        const y = v => pad.top + (hi - v) * (height - pad.top - pad.bottom) / (hi - lo);

        const svg = make('svg', { width, height, viewBox: `0 0 ${width} ${height}` });

        // Recessive hairline grid + clean ticks
        const tickStep = (hi - lo) / step > 6 ? step * 2 : step;
        for (let v = lo; v <= hi; v += tickStep) {
            svg.appendChild(make('line', { x1: pad.left, x2: width - pad.right, y1: y(v), y2: y(v), stroke: GRID, 'stroke-width': 1 }));
            const t = make('text', { x: pad.left - 8, y: y(v) + 4, 'text-anchor': 'end', fill: INK, 'font-size': 11, 'font-family': 'JetBrains Mono, monospace' });
            t.textContent = v;
            svg.appendChild(t);
        }

        // Player rating line (only once a rating exists)
        const rated = points.map((p, i) => [i, p.rating]).filter(([, v]) => v !== null);
        if (rated.length > 1) {
            svg.appendChild(make('path', {
                d: rated.map(([i, v], n) => `${n ? 'L' : 'M'}${x(i)} ${y(v)}`).join(''),
                fill: 'none', stroke: RATING, 'stroke-width': 2, 'stroke-linejoin': 'round', 'stroke-linecap': 'round',
            }));
        }
        if (rated.length) {
            const [li, lv] = rated[rated.length - 1];
            svg.appendChild(make('circle', { cx: x(li), cy: y(lv), r: 5, fill: RATING, stroke: SURFACE, 'stroke-width': 2 }));
            const end = make('text', { x: x(li) + 9, y: y(lv) + 4, fill: '#0c1714', 'font-size': 12, 'font-weight': 800, 'font-family': 'Bricolage Grotesque, sans-serif' });
            end.textContent = lv;
            svg.appendChild(end);
        }

        // Round ratings as dots with a surface ring
        points.forEach((p, i) => svg.appendChild(make('circle', { cx: x(i), cy: y(p.round), r: 4, fill: ROUND, stroke: SURFACE, 'stroke-width': 2 })));

        // Hover: crosshair + tooltip at the nearest tournament
        const cross = make('line', { y1: pad.top, y2: height - pad.bottom, stroke: '#a39d8e', 'stroke-width': 1, visibility: 'hidden' });
        svg.appendChild(cross);
        const hit = make('rect', { x: 0, y: 0, width, height, fill: 'transparent' });
        svg.appendChild(hit);
        el.appendChild(svg);

        const tip = document.createElement('div');
        tip.className = 'ds-charttip';
        el.appendChild(tip);

        const show = clientX => {
            const rect = svg.getBoundingClientRect();
            const px = clientX - rect.left;
            let i = Math.round((px - pad.left) / ((width - pad.left - pad.right) / Math.max(1, points.length - 1)));
            i = Math.max(0, Math.min(points.length - 1, i));
            const p = points[i];
            cross.setAttribute('x1', x(i)); cross.setAttribute('x2', x(i)); cross.setAttribute('visibility', 'visible');
            tip.innerHTML = `<strong></strong><span class="ds-charttip__date"></span>
                <span><i style="background:${ROUND}"></i>Round <b>${p.round}</b> (${fmtRel(p.score)})</span>
                <span><i class="is-line" style="background:${RATING}"></i>Rating <b>${p.rating ?? 'unrated'}</b></span>`;
            tip.querySelector('strong').textContent = p.event;
            tip.querySelector('.ds-charttip__date').textContent = p.date;
            tip.style.left = Math.min(Math.max(x(i), 90), width - 90) + 'px';
            tip.classList.add('is-visible');
        };
        hit.addEventListener('pointermove', e => show(e.clientX));
        hit.addEventListener('pointerdown', e => show(e.clientX));
        hit.addEventListener('pointerleave', () => { tip.classList.remove('is-visible'); cross.setAttribute('visibility', 'hidden'); });
    }

    draw();
    let resizeTimer;
    window.addEventListener('resize', () => { clearTimeout(resizeTimer); resizeTimer = setTimeout(draw, 120); });
})();
</script>
@endpush
@endif
@endsection
