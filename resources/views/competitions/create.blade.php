@extends('layouts.dashboard')

@section('title', 'Create Competition')

@section('content')
<div class="container mx-auto px-2 sm:px-4 py-4 sm:py-8">
    <div class="max-w-full sm:max-w-3xl mx-auto">
        <div class="mb-6">
            <a href="{{ route('competitions.index') }}" class="text-blue-600 hover:text-blue-800 flex items-center">
                <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Back to Competitions
            </a>
        </div>

        <div class="bg-white rounded-lg shadow-lg p-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-6">Create New Competition</h1>

            @if(session('success'))
                <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('competitions.store') }}" class="space-y-6">
                @csrf

                <div class="border-b border-gray-200 pb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Basic Information</h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Competition Name *</label>
                            <input type="text" name="name" id="name" value="{{ old('name') }}" required
                                class="w-full rounded-lg border-gray-300 border px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('name') border-red-500 @enderror"
                                placeholder="Summer Open 2026">
                            @error('name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea name="description" id="description" rows="4"
                                class="w-full rounded-lg border-gray-300 border px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('description') border-red-500 @enderror"
                                placeholder="Description for competition">{{ old('description') }}</textarea>
                            @error('description')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="border-b border-gray-200 pb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Date & Location</h3>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                        <div>
                            <label for="event_date" class="block text-sm font-medium text-gray-700 mb-1">Event Date *</label>
                            <input type="date" name="event_date" id="event_date" value="{{ old('event_date') }}" required
                                class="w-full rounded-lg border-gray-300 border px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('event_date') border-red-500 @enderror">
                            @error('event_date')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="location" class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                            <input type="text" name="location" id="location" value="{{ old('location') }}"
                                class="w-full rounded-lg border-gray-300 border px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('location') border-red-500 @enderror"
                                placeholder="City, Country">
                            @error('location')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label for="course_name" class="block text-sm font-medium text-gray-700 mb-1">Course Name</label>
                            <input type="text" name="course_name" id="course_name" value="{{ old('course_name') }}"
                                class="w-full rounded-lg border-gray-300 border px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('course_name') border-red-500 @enderror"
                                placeholder="Mežaparks Disc Golf Course">
                            @error('course_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="border-b border-gray-200 pb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Format & Rules</h3>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                        <div>
                            <label for="format" class="block text-sm font-medium text-gray-700 mb-1">Format *</label>
                            <select name="format" id="format" required
                                class="w-full rounded-lg border-gray-300 border px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('format') border-red-500 @enderror">
                                <option value="stroke_play" {{ old('format') == 'stroke_play' ? 'selected' : '' }}>Stroke Play</option>
                                <option value="match_play" {{ old('format') == 'match_play' ? 'selected' : '' }}>Match Play</option>
                                <option value="stableford" {{ old('format') == 'stableford' ? 'selected' : '' }}>Stableford</option>
                                <option value="best_disc" {{ old('format') == 'best_disc' ? 'selected' : '' }}>Best Disc</option>
                                <option value="team" {{ old('format') == 'team' ? 'selected' : '' }}>Team Competition</option>
                            </select>
                            @error('format')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="holes" class="block text-sm font-medium text-gray-700 mb-1">Number of Holes *</label>
                            <select name="holes" id="holes" required
                                class="w-full rounded-lg border-gray-300 border px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('holes') border-red-500 @enderror">
                                <option value="9" {{ old('holes') == '9' ? 'selected' : '' }}>9 Holes</option>
                                <option value="18" {{ old('holes', '18') == '18' ? 'selected' : '' }}>18 Holes</option>
                                <option value="27" {{ old('holes') == '27' ? 'selected' : '' }}>27 Holes</option>
                            </select>
                            @error('holes')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label for="divisions" class="block text-sm font-medium text-gray-700 mb-1">Divisions</label>
                            <input type="text" name="divisions" id="divisions" value="{{ old('divisions') }}"
                                class="w-full rounded-lg border-gray-300 border px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('divisions') border-red-500 @enderror"
                                placeholder="e.g., Open, Amateur, Women, Juniors (comma separated)">
                            @error('divisions')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="border-b border-gray-200 pb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Registration Details</h3>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                        <div>
                            <label for="entry_fee" class="block text-sm font-medium text-gray-700 mb-1">Entry Fee</label>
                            <div class="flex">
                                <input type="number" name="entry_fee" id="entry_fee" value="{{ old('entry_fee', 0) }}" step="0.01" min="0"
                                    class="flex-1 rounded-l-lg border-gray-300 border px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('entry_fee') border-red-500 @enderror"
                                    placeholder="0.00">
                                <select name="currency" class="rounded-r-lg border-gray-300 border border-l-0 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="EUR" {{ old('currency') == 'EUR' ? 'selected' : '' }}>EUR</option>
                                    <option value="USD" {{ old('currency') == 'USD' ? 'selected' : '' }}>USD</option>
                                    <option value="GBP" {{ old('currency') == 'GBP' ? 'selected' : '' }}>GBP</option>
                                </select>
                            </div>
                            @error('entry_fee')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="max_participants" class="block text-sm font-medium text-gray-700 mb-1">Max Participants</label>
                            <input type="number" name="max_participants" id="max_participants" value="{{ old('max_participants') }}" min="1"
                                class="w-full rounded-lg border-gray-300 border px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('max_participants') border-red-500 @enderror"
                                placeholder="Leave empty for unlimited">
                            @error('max_participants')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="registration_deadline" class="block text-sm font-medium text-gray-700 mb-1">Registration Deadline</label>
                            <input type="datetime-local" name="registration_deadline" id="registration_deadline" value="{{ old('registration_deadline') }}"
                                class="w-full rounded-lg border-gray-300 border px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('registration_deadline') border-red-500 @enderror">
                            @error('registration_deadline')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="registration_link" class="block text-sm font-medium text-gray-700 mb-1">External Registration Link</label>
                            <input type="url" name="registration_link" id="registration_link" value="{{ old('registration_link') }}"
                                class="w-full rounded-lg border-gray-300 border px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('registration_link') border-red-500 @enderror"
                                placeholder="https://...">
                            @error('registration_link')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Visibility</h3>
                    
                    <div class="flex items-center">
                        <input type="checkbox" name="is_public" id="is_public" value="1" {{ old('is_public', '1') ? 'checked' : '' }}
                            class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                        <label for="is_public" class="ml-2 text-sm text-gray-700">
                            Make this competition public
                        </label>
                    </div>
                    <p class="mt-1 text-sm text-gray-500 ml-6">
                        Uncheck to keep it private (only accessible via direct link)
                    </p>
                </div>

                <div class="flex items-center justify-end pt-4">
                    <a href="{{ route('competitions.index') }}" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition mr-3">
                        Cancel
                    </a>
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        Create Competition
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
