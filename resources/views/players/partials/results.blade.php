{{-- Player cards + pagination. Expects $players (paginator), $query and $sort. --}}
@if($players->count())
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @foreach($players as $player)
            @php
                // League-table position, only meaningful on the unfiltered "Top rated" list
                $rank = $sort === 'rating' && $query === '' && $player->rating !== null ? $players->firstItem() + $loop->index : null;
            @endphp
            <a href="{{ route('profile.show', $player) }}" @class(['ds-playercard', 'is-me' => $player->id === Auth::id()])>
                <span class="ds-avatar ds-playercard__avatar">
                    @if($player->avatar)
                        <img src="{{ asset('storage/' . $player->avatar) }}" alt="">
                    @else
                        <span>{{ strtoupper(substr($player->name, 0, 1)) }}</span>
                    @endif
                </span>
                <span class="min-w-0 flex-1">
                    <span class="flex items-center gap-1.5">
                        <span class="truncate font-display text-base font-extrabold text-ink">{{ $player->name }}</span>
                        @if($player->id === Auth::id())
                            <span class="shrink-0 text-xs font-semibold text-indigo-600">(you)</span>
                        @endif
                        @if($player->isAdmin())
                            <span class="ds-pill shrink-0 bg-flight-soft !py-0.5 text-[#b4401a]">Admin</span>
                        @elseif($player->isVerified())
                            <span class="ds-pill shrink-0 bg-indigo-50 !py-0.5 text-indigo-700">Verified</span>
                        @endif
                    </span>
                    <span class="mt-0.5 block font-mono text-[0.68rem] uppercase tracking-wider text-gray-500">
                        {{ $player->events_count }} {{ Str::plural('event', $player->events_count) }} · {{ $player->round_ratings_count }} rated {{ Str::plural('round', $player->round_ratings_count) }}
                    </span>
                </span>
                <span class="ds-playercard__rating">
                    @if($rank)
                        <small>#{{ $rank }}</small>
                    @endif
                    <b @class(['is-unrated' => $player->rating === null])>{{ $player->rating ?? '—' }}</b>
                    <small>{{ $player->rating === null ? 'Unrated' : 'Rating' }}</small>
                </span>
            </a>
        @endforeach
    </div>

    @if($players->hasPages())
        <div class="mt-6">{{ $players->links() }}</div>
    @endif
@else
    <div class="ds-card px-6 py-14 text-center">
        <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-indigo-50 text-indigo-600">
            <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
        </span>
        <p class="mt-4 font-display text-2xl font-extrabold text-ink">No players found</p>
        <p class="mt-2 text-sm text-gray-500">Nobody matches “{{ $query }}”. Check the spelling or try part of the name.</p>
    </div>
@endif
