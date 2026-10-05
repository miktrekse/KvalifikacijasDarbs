@extends('layouts.dashboard')

@section('title', 'Competitions')

@section('content')
@php
    $user = Auth::user();
    $tabs = [
        'all' => ['All', null],
        'ongoing' => ['Live', $counts['live']],
        'upcoming' => ['Upcoming', $counts['upcoming']],
        'completed' => ['Results', $counts['completed']],
    ];
    if (!$user->isGuest()) {
        $tabs['mine'] = ['My events', $counts['mine']];
    }
    if ($user->isAdmin()) {
        $tabs['cancelled'] = ['Cancelled', null];
    }
    $tabUrl = fn (string $key) => route('competitions.index', array_filter(['status' => $key === 'all' ? null : $key, 'approved' => $approved !== 'all' ? $approved : null]));
    $statusMeta = [
        'ongoing' => ['Live', 'is-live', '#22a268'],
        'upcoming' => ['Upcoming', 'is-upcoming', '#0ea5e9'],
        'completed' => ['Final', 'is-final', '#a39d8e'],
        'cancelled' => ['Cancelled', 'is-cancelled', '#b91c1c'],
    ];
@endphp
<div class="space-y-6">
    <section class="ds-hero">
        <div class="grid gap-8 lg:grid-cols-[1.2fr_1fr] lg:items-end">
            <div>
                <p class="ds-eyebrow">Tournaments</p>
                <h1 class="mt-4">Find your<br><em>next event.</em></h1>
                <p class="mt-4 max-w-md text-sm sm:text-base">Register, get drawn onto a card and keep score live — every finished round updates your rating.</p>
                @if($user->canPublish())
                    <a href="{{ route('competitions.create') }}" class="ds-btn ds-btn--flight mt-6">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                        Create competition
                    </a>
                @endif
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="ds-stat" style="--accent: #3fbd82">
                    <p class="ds-stat__label">Live now</p>
                    <p class="ds-stat__value">{{ $counts['live'] }}</p>
                    <span class="ds-stat__ring"></span>
                </div>
                <div class="ds-stat" style="--accent: #7cc9ff">
                    <p class="ds-stat__label">Upcoming</p>
                    <p class="ds-stat__value">{{ $counts['upcoming'] }}</p>
                    <span class="ds-stat__ring"></span>
                </div>
                <div class="ds-stat" style="--accent: #f26b3a">
                    <p class="ds-stat__label">You're registered</p>
                    <p class="ds-stat__value">{{ $counts['mine'] }}</p>
                    <span class="ds-stat__ring"></span>
                </div>
                <div class="ds-stat" style="--accent: #c6f36b">
                    <p class="ds-stat__label">Your rating</p>
                    <p class="ds-stat__value">{{ $user->rating ?? '—' }}</p>
                    <span class="ds-stat__ring"></span>
                </div>
            </div>
        </div>
    </section>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <nav class="ds-tabs max-w-full overflow-x-auto" aria-label="Filter competitions">
            @foreach($tabs as $key => [$label, $count])
                <a href="{{ $tabUrl($key) }}" @class(['ds-tablink', 'is-active' => $status === $key]) @if($status === $key) aria-current="page" @endif>
                    {{ $label }}
                    @if($count)
                        <span class="ds-tablink__count">{{ $count }}</span>
                    @endif
                </a>
            @endforeach
        </nav>
        @if($user->isAdmin())
            <div class="flex items-center gap-2 text-sm">
                <span class="font-mono text-[0.65rem] font-bold uppercase tracking-[0.16em] text-gray-500">Approval</span>
                @foreach(['all' => 'All', 'pending' => 'Pending' . ($counts['pending'] ? ' (' . $counts['pending'] . ')' : ''), 'approved' => 'Approved'] as $key => $label)
                    <a href="{{ route('competitions.index', array_filter(['status' => $status !== 'all' ? $status : null, 'approved' => $key !== 'all' ? $key : null])) }}"
                       @class(['ds-tabchip !py-1.5 !text-xs', 'is-active' => $approved === $key])>{{ $label }}</a>
                @endforeach
            </div>
        @endif
    </div>

    @if($competitions->count() > 0)
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach($competitions as $competition)
                @php
                    [$statusLabel, $statusClass, $accent] = $statusMeta[$competition->status] ?? ['Unknown', 'is-final', '#a39d8e'];
                    $startsAt = $competition->startsAt();
                    $isMine = in_array($competition->id, $myRegistrationIds, true);
                    $spots = $competition->max_participants;
                    $fill = $spots ? min(100, round($competition->registrations_count / $spots * 100)) : null;
                @endphp
                <a href="{{ route('competitions.view', $competition->id) }}" class="ds-event" style="--event: {{ $accent }}">
                    <div class="flex items-start gap-4">
                        <div class="ds-event__date">
                            <span class="font-mono text-[0.6rem] font-bold uppercase tracking-widest">{{ $startsAt->format('M') }}</span>
                            <span class="font-display text-3xl font-extrabold leading-none">{{ $startsAt->format('j') }}</span>
                            <span class="font-mono text-[0.6rem] uppercase tracking-wider">{{ $startsAt->format('D') }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="ds-eventstatus {{ $statusClass }}">{{ $statusLabel }}</span>
                                @if(!$competition->is_approved)
                                    <span class="ds-pill bg-amber-100 text-amber-800">Pending approval</span>
                                @endif
                                @if($isMine)
                                    <span class="ds-pill bg-flight-soft text-[#b4401a]">You're in</span>
                                @endif
                            </div>
                            <h2 class="mt-2 truncate font-display text-xl font-extrabold leading-tight tracking-tight text-ink">{{ $competition->name }}</h2>
                            <p class="mt-0.5 truncate text-sm text-gray-500">
                                {{ $startsAt->format('H:i') }}
                                @if($competition->location) · {{ $competition->location }} @endif
                            </p>
                        </div>
                    </div>

                    <dl class="ds-event__meta">
                        <div>
                            <dt>Course</dt>
                            <dd class="truncate" title="{{ $competition->course_name }}">{{ $competition->course_name ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt>Holes</dt>
                            <dd>{{ $competition->holes }}</dd>
                        </div>
                        <div>
                            <dt>Par worth</dt>
                            <dd>{{ $competition->course_rating_after ? number_format($competition->course_rating_after, 0) : ($competition->course ? number_format($competition->course->par_rating, 0) : \App\Models\Course::BASE_PAR_RATING) }}</dd>
                        </div>
                    </dl>

                    <div class="mt-3 flex flex-wrap gap-1.5">
                        <span class="ds-tag">{{ ucfirst($competition->competition_type ?? 'singles') }}</span>
                        <span class="ds-tag">{{ ucfirst(str_replace('_', ' ', $competition->format)) }}</span>
                        @foreach(array_slice($competition->divisionsArray, 0, 3) as $division)
                            <span class="ds-tag">{{ $division }}</span>
                        @endforeach
                        @if(count($competition->divisionsArray) > 3)
                            <span class="ds-tag">+{{ count($competition->divisionsArray) - 3 }}</span>
                        @endif
                        @if($competition->entry_fee > 0)
                            <span class="ds-tag">{{ number_format($competition->entry_fee, 2) }} {{ $competition->currency }}</span>
                        @endif
                    </div>

                    <div class="mt-auto border-t border-dashed border-line pt-4">
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="font-semibold text-gray-700">
                                <strong class="font-display text-lg font-extrabold text-ink">{{ $competition->registrations_count }}</strong>{{ $spots ? ' / ' . $spots : '' }}
                                {{ Str::plural('player', $competition->registrations_count) }}
                            </span>
                            <span class="shrink-0 text-xs font-semibold text-gray-500">
                                @if($competition->status === 'upcoming')
                                    {{ $startsAt->isFuture() ? 'Starts ' . $startsAt->diffForHumans() : 'Starting now' }}
                                @elseif($competition->status === 'ongoing')
                                    Round in progress
                                @elseif($competition->status === 'completed')
                                    {{ $competition->rated_at ? 'Rated' : 'Finished' }} · {{ $startsAt->diffForHumans() }}
                                @else
                                    By {{ $competition->user->name ?? 'Unknown' }}
                                @endif
                            </span>
                        </div>
                        @if($fill !== null)
                            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-100">
                                <span class="block h-full rounded-full {{ $fill >= 100 ? 'bg-flight' : 'bg-indigo-500' }}" style="width: {{ $fill }}%"></span>
                            </div>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>

        <div>{{ $competitions->links() }}</div>
    @else
        <div class="ds-card px-6 py-14 text-center">
            <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-indigo-50 text-indigo-600">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0zM17 5h3v2a3 3 0 0 1-3 3M7 5H4v2a3 3 0 0 0 3 3"/></svg>
            </span>
            <p class="mt-4 font-display text-2xl font-extrabold text-ink">
                @switch($status)
                    @case('ongoing') Nothing live right now @break
                    @case('upcoming') No upcoming events yet @break
                    @case('completed') No results yet @break
                    @case('mine') You haven't registered for anything @break
                    @default No competitions found
                @endswitch
            </p>
            <p class="mt-2 text-sm text-gray-500">{{ $status === 'mine' ? 'Browse upcoming events and sign up for one.' : 'Check back soon, or look at another tab.' }}</p>
            <div class="mt-6 flex flex-wrap justify-center gap-3">
                @if($status !== 'all')
                    <a href="{{ route('competitions.index') }}" class="ds-btn ds-btn--line">See all events</a>
                @endif
                @if($user->canPublish())
                    <a href="{{ route('competitions.create') }}" class="ds-btn ds-btn--flight">Create competition</a>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection
