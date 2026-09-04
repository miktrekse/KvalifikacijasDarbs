@extends('layouts.dashboard')

@section('title', 'My Saved Exercises')

@section('content')
<div class="container mx-auto px-2 sm:px-4 py-4 sm:py-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">My Saved Exercises</h1>
            <p class="text-gray-600 mt-1">Exercises you've saved from the public library</p>
        </div>
        <a href="{{ route('exercises.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
            Browse More
        </a>
    </div>

    @if($exercises->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($exercises as $exercise)
                <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition p-6 border border-gray-100">
                    <div class="flex items-start justify-between mb-3">
                        <h2 class="text-xl font-semibold text-gray-800">{{ $exercise->title }}</h2>
                        @if($exercise->category)
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">{{ $exercise->category->name }}</span>
                        @endif
                    </div>
                    
                    <p class="text-gray-600 mb-4 line-clamp-3">{{ Str::limit($exercise->description, 120) }}</p>
                    
                    <div class="flex items-center justify-between text-sm text-gray-500 mb-4">
                        <span class="flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            {{ $exercise->duration_minutes ?? 'N/A' }} min
                        </span>
                        <span class="flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                            {{ ucfirst($exercise->difficulty ?? 'Any') }}
                        </span>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <a href="{{ route('exercises.view', $exercise->id) }}" class="flex-1 text-center px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition mr-2">
                            View Details
                        </a>
                        <form action="{{ route('exercises.toggleSave') }}" method="POST">
                            @csrf
                            <input type="hidden" name="exercise_id" value="{{ $exercise->id }}">
                            <button type="submit" class="p-2 text-yellow-500 hover:bg-yellow-50 rounded-lg transition" title="Remove from saved">
                                <svg class="w-5 h-5 fill-current" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                                </svg>
                            </button>
                        </form>
                    </div>

                    <div class="mt-3 pt-3 border-t border-gray-100 text-xs text-gray-500">
                        Saved {{ $exercise->pivot->created_at->diffForHumans() }}
                    </div>
                </div>
            @endforeach
        </div>
        
        <div class="mt-8">
            {{ $exercises->links() }}
        </div>
    @else
        <div class="text-center py-16 bg-white rounded-lg shadow-md">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path>
            </svg>
            <h3 class="mt-4 text-lg font-medium text-gray-900">No saved exercises yet</h3>
            <p class="mt-2 text-gray-500">Browse the public exercises and save the ones you want to practice!</p>
            <div class="mt-6">
                <a href="{{ route('exercises.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    Browse Exercises
                </a>
            </div>
        </div>
    @endif
</div>
@endsection
