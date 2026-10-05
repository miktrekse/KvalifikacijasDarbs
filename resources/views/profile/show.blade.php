@extends('layouts.dashboard')

@section('title', $user->name . ' - Profile')

@section('content')
<div class="mx-auto max-w-6xl space-y-6 py-4 sm:py-8">
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
                @if(Auth::id() === $user->id)
                    <a href="#profile-settings" class="ds-btn ds-btn--flight self-start sm:self-auto">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                        Edit profile
                    </a>
                @endif
            @endauth
        </div>
    </section>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="ds-stat" style="--accent: #22a268"><p class="ds-stat__label">Current rating</p><p class="ds-stat__value text-indigo-700">{{ $user->rating ?? '—' }}</p><span class="ds-stat__ring"></span></div>
        <div class="ds-stat" style="--accent: #f26b3a"><p class="ds-stat__label">Tournaments played</p><p class="ds-stat__value">{{ $playedCompetitions->count() }}</p><span class="ds-stat__ring"></span></div>
        <div class="ds-stat" style="--accent: #8b5cf6"><p class="ds-stat__label">Registered</p><p class="ds-stat__value">{{ $registeredCompetitions->count() }}</p><span class="ds-stat__ring"></span></div>
        <div class="ds-stat" style="--accent: #0ea5e9"><p class="ds-stat__label">Divisions entered</p><p class="ds-stat__value">{{ $registeredCompetitions->pluck('division')->unique()->count() }}</p><span class="ds-stat__ring"></span></div>
    </div>

    <section class="rounded-xl bg-white p-5 shadow-md sm:p-7">
        <div class="mb-5 flex items-center justify-between"><div><h2 class="text-xl font-bold text-gray-900">Tournament history</h2><p class="mt-1 text-sm text-gray-500">Registered events and rating snapshots.</p></div></div>
        @if($registeredCompetitions->isNotEmpty())
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200"><thead><tr class="text-left text-xs uppercase tracking-wide text-gray-500"><th class="px-3 py-3">Tournament</th><th class="px-3 py-3">Division</th><th class="px-3 py-3">Rating</th><th class="px-3 py-3">Status</th><th class="px-3 py-3">Registered</th></tr></thead><tbody class="divide-y divide-gray-100">
                @foreach($registeredCompetitions as $registration)
                    <tr class="text-sm"><td class="px-3 py-3 font-semibold"><a href="{{ route('competitions.view', $registration->competition) }}" class="text-indigo-700 hover:underline">{{ $registration->competition?->name ?? 'Deleted tournament' }}</a></td><td class="px-3 py-3 text-gray-600">{{ $registration->division }}</td><td class="px-3 py-3 text-gray-600">{{ $registration->rating ?? '—' }}</td><td class="px-3 py-3"><span class="rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-600">{{ $registration->competition?->status ?? 'unknown' }}</span></td><td class="px-3 py-3 text-gray-500">{{ $registration->created_at->format('M j, Y') }}</td></tr>
                @endforeach
            </tbody></table></div>
        @else
            <p class="rounded-lg bg-gray-50 px-4 py-8 text-center text-sm text-gray-500">No tournament registrations yet.</p>
        @endif
    </section>

    @auth
        @if(Auth::id() === $user->id)
            <section id="profile-settings" class="rounded-xl bg-white p-5 shadow-md sm:p-7">
                <h2 class="text-xl font-bold text-gray-900">Profile settings</h2><p class="mt-1 text-sm text-gray-500">Update your public player details and profile picture.</p>
                <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="mt-5 grid gap-4 sm:grid-cols-2">
                    @csrf @method('PUT')
                    <div><label class="block text-sm font-medium text-gray-700" for="profile-name">Name</label><input id="profile-name" name="name" value="{{ old('name', $user->name) }}" required class="mt-1 w-full rounded-lg border-gray-300"><p class="mt-1 text-xs text-red-600">@error('name'){{ $message }}@enderror</p></div>
                    <div><label class="block text-sm font-medium text-gray-700" for="profile-rating">Current rating</label><input id="profile-rating" type="number" min="0" max="1100" name="rating" value="{{ old('rating', $user->rating) }}" class="mt-1 w-full rounded-lg border-gray-300"><p class="mt-1 text-xs text-red-600">@error('rating'){{ $message }}@enderror</p></div>
                    <div><label class="block text-sm font-medium text-gray-700" for="profile-gender">Gender</label><select id="profile-gender" name="gender" required class="mt-1 w-full rounded-lg border-gray-300"><option value="female" @selected(old('gender', $user->gender) === 'female')>Female</option><option value="male" @selected(old('gender', $user->gender) === 'male')>Male</option></select></div>
                    <div><label class="block text-sm font-medium text-gray-700" for="profile-dob">Date of birth</label><input id="profile-dob" type="date" name="date_of_birth" max="{{ now()->toDateString() }}" value="{{ old('date_of_birth', optional($user->date_of_birth)->format('Y-m-d')) }}" required class="mt-1 w-full rounded-lg border-gray-300"><p class="mt-1 text-xs text-red-600">@error('date_of_birth'){{ $message }}@enderror</p></div>
                    <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700" for="profile-avatar">Profile picture</label><input id="profile-avatar" type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full rounded-lg border border-gray-300 p-2 text-sm"><p class="mt-1 text-xs text-gray-500">JPG, PNG, or WebP up to 5 MB.</p><p class="mt-1 text-xs text-red-600">@error('avatar'){{ $message }}@enderror</p></div>
                    <div class="sm:col-span-2"><button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Save profile</button></div>
                </form>
            </section>
        @endif
    @endauth
</div>
@endsection
