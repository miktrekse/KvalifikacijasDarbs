@extends('layouts.dashboard')

@section('title', $user->name . ' - Profile')

@section('content')
<div class="mx-auto max-w-6xl space-y-6 py-4 sm:py-8">
    <section class="overflow-hidden rounded-xl bg-white shadow-md">
        <div class="h-32 bg-gradient-to-r from-indigo-700 via-indigo-600 to-cyan-600"></div>
        <div class="px-5 pb-6 sm:px-8">
            <div class="-mt-14 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="flex items-end gap-4">
                    @if($user->avatar)
                        <img src="{{ Storage::disk('public')->url($user->avatar) }}" alt="{{ $user->name }}" class="h-28 w-28 rounded-2xl border-4 border-white object-cover shadow-lg">
                    @else
                        <div class="flex h-28 w-28 items-center justify-center rounded-2xl border-4 border-white bg-indigo-100 text-4xl font-bold text-indigo-700 shadow-lg">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
                    @endif
                    <div class="pb-1">
                        <h1 class="text-2xl font-bold text-gray-900">{{ $user->name }}</h1>
                        <p class="text-sm text-gray-500">{{ ucfirst($user->gender) }} · Member since {{ $user->created_at->format('M Y') }}</p>
                    </div>
                </div>
                @auth
                    @if(Auth::id() === $user->id)
                        <a href="#profile-settings" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Edit profile</a>
                    @endif
                @endauth
            </div>
        </div>
    </section>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-xl bg-white p-5 shadow-sm"><p class="text-sm text-gray-500">Current rating</p><p class="mt-1 text-3xl font-bold text-indigo-700">{{ $user->rating ?? '—' }}</p></div>
        <div class="rounded-xl bg-white p-5 shadow-sm"><p class="text-sm text-gray-500">Tournaments played</p><p class="mt-1 text-3xl font-bold text-gray-900">{{ $playedCompetitions->count() }}</p></div>
        <div class="rounded-xl bg-white p-5 shadow-sm"><p class="text-sm text-gray-500">Tournaments registered</p><p class="mt-1 text-3xl font-bold text-gray-900">{{ $registeredCompetitions->count() }}</p></div>
        <div class="rounded-xl bg-white p-5 shadow-sm"><p class="text-sm text-gray-500">Divisions entered</p><p class="mt-1 text-3xl font-bold text-gray-900">{{ $registeredCompetitions->pluck('division')->unique()->count() }}</p></div>
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
