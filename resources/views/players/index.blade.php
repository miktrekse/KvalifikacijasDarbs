@extends('layouts.dashboard')

@section('title', 'Players')

@section('content')
<div class="space-y-6">
    <section class="ds-hero">
        <div class="grid gap-8 lg:grid-cols-[1.2fr_1fr] lg:items-end">
            <div>
                <p class="ds-eyebrow">Players</p>
                <h1 class="mt-4">Who's out<br><em>on the course.</em></h1>
                <p class="mt-4 max-w-md text-sm sm:text-base">Look up anyone on DiscStats — their rating, tournament history and stats.</p>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div class="ds-stat" style="--accent: #3fbd82">
                    <p class="ds-stat__label">Players</p>
                    <p class="ds-stat__value">{{ $stats['players'] }}</p>
                    <span class="ds-stat__ring"></span>
                </div>
                <div class="ds-stat" style="--accent: #7cc9ff">
                    <p class="ds-stat__label">Rated</p>
                    <p class="ds-stat__value">{{ $stats['rated'] }}</p>
                    <span class="ds-stat__ring"></span>
                </div>
                <div class="ds-stat" style="--accent: #f26b3a">
                    <p class="ds-stat__label">Top rating</p>
                    <p class="ds-stat__value">{{ $stats['top'] ?? '—' }}</p>
                    <span class="ds-stat__ring"></span>
                </div>
            </div>
        </div>
    </section>

    <form id="player-search" method="GET" action="{{ route('players.index') }}" class="flex flex-wrap items-center gap-3" role="search">
        <label class="ds-filters__search min-w-0 flex-1 basis-72">
            <svg class="h-4 w-4 shrink-0 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
            <input type="search" name="q" value="{{ $query }}" placeholder="Search players by name…" aria-label="Search players" autocomplete="off" @if($query !== '') autofocus @endif>
            <button type="submit" class="ds-btn ds-btn--flight !py-2">Search</button>
        </label>
        <nav class="ds-tabs max-w-full overflow-x-auto" aria-label="Sort players">
            @foreach($sorts as $key => $label)
                <button type="submit" name="sort" value="{{ $key }}" @class(['ds-tablink', 'is-active' => $sort === $key])>{{ $label }}</button>
            @endforeach
        </nav>
    </form>

    <div id="player-results">
        @include('players.partials.results')
    </div>
</div>
@endsection

@push('scripts')
<script>
    (() => {
        const form = document.getElementById('player-search');
        const input = form.querySelector('input[name="q"]');
        const results = document.getElementById('player-results');
        let sort = @json($sort);
        let timer = null;
        let request = null;

        // Results update as you type; the URL keeps the search so it can be shared or reloaded
        async function load() {
            const params = new URLSearchParams();
            if (input.value.trim()) params.set('q', input.value.trim());
            if (sort !== 'rating') params.set('sort', sort);
            const url = `${form.action}${params.toString() ? '?' + params : ''}`;

            request?.abort();
            request = new AbortController();
            try {
                const response = await fetch(url, { headers: { 'X-Partial': 'results' }, signal: request.signal });
                if (!response.ok) return;
                results.innerHTML = await response.text();
                history.replaceState(null, '', url);
            } catch (e) {}
        }

        input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(load, 250); });
        form.querySelectorAll('button[name="sort"]').forEach(button => button.addEventListener('click', event => {
            event.preventDefault();
            sort = button.value;
            form.querySelectorAll('button[name="sort"]').forEach(b => b.classList.toggle('is-active', b === button));
            load();
        }));
        form.addEventListener('submit', event => { event.preventDefault(); clearTimeout(timer); load(); });
        if (input.value) input.setSelectionRange(input.value.length, input.value.length);
    })();
</script>
@endpush
