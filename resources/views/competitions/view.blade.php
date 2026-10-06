@extends('layouts.dashboard')

@section('title', $competition->name)

@section('content')
@auth
@php
    // Why each division is closed to this player (null = they can enter it)
    $divisionBlockers = collect($competition->divisionsArray)->mapWithKeys(fn ($division) => [
        $division => Auth::user()->divisionBlocker($division, ($competition->division_rules ?? [])[$division] ?? null),
    ]);
    $registrationDivisions = $divisionBlockers->filter(fn ($blocker) => $blocker === null)->keys();
@endphp
@endauth
@php
    $statusMeta = [
        'ongoing' => ['Live', 'is-live'],
        'upcoming' => ['Upcoming', 'is-upcoming'],
        'completed' => ['Final', 'is-final'],
        'cancelled' => ['Cancelled', 'is-cancelled'],
    ][$competition->status] ?? [ucfirst($competition->status), 'is-final'];
    $myRegistration = Auth::check() ? $competition->registrations->firstWhere('user_id', Auth::id()) : null;
    $startsAt = $competition->startsAt();
@endphp
<div class="space-y-6">
    <a href="{{ route('competitions.index') }}" class="inline-flex items-center gap-1 text-sm font-bold text-indigo-700 hover:text-indigo-900">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M15 19l-7-7 7-7"/></svg>
        Competitions
    </a>

    <section class="ds-hero">
        <div class="flex flex-wrap items-start justify-between gap-6">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="ds-eventstatus {{ $statusMeta[1] }}">{{ $statusMeta[0] }}</span>
                    @if(!$competition->is_approved)
                        <span class="ds-pill bg-amber-100 text-amber-800">Pending approval</span>
                    @endif
                    @if($myRegistration)
                        <span class="ds-pill bg-flight-soft text-[#b4401a]">You're registered · {{ $myRegistration->division }}</span>
                    @endif
                </div>
                <h1 class="mt-4 !text-[clamp(1.9rem,4.5vw,3rem)]">{{ $competition->name }}</h1>
                <p class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/></svg>
                        {{ $startsAt->format('l, F j, Y') }} · {{ $startsAt->format('H:i') }}
                    </span>
                    @if($competition->location)
                        <span class="inline-flex items-center gap-1.5">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                            {{ $competition->location }}
                        </span>
                    @endif
                </p>
            </div>

            @auth
                @if(Auth::user()->isAdmin())
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('competitions.edit', $competition->id) }}" class="ds-btn ds-btn--ghost !py-2">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                            Edit
                        </a>
                        @if(!$competition->is_approved)
                            <form method="POST" action="{{ route('competitions.approve', $competition->id) }}">
                                @csrf
                                <button type="submit" class="ds-btn ds-btn--flight !py-2">Approve</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('competitions.unapprove', $competition->id) }}">
                                @csrf
                                <button type="submit" class="ds-btn ds-btn--ghost !py-2">Unapprove</button>
                            </form>
                        @endif
                    </div>
                @endif
            @endauth
        </div>

        <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="ds-stat" style="--accent: #3fbd82">
                <p class="ds-stat__label">Players</p>
                <p class="ds-stat__value">{{ $competition->registrations->count() }}<span class="text-lg text-white/40">{{ $competition->max_participants ? '/' . $competition->max_participants : '' }}</span></p>
                <span class="ds-stat__ring"></span>
            </div>
            <div class="ds-stat" style="--accent: #7cc9ff">
                <p class="ds-stat__label">Holes</p>
                <p class="ds-stat__value">{{ $competition->holes }}</p>
                <span class="ds-stat__ring"></span>
            </div>
            <div class="ds-stat" style="--accent: #c6f36b">
                <p class="ds-stat__label">Format</p>
                <p class="ds-stat__value !text-xl !leading-tight">{{ ucfirst(str_replace('_', ' ', $competition->format)) }}</p>
                <span class="ds-stat__ring"></span>
            </div>
            <div class="ds-stat" style="--accent: #f26b3a">
                <p class="ds-stat__label">Entry</p>
                <p class="ds-stat__value !text-xl !leading-tight">{{ $competition->entry_fee > 0 ? number_format($competition->entry_fee, 2) . ' ' . $competition->currency : 'Free' }}</p>
                <span class="ds-stat__ring"></span>
            </div>
        </div>
    </section>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @if($competition->description)
                <section class="ds-card">
                    <div class="ds-card__head"><h2 class="ds-card__title">About</h2></div>
                    <div class="ds-card__body whitespace-pre-line text-sm leading-relaxed text-gray-700">{{ $competition->description }}</div>
                </section>
            @endif

            @if($competition->course_name)
            <div class="ds-card p-6">
                <h2 class="ds-card__title mb-4">Course Information</h2>
                <div class="flex items-center">
                    <svg class="w-6 h-6 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                    <div>
                        <p class="font-semibold text-gray-800">{{ $competition->course_name }}</p>
                        <p class="text-sm text-gray-500">{{ $competition->holes }} holes</p>
                    </div>
                </div>

                {{-- Course rating: what par is worth here and how this event moved it --}}
                @php
                    $parRating = $competition->course_rating_after ?? $ratingCourse?->par_rating ?? \App\Models\Course::BASE_PAR_RATING;
                    $perStroke = $ratingCourse?->points_per_stroke ?? \App\Models\Course::POINTS_PER_STROKE;
                    $moved = $competition->course_rating_before !== null ? round($competition->course_rating_after - $competition->course_rating_before, 1) : null;
                @endphp
                <div class="mt-4 grid grid-cols-3 gap-2 rounded-2xl bg-gray-50 p-3 text-center">
                    <div>
                        <p class="ds-stat__label">Par rating</p>
                        <p class="mt-1 font-display text-2xl font-extrabold text-ink">{{ number_format($parRating, 0) }}</p>
                    </div>
                    <div>
                        <p class="ds-stat__label">Per stroke</p>
                        <p class="mt-1 font-display text-2xl font-extrabold text-ink">±{{ rtrim(rtrim(number_format($perStroke, 2), '0'), '.') }}</p>
                    </div>
                    <div>
                        <p class="ds-stat__label">Rated rounds</p>
                        <p class="mt-1 font-display text-2xl font-extrabold text-ink">{{ $ratingCourse?->rated_rounds ?? 0 }}</p>
                    </div>
                </div>
                <p class="mt-2 text-xs text-gray-500">
                    A par round here is rated {{ number_format($parRating, 0) }}; each stroke under or over par is worth {{ rtrim(rtrim(number_format($perStroke, 2), '0'), '.') }} points
                    (−3 ≈ {{ round($parRating + 3 * $perStroke) }}, +2 ≈ {{ round($parRating - 2 * $perStroke) }}).
                    @if($moved !== null)
                        This tournament moved the course rating {{ $moved >= 0 ? 'up' : 'down' }} {{ abs($moved) }} points ({{ number_format($competition->course_rating_before, 1) }} → {{ number_format($competition->course_rating_after, 1) }}) based on how the rated players scored.
                    @elseif(!$ratingCourse)
                        New courses start at {{ \App\Models\Course::BASE_PAR_RATING }} and adjust slightly after every rated tournament.
                    @endif
                </p>
            </div>
            @endif

            @include('competitions.partials.round')

            <div class="ds-card p-6">
                <div class="flex items-center justify-between gap-3 mb-4">
                    <div>
                        <h2 class="ds-card__title">Registered players</h2>
                        <p class="mt-1 text-sm text-gray-500">Participants grouped by their selected division.</p>
                    </div>
                    <span class="shrink-0 px-3 py-1 bg-blue-100 text-blue-800 text-sm font-semibold rounded-full">{{ $competition->registrations->count() }} registered</span>
                </div>
                @if($competition->registrations->count())
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach($competition->registrations as $registration)
                            <div class="flex items-center justify-between gap-4 rounded-lg border border-gray-100 bg-gray-50 px-4 py-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <a href="{{ route('profile.show', $registration->user) }}" class="shrink-0">
                                        @if($registration->user->avatar)
                                            <img src="{{ asset('storage/' . $registration->user->avatar) }}" alt="{{ $registration->user->name }}" class="h-9 w-9 rounded-full object-cover">
                                        @else
                                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100 font-semibold text-blue-700">{{ strtoupper(substr($registration->user->name, 0, 1)) }}</span>
                                        @endif
                                    </a>
                                    <div class="min-w-0">
                                        <a href="{{ route('profile.show', $registration->user) }}" class="truncate font-semibold text-gray-800 hover:text-indigo-700">{{ $registration->user->name }}</a>
                                        <p class="text-sm text-gray-500">{{ $registration->division }}</p>
                                    </div>
                                </div>
                                <time class="shrink-0 text-xs text-gray-400" datetime="{{ $registration->created_at->toISOString() }}">
                                    {{ $registration->created_at->format('M j, Y') }}
                                </time>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="rounded-lg bg-gray-50 px-4 py-6 text-center text-sm text-gray-500">No one has registered yet.</p>
                @endif
            </div>
        </div>

        <div class="space-y-6">
            <div class="ds-card p-6">
                <h2 class="ds-card__title mb-4">Competition Details</h2>
                
                <div class="space-y-4">
                    <div class="flex justify-between items-center pb-3 border-b border-gray-100">
                        <span class="text-gray-600">Players</span>
                        <span class="font-semibold text-gray-800">{{ ucfirst($competition->competition_type ?? 'singles') }}</span>
                    </div>

                    <div class="flex justify-between items-center pb-3 border-b border-gray-100">
                        <span class="text-gray-600">Format</span>
                        <span class="font-semibold text-gray-800">{{ ucfirst(str_replace('_', ' ', $competition->format)) }}</span>
                    </div>
                    
                    <div class="flex justify-between items-center pb-3 border-b border-gray-100">
                        <span class="text-gray-600">Holes</span>
                        <span class="font-semibold text-gray-800">{{ $competition->holes }}</span>
                    </div>

                    @if($competition->divisions)
                    <div class="pb-3 border-b border-gray-100">
                        <span class="text-gray-600 block mb-2">Divisions</span>
                        <div class="flex flex-wrap gap-1">
                            @php
                                $divisions = is_array($competition->divisions) ? $competition->divisions : json_decode($competition->divisions, true) ?? [];
                            @endphp
                            @foreach($divisions as $division)
                                @php $rule = ($competition->division_rules ?? [])[$division] ?? null; @endphp
                                <span class="px-2 py-1 bg-purple-100 text-purple-800 text-xs rounded-full">
                                    {{ trim($division) }}
                                    @if($rule)
                                        · {{ $rule['gender'] !== 'any' ? ucfirst($rule['gender']) : 'Any' }}
                                        @if($rule['min_age'] !== null || $rule['max_age'] !== null)
                                            · age {{ $rule['min_age'] ?? 0 }}-{{ $rule['max_age'] ?? 'up' }}
                                        @endif
                                        @if($rule['min_rating'] !== null)
                                            · rating {{ $rule['min_rating'] }}+
                                        @endif
                                    @endif
                                </span>
                            @endforeach
                        </div>
                    </div>
                    @endif
                    
                    @if($competition->entry_fee > 0)
                    <div class="flex justify-between items-center pb-3 border-b border-gray-100">
                        <span class="text-gray-600">Entry Fee</span>
                        <span class="font-semibold text-gray-800">{{ number_format($competition->entry_fee, 2) }} {{ $competition->currency }}</span>
                    </div>
                    @endif

                    @if($competition->max_participants)
                    <div class="flex justify-between items-center pb-3 border-b border-gray-100">
                        <span class="text-gray-600">Max Participants</span>
                        <span class="font-semibold text-gray-800">{{ $competition->max_participants }}</span>
                    </div>
                    @endif
                </div>
            </div>

            <div class="ds-card p-6">
                <h2 class="ds-card__title mb-4">Registration</h2>
                
                @if($competition->registration_deadline)
                    <div class="mb-4">
                        <p class="text-sm text-gray-500">Registration Deadline</p>
                        <p class="font-semibold text-gray-800">{{ \Carbon\Carbon::parse($competition->registration_deadline)->format('l, F j, Y g:i A') }}</p>
                    </div>
                @endif

                @auth
                    @if(Auth::user()->isGuest())
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <input type="hidden" name="then" value="register">
                            <button type="submit" class="block w-full mb-3 text-center px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold">
                                Sign up to register
                            </button>
                        </form>
                    @elseif($competition->hasGroups())
                        <p class="mb-3 text-sm text-gray-500 text-center">Registration closed — groups have been drawn.</p>
                    @elseif($competition->status === 'upcoming' && $registrationDivisions->isNotEmpty() && (!$competition->max_participants || $competition->registrations->count() < $competition->max_participants))
                        <button type="button" id="open-registration-modal"
                            class="block w-full mb-3 text-center px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold">
                            Register for this competition
                        </button>
                    @elseif($competition->max_participants && $competition->registrations->count() >= $competition->max_participants)
                        <p class="mb-3 text-sm text-red-600 text-center">Registration is full.</p>
                    @elseif($registrationDivisions->isEmpty())
                        <p class="mb-3 text-sm text-gray-500 text-center">None of this event's divisions are open to you (gender, age or rating limits).</p>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="block w-full mb-3 text-center px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold">
                        Sign in to register
                    </a>
                @endauth

                @if($competition->registration_link)
                    <a href="{{ $competition->registration_link }}" target="_blank" rel="noopener noreferrer" 
                        class="block w-full text-center px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold">
                        Register Now
                        <svg class="w-4 h-4 inline ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                        </svg>
                    </a>
                @else
                    <p class="text-gray-500 text-center">No external registration link available.</p>
                @endif
            </div>

            <div class="ds-card p-6">
                <h2 class="ds-card__title mb-4">Organizer</h2>
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center mr-3">
                        <span class="text-lg font-semibold text-gray-600">{{ substr($competition->user->name ?? 'U', 0, 1) }}</span>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">{{ $competition->user->name ?? 'Unknown' }}</p>
                        <p class="text-sm text-gray-500">Organizer</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@auth
<div id="registration-modal" data-open="{{ $errors->has('division') || $errors->has('phone') ? 'true' : 'false' }}" class="fixed inset-0 z-50 hidden items-center justify-center bg-gray-900/50 p-4" role="dialog" aria-modal="true" aria-labelledby="registration-modal-title">
    <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 id="registration-modal-title" class="text-xl font-bold text-gray-800">Register for {{ $competition->name }}</h2>
                <p class="mt-1 text-sm text-gray-500">Choose an eligible division and provide a phone number for the organizer.</p>
            </div>
            <button type="button" id="close-registration-modal" class="text-2xl leading-none text-gray-400 hover:text-gray-700" aria-label="Close">&times;</button>
        </div>
        <form method="POST" action="{{ route('competitions.register', $competition->id) }}" class="mt-5 space-y-4">
            @csrf
            <div>
                <label for="registration-division" class="block text-sm font-medium text-gray-700">Division</label>
                <select name="division" id="registration-division" required class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Select a division</option>
                    @foreach($divisionBlockers as $division => $blocker)
                        @php $rule = ($competition->division_rules ?? [])[$division] ?? null; @endphp
                        <option value="{{ $division }}" @selected(old('division') === $division) @disabled($blocker)>{{ $division }}{{ $rule && $rule['gender'] !== 'any' ? ' · ' . ucfirst($rule['gender']) . ' only' : '' }}{{ $rule && ($rule['min_age'] !== null || $rule['max_age'] !== null) ? ' · age ' . ($rule['min_age'] ?? 0) . '-' . ($rule['max_age'] ?? 'up') : '' }}{{ $rule && $rule['min_rating'] !== null ? ' · rating ' . $rule['min_rating'] . '+' : '' }}{{ isset(\App\Models\User::DIVISION_MAX_RATINGS[$division]) ? ' · max ' . \App\Models\User::DIVISION_MAX_RATINGS[$division] : '' }}{{ $blocker ? ' — ' . $blocker : '' }}</option>
                    @endforeach
                </select>
                @if($registrationDivisions->isEmpty())
                    <p class="mt-2 text-sm text-red-600">None of this event's divisions are open to you.</p>
                @endif
                @error('division')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="registration-phone" class="block text-sm font-medium text-gray-700">Phone number</label>
                <input type="tel" name="phone" id="registration-phone" value="{{ old('phone') }}" required placeholder="+371 2000 0000" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:ring-blue-500">
                @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="flex items-center justify-between gap-3 rounded-xl bg-gray-50 px-4 py-3">
                <div>
                    <p class="text-sm font-medium text-gray-700">Player rating</p>
                    <p class="text-xs text-gray-500">Calculated from your rated tournament rounds.</p>
                </div>
                <span class="font-display text-2xl font-extrabold text-ink">{{ Auth::user()->rating ?? 'Unrated' }}</span>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" id="cancel-registration-modal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Confirm registration</button>
            </div>
        </form>
    </div>
</div>
@endauth
@endsection

@push('scripts')
<script>
    const registrationModal = document.getElementById('registration-modal');
    const openRegistrationModal = document.getElementById('open-registration-modal');
    const closeRegistrationModal = () => registrationModal?.classList.replace('flex', 'hidden');
    const showRegistrationModal = () => registrationModal?.classList.replace('hidden', 'flex');
    openRegistrationModal?.addEventListener('click', showRegistrationModal);
    document.getElementById('close-registration-modal')?.addEventListener('click', closeRegistrationModal);
    document.getElementById('cancel-registration-modal')?.addEventListener('click', closeRegistrationModal);
    registrationModal?.addEventListener('click', event => {
        if (event.target === registrationModal) closeRegistrationModal();
    });
    if (registrationModal?.dataset.open === 'true') {
        showRegistrationModal();
    }
</script>
@endpush
