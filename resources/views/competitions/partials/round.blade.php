@php
    $myRegistration = Auth::check() ? $competition->registrations->firstWhere('user_id', Auth::id()) : null;
    $myGroup = $myRegistration?->group;
    $isAdmin = Auth::check() && Auth::user()->isAdmin();
    $canEditScores = Auth::check() && $competition->canEditScores(Auth::user());
    $hasScores = $leaderboard->contains(fn ($row) => $row['thru'] > 0);
@endphp

@if(!$competition->isClosed() || $competition->hasGroups())
<section class="ds-card">
    <div class="ds-card__head">
        <div>
            <p class="ds-eyebrow">The round</p>
            <h2 class="ds-card__title mt-2">
                @if(!$competition->hasGroups())
                    Groups drawn {{ $competition->groupsDrawAt()->format('H:i') }}
                @elseif($competition->scoringIsOpen())
                    Round in progress
                @elseif($competition->isClosed())
                    Final results
                @else
                    Tee off at {{ $competition->startsAt()->format('H:i') }}
                @endif
            </h2>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if($canEditScores)
                <a href="{{ route('competitions.scorecard.edit', $competition->id) }}" class="ds-btn ds-btn--line shrink-0">Edit scores</a>
            @endif
            @if($competition->hasGroups() && ($myGroup || $isAdmin))
                <a href="{{ route('competitions.score', $competition->id) }}" class="ds-btn {{ $competition->scoringIsOpen() ? 'ds-btn--flight' : 'ds-btn--line' }} shrink-0">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                    {{ $competition->scoringIsOpen() ? 'Keep score' : ($competition->isClosed() ? 'Scorecard' : 'Your card') }}
                </a>
            @endif
        </div>
    </div>

    <div class="ds-card__body space-y-6">
        @if(!$competition->hasGroups())
            <div class="flex flex-wrap items-center gap-4 rounded-2xl bg-gray-50 p-4">
                <div class="font-display text-3xl font-extrabold tracking-tight text-ink" data-countdown="{{ $competition->groupsDrawAt()->toIso8601String() }}">–</div>
                <p class="max-w-md text-sm text-gray-500">
                    Registered players are split into cards of at least {{ \App\Support\CompetitionGrouping::MIN_GROUP_SIZE }}
                    {{ \App\Models\Competition::GROUPS_DRAWN_MINUTES_BEFORE }} minutes before tee off ({{ $competition->startsAt()->format('M j, H:i') }}).
                    Registration closes when the groups are drawn. Scoring opens at tee off.
                </p>
            </div>
        @else
            <div>
                <h3 class="mb-3 font-mono text-[0.68rem] font-bold uppercase tracking-[0.18em] text-gray-500">Cards · shotgun start</h3>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach($competition->groups as $group)
                        <div @class(['ds-group-card', 'is-mine' => $myGroup?->id === $group->id])>
                            <div class="mb-2 flex items-center justify-between">
                                <p class="font-display text-lg font-extrabold text-ink">Card {{ $group->number }}</p>
                                <span class="ds-pill bg-indigo-100 text-indigo-800">Hole {{ $group->starting_hole }}</span>
                            </div>
                            <ul class="space-y-1.5">
                                @foreach($group->registrations as $registration)
                                    <li class="flex items-center justify-between gap-2 text-sm">
                                        <a href="{{ route('profile.show', $registration->user) }}" class="truncate font-semibold text-gray-800 hover:text-indigo-700">{{ $registration->user->name }}</a>
                                        <span class="shrink-0 font-mono text-[0.68rem] text-gray-500">{{ $registration->division }}</span>
                                    </li>
                                @endforeach
                            </ul>
                            @if($isAdmin && !$competition->isClosed())
                                <a href="{{ route('competitions.score', ['id' => $competition->id, 'group' => $group->number]) }}" class="mt-3 inline-block text-xs font-bold text-indigo-700 hover:underline">Score this card →</a>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <div>
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="font-mono text-[0.68rem] font-bold uppercase tracking-[0.18em] text-gray-500">Leaderboard</h3>
                    @if($competition->scoringIsOpen())
                        <span class="ds-pill bg-indigo-100 text-indigo-800">Live</span>
                    @endif
                </div>
                @if(!$hasScores)
                    <p class="rounded-2xl border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500">Scores appear here as soon as the first hole is finished.</p>
                @else
                    <div class="grid gap-5 {{ $leaderboard->pluck('division')->unique()->count() > 1 ? 'lg:grid-cols-2' : '' }}">
                        @foreach($leaderboard->groupBy('division') as $division => $rows)
                            <div>
                                <p class="mb-1 font-display font-extrabold text-ink">{{ $division }}</p>
                                @foreach($rows as $row)
                                    @php
                                        // Positions are per division
                                        $better = $rows->filter(fn ($other) => $other['thru'] > 0 && $other['relative'] < $row['relative'])->count();
                                        $tied = $row['thru'] > 0 && $rows->filter(fn ($other) => $other['thru'] > 0 && $other['relative'] === $row['relative'])->count() > 1;
                                    @endphp
                                    <div class="ds-board-row">
                                        <span class="font-mono text-sm font-bold text-gray-500">{{ $row['thru'] ? ($tied ? 'T' : '') . ($better + 1) : '–' }}</span>
                                        <a href="{{ route('profile.show', $row['user']) }}" class="truncate text-sm font-semibold text-gray-800 hover:text-indigo-700">{{ $row['user']->name }}</a>
                                        <span class="font-mono text-[0.68rem] text-gray-500">{{ $row['thru'] ? ($row['thru'] >= $competition->holes ? 'F' : 'thru ' . $row['thru']) : 'Card ' . $row['group'] }}</span>
                                        <span class="min-w-[2.6rem] text-right font-mono text-xs font-bold text-ink" title="Round rating">
                                            @if($row['round_rating'] !== null)
                                                <span class="mr-1 inline-block h-1.5 w-1.5 rounded-full bg-[#7c4dde] align-middle" aria-hidden="true"></span>{{ $row['round_rating'] }}
                                            @endif
                                        </span>
                                        <span class="ds-rel {{ $row['relative'] < 0 ? 'is-under' : ($row['relative'] > 0 ? 'is-over' : 'is-even') }}">
                                            {{ $row['thru'] ? ($row['relative'] === 0 ? 'E' : ($row['relative'] > 0 ? '+' : '') . $row['relative']) : '–' }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>
</section>

@if(!$competition->hasGroups() && !$competition->isClosed())
    @push('scripts')
    <script>
        (() => {
            const el = document.querySelector('[data-countdown]');
            if (!el) return;
            const target = new Date(el.dataset.countdown);
            const tick = () => {
                const ms = target - new Date();
                if (ms <= 0) { el.textContent = 'Drawing…'; setTimeout(() => location.reload(), 4000); return; }
                const d = Math.floor(ms / 86400000), h = Math.floor(ms / 3600000) % 24, m = Math.floor(ms / 60000) % 60, s = Math.floor(ms / 1000) % 60;
                el.textContent = (d ? d + 'd ' : '') + String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
                setTimeout(tick, 1000);
            };
            tick();
        })();
    </script>
    @endpush
@endif
@endif
