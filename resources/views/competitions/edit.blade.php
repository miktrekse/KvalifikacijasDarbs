@extends('layouts.dashboard')

@section('title', 'Edit Competition')

@section('content')
<div class="container mx-auto px-2 sm:px-4 py-4 sm:py-8">
    <div class="max-w-full sm:max-w-3xl mx-auto">
        <div class="mb-6">
            <a href="{{ route('competitions.view', $competition->id) }}" class="text-blue-600 hover:text-blue-800 flex items-center">
                <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Back to Competition
            </a>
        </div>

        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Edit Competition</h1>
                <span class="px-3 py-1 bg-yellow-100 text-yellow-800 text-sm rounded-full">Admin Mode</span>
            </div>

            @if(session('success'))
                <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('competitions.update', $competition->id) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <h3 class="text-lg font-semibold text-yellow-800 mb-4">Admin Controls</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex items-center">
                            <input type="checkbox" name="is_approved" id="is_approved" value="1" {{ $competition->is_approved ? 'checked' : '' }}
                                class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <label for="is_approved" class="ml-2 text-sm text-gray-700">
                                Approved
                            </label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" name="is_public" id="is_public" value="1" {{ $competition->is_public ? 'checked' : '' }}
                                class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <label for="is_public" class="ml-2 text-sm text-gray-700">
                                Public
                            </label>
                        </div>
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select name="status" id="status" 
                                class="w-full rounded-lg border-gray-300 border px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="upcoming" {{ $competition->status == 'upcoming' ? 'selected' : '' }}>Upcoming</option>
                                <option value="ongoing" {{ $competition->status == 'ongoing' ? 'selected' : '' }}>Ongoing</option>
                                <option value="completed" {{ $competition->status == 'completed' ? 'selected' : '' }}>Completed</option>
                                <option value="cancelled" {{ $competition->status == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="border-b border-gray-200 pb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Basic Information</h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Competition Name *</label>
                            <input type="text" name="name" id="name" value="{{ old('name', $competition->name) }}" required
                                class="w-full rounded-lg border-gray-300 border px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('name') border-red-500 @enderror"
                                placeholder="e.g., Summer Open 2024">
                            @error('name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea name="description" id="description" rows="4"
                                class="w-full rounded-lg border-gray-300 border px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('description') border-red-500 @enderror"
                                placeholder="Describe the competition, rules, prizes, etc.">{{ old('description', $competition->description) }}</textarea>
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
                            <input type="date" name="event_date" id="event_date" value="{{ old('event_date', $competition->event_date->format('Y-m-d')) }}" required
                                class="w-full rounded-lg border-gray-300 border px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('event_date') border-red-500 @enderror">
                            @error('event_date')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="location" class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                            <input type="text" name="location" id="location" value="{{ old('location', $competition->location) }}"
                                class="w-full rounded-lg border-gray-300 border px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('location') border-red-500 @enderror"
                                placeholder="City, Country">
                            @error('location')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label for="course_name" class="block text-sm font-medium text-gray-700 mb-1">Course Name</label>
                            <input type="text" name="course_name" id="course_name" value="{{ old('course_name', $competition->course_name) }}"
                                class="w-full rounded-lg border-gray-300 border px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('course_name') border-red-500 @enderror"
                                placeholder="e.g., Rīga Botanical Garden Disc Golf Course">
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
                                <option value="stroke_play" {{ old('format', $competition->format) == 'stroke_play' ? 'selected' : '' }}>Stroke Play</option>
                                <option value="match_play" {{ old('format', $competition->format) == 'match_play' ? 'selected' : '' }}>Match Play</option>
                                <option value="stableford" {{ old('format', $competition->format) == 'stableford' ? 'selected' : '' }}>Stableford</option>
                                <option value="best_disc" {{ old('format', $competition->format) == 'best_disc' ? 'selected' : '' }}>Best Disc</option>
                                <option value="team" {{ old('format', $competition->format) == 'team' ? 'selected' : '' }}>Team Competition</option>
                            </select>
                            @error('format')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="holes" class="block text-sm font-medium text-gray-700 mb-1">Number of Holes *</label>
                            <select name="holes" id="holes" required
                                class="w-full rounded-lg border-gray-300 border px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('holes') border-red-500 @enderror">
                                <option value="9" {{ old('holes', $competition->holes) == '9' ? 'selected' : '' }}>9 Holes</option>
                                <option value="18" {{ old('holes', $competition->holes) == '18' ? 'selected' : '' }}>18 Holes</option>
                                <option value="27" {{ old('holes', $competition->holes) == '27' ? 'selected' : '' }}>27 Holes</option>
                            </select>
                            @error('holes')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label for="divisions" class="block text-sm font-medium text-gray-700 mb-1">Divisions</label>
                            <input type="text" name="divisions" id="divisions" value="{{ old('divisions', is_array($competition->divisions) ? implode(', ', $competition->divisions) : $competition->divisions) }}"
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
                                <input type="number" name="entry_fee" id="entry_fee" value="{{ old('entry_fee', $competition->entry_fee) }}" step="0.01" min="0"
                                    class="flex-1 rounded-l-lg border-gray-300 border px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('entry_fee') border-red-500 @enderror"
                                    placeholder="0.00">
                                <select name="currency" class="rounded-r-lg border-gray-300 border border-l-0 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="EUR" {{ old('currency', $competition->currency) == 'EUR' ? 'selected' : '' }}>EUR</option>
                                    <option value="USD" {{ old('currency', $competition->currency) == 'USD' ? 'selected' : '' }}>USD</option>
                                    <option value="GBP" {{ old('currency', $competition->currency) == 'GBP' ? 'selected' : '' }}>GBP</option>
                                </select>
                            </div>
                            @error('entry_fee')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="max_participants" class="block text-sm font-medium text-gray-700 mb-1">Max Participants</label>
                            <input type="number" name="max_participants" id="max_participants" value="{{ old('max_participants', $competition->max_participants) }}" min="1"
                                class="w-full rounded-lg border-gray-300 border px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('max_participants') border-red-500 @enderror"
                                placeholder="Leave empty for unlimited">
                            @error('max_participants')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="registration_deadline" class="block text-sm font-medium text-gray-700 mb-1">Registration Deadline</label>
                            <input type="datetime-local" name="registration_deadline" id="registration_deadline" value="{{ old('registration_deadline', $competition->registration_deadline ? $competition->registration_deadline->format('Y-m-d\TH:i') : '') }}"
                                class="w-full rounded-lg border-gray-300 border px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('registration_deadline') border-red-500 @enderror">
                            @error('registration_deadline')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="registration_link" class="block text-sm font-medium text-gray-700 mb-1">External Registration Link</label>
                            <input type="url" name="registration_link" id="registration_link" value="{{ old('registration_link', $competition->registration_link) }}"
                                class="w-full rounded-lg border-gray-300 border px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('registration_link') border-red-500 @enderror"
                                placeholder="https://...">
                            @error('registration_link')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-4">
                    <form method="POST" action="{{ route('competitions.destroy', $competition->id) }}" class="inline" onsubmit="return confirm('Are you sure you want to delete this competition?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                            Delete Competition
                        </button>
                    </form>
                    
                    <div class="flex items-center">
                        <a href="{{ route('competitions.view', $competition->id) }}" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition mr-3">
                            Cancel
                        </a>
                        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                            Update Competition
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
