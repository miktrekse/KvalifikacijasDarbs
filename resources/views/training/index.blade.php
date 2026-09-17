@extends('layouts.dashboard')

@section('title', 'Training Rounds')

@section('content')
<div class="container mx-auto px-2 sm:px-4 py-4 sm:py-8">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Training Rounds</h1>
            <p class="text-sm text-gray-500 mt-1">Track practice rounds shot by shot, UDisc-style.</p>
        </div>
        <a href="{{ route('training.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Start a Round
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    @if($rounds->isEmpty())
        <div class="bg-white rounded-lg shadow-sm p-10 text-center">
            <p class="text-gray-500">No training rounds yet.</p>
            <a href="{{ route('training.create') }}" class="mt-3 inline-block text-blue-600 hover:text-blue-800 font-medium">Start your first round &rarr;</a>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach($rounds as $round)
                <a href="{{ route('training.show', $round->id) }}" class="block bg-white rounded-lg shadow-sm p-5 hover:shadow-md transition">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-800 truncate">{{ $round->course_name }}</p>
                            <p class="text-sm text-gray-500">{{ $round->holes_count }} holes &middot; {{ $round->created_at->format('M j, Y') }}</p>
                        </div>
                        <span class="shrink-0 px-2.5 py-1 rounded-full text-xs font-semibold uppercase
                            @if($round->status === 'completed') bg-gray-100 text-gray-600 @else bg-green-100 text-green-700 @endif">
                            {{ $round->status === 'completed' ? 'Completed' : 'In Progress' }}
                        </span>
                    </div>
                    <div class="mt-3 flex items-center -space-x-2">
                        @foreach($round->players->take(5) as $player)
                            @if($player->avatar)
                                <img src="{{ asset('storage/' . $player->avatar) }}" alt="{{ $player->name }}" class="h-7 w-7 rounded-full object-cover ring-2 ring-white">
                            @else
                                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700 ring-2 ring-white">{{ strtoupper(substr($player->name, 0, 1)) }}</span>
                            @endif
                        @endforeach
                        @if($round->players->count() > 5)
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-600 ring-2 ring-white">+{{ $round->players->count() - 5 }}</span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $rounds->links() }}
        </div>
    @endif
</div>
@endsection
