{{--
    Stats card with three views: all-time averages / last 5 / best ever.
    Used for tournament stats (public) and practice stats (owner only).

    Expects: $sectionId, $title, $modes (RoundStats::summarise() or null), $captions [all, last5, best],
    $emptyTitle, $emptyText, $roundNoun ("tournament" / "practice round"), $private (bool), $note (optional)
--}}
@php
    $fmtRelAvg = fn ($v) => $v === null ? '—' : ($v == 0 ? 'E' : ($v > 0 ? '+' : '') . (floor($v) == $v ? (int) $v : number_format($v, 1)));
@endphp
<section class="ds-card" id="{{ $sectionId }}" data-stat-section>
    <div class="ds-card__head flex-wrap">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="ds-card__title">{{ $title }}</h2>
                @if($private)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-1 text-[0.7rem] font-bold text-gray-600" title="Other players can&#039;t see this section on your profile">
                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                        Only you can see this
                    </span>
                @endif
            </div>
            <p class="mt-1 text-sm text-gray-500" data-stats-caption></p>
        </div>
        @if($modes)
            <div class="ds-tabs" role="group" aria-label="{{ $title }} view">
                <button type="button" data-stats-mode="all" class="is-active" aria-pressed="true">All-time average</button>
                <button type="button" data-stats-mode="last5" aria-pressed="false">Last 5</button>
                <button type="button" data-stats-mode="best" aria-pressed="false">Best ever</button>
            </div>
        @endif
    </div>
    <div class="ds-card__body">
        @if(!$modes)
            <div class="rounded-2xl border border-dashed border-gray-300 px-4 py-10 text-center">
                <p class="font-display text-lg font-extrabold text-ink">{{ $emptyTitle }}</p>
                <p class="mt-1 text-sm text-gray-500">{{ $emptyText }}</p>
            </div>
        @else
            @foreach(['all', 'last5', 'best'] as $mode)
                <div class="ds-statgrid {{ $mode === 'all' ? '' : 'hidden' }}" data-stats-panel="{{ $mode }}" data-caption="{{ $captions[$mode] }}">
                    @foreach($modes['keys'] as $key)
                        @php
                            [$label, $kind] = \App\Support\RoundStats::STATS[$key];
                            $stat = $modes[$mode]['stats'][$key];
                        @endphp
                        <div class="ds-stattile">
                            <div class="flex items-start justify-between gap-2">
                                <p class="ds-stat__label">{{ $label }}</p>
                                @if($mode === 'best' && !empty($stat['url']))
                                    <a href="{{ $stat['url'] }}" class="ds-stattile__jump" title="Open {{ $stat['title'] }}" aria-label="Open the {{ $roundNoun }}: {{ $stat['title'] }}">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M8 7h9v9"/></svg>
                                    </a>
                                @endif
                            </div>

                            @if($kind === 'percent')
                                <div class="mt-3 flex items-center gap-3">
                                    <div class="ds-ring ds-ring--sm" style="--p: {{ $stat['value'] ?? 0 }}; --c: #178454"><span>{{ $stat['value'] !== null ? $stat['value'] . '%' : '—' }}</span></div>
                                    <p class="font-mono text-xs text-gray-500">{{ $stat['value'] !== null ? $stat['detail'] : 'no attempts' }}</p>
                                </div>
                            @elseif($kind === 'metres')
                                <p class="mt-3 font-display text-3xl font-extrabold leading-none tracking-tight text-ink">{{ $stat['value'] ?? '—' }}<span class="text-lg text-gray-400">{{ $stat['value'] ? ' m' : '' }}</span></p>
                                <p class="mt-1 font-mono text-xs text-gray-500">{{ $stat['value'] ? 'made from outside C2' : 'no throw-ins logged' }}</p>
                            @elseif($kind === 'relative')
                                <p class="mt-3"><span class="ds-rel ds-rel--lg {{ $stat['value'] === null ? 'is-even' : ($stat['value'] < 0 ? 'is-under' : ($stat['value'] > 0 ? 'is-over' : 'is-even')) }}">{{ $fmtRelAvg($stat['value']) }}</span></p>
                                <p class="mt-1 font-mono text-xs text-gray-500">{{ $mode === 'best' ? 'lowest round' : 'per round' }}</p>
                            @else
                                <p class="mt-3 font-display text-3xl font-extrabold leading-none tracking-tight text-ink">{{ $stat['value'] ?? '—' }}</p>
                                <p class="mt-1 font-mono text-xs text-gray-500">{{ $mode === 'best' ? ($key === 'ob' ? 'fewest in a round' : 'in one round') : ($key === 'rating' ? 'average' : 'per round') }}</p>
                            @endif

                            @if($mode === 'best' && !empty($stat['title']))
                                <p class="mt-3 truncate border-t border-dashed border-line pt-2 text-xs text-gray-500" title="{{ $stat['title'] }}">{{ $stat['title'] }} · {{ $stat['date'] }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach
        @endif
        @if(!empty($note))
            <p class="mt-4 text-xs text-gray-500">{{ $note }}</p>
        @endif
    </div>
</section>

@pushOnce('scripts')
<script>
// Each stats card switches between all-time averages, last 5 and best ever on its own
document.querySelectorAll('[data-stat-section]').forEach(section => {
    const caption = section.querySelector('[data-stats-caption]');
    const show = mode => {
        section.querySelectorAll('[data-stats-mode]').forEach(b => {
            b.classList.toggle('is-active', b.dataset.statsMode === mode);
            b.setAttribute('aria-pressed', b.dataset.statsMode === mode);
        });
        section.querySelectorAll('[data-stats-panel]').forEach(p => {
            p.classList.toggle('hidden', p.dataset.statsPanel !== mode);
            if (p.dataset.statsPanel === mode) caption.textContent = p.dataset.caption;
        });
    };
    section.querySelectorAll('[data-stats-mode]').forEach(b => b.addEventListener('click', () => show(b.dataset.statsMode)));
    if (section.querySelector('[data-stats-panel]')) show('all');
});
</script>
@endPushOnce
