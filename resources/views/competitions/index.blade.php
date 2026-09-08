@extends('layouts.dashboard')

@section('title', 'Competitions')

@section('content')
<div class="container mx-auto px-2 sm:px-4 py-3 sm:py-5">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-4 sm:mb-6 gap-3">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">DiscStats Competitions</h1>
            <p class="text-gray-600 mt-1 text-sm sm:text-base">Find and register for tournaments</p>
        </div>
        <a href="{{ route('competitions.create') }}" class="inline-flex items-center px-3 py-2 sm:px-4 sm:py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm sm:text-base w-full sm:w-auto justify-center">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Create
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-sm p-3 sm:p-4 mb-4 sm:mb-6">
        <form method="GET" class="flex flex-wrap gap-4 items-center">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="all">All Statuses</option>
                    <option value="upcoming" {{ request('status') == 'upcoming' ? 'selected' : '' }}>Upcoming</option>
                    <option value="ongoing" {{ request('status') == 'ongoing' ? 'selected' : '' }}>Ongoing</option>
                    <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                </select>
            </div>
            @auth
                @if(Auth::user()->isAdmin())
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Approval</label>
                    <select name="approved" class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="all">All</option>
                        <option value="approved" {{ request('approved') == 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="pending" {{ request('approved') == 'pending' ? 'selected' : '' }}>Pending Approval</option>
                    </select>
                </div>
                @endif
            @endauth
            <div class="flex items-end">
                <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-900 transition">
                    Filter
                </button>
            </div>
        </form>
    </div>

    @if($competitions->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($competitions as $competition)
                <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition border border-gray-100 overflow-hidden">
                    <div class="px-4 py-2 flex items-center justify-between 
                        @if($competition->status == 'upcoming') bg-blue-50 
                        @elseif($competition->status == 'ongoing') bg-green-50 
                        @elseif($competition->status == 'completed') bg-gray-50 
                        @else bg-red-50 @endif">
                        <span class="text-xs font-semibold uppercase tracking-wide 
                            @if($competition->status == 'upcoming') text-blue-700 
                            @elseif($competition->status == 'ongoing') text-green-700 
                            @elseif($competition->status == 'completed') text-gray-700 
                            @else text-red-700 @endif">
                            {{ $competition->status }}
                        </span>
                        @if(!$competition->is_approved)
                            <span class="text-xs font-semibold text-yellow-700 uppercase">Pending Approval</span>
                        @endif
                    </div>
                    
                    <div class="p-5">
                        <h2 class="text-xl font-bold text-gray-800 mb-2">{{ $competition->name }}</h2>
                        
                        <div class="space-y-2 text-sm text-gray-600 mb-4">
                            <div class="flex items-center">
                                <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                {{ \Carbon\Carbon::parse($competition->event_date)->format('l, F j, Y') }}
                            </div>
                            @if($competition->location)
                            <div class="flex items-center">
                                <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                {{ $competition->location }}
                            </div>
                            @endif
                            @if($competition->course_name)
                            <div class="flex items-center">
                                <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                                {{ $competition->course_name }}
                            </div>
                            @endif
                        </div>

                        <div class="flex flex-wrap gap-2 mb-4">
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                                {{ ucfirst($competition->competition_type ?? 'singles') }}
                            </span>
                            <span class="px-2 py-1 bg-purple-100 text-purple-800 text-xs rounded-full">
                                {{ ucfirst(str_replace('_', ' ', $competition->format)) }}
                            </span>
                            <span class="px-2 py-1 bg-orange-100 text-orange-800 text-xs rounded-full">
                                {{ $competition->holes }} holes
                            </span>
                            @if($competition->entry_fee > 0)
                            <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                                {{ number_format($competition->entry_fee, 2) }} {{ $competition->currency }}
                            </span>
                            @endif
                        </div>

                        <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                            <a href="{{ route('competitions.view', $competition->id) }}" class="flex-1 text-center px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition mr-2">
                                View Details
                            </a>
                        </div>

                        <div class="mt-3 pt-3 border-t border-gray-100 text-xs text-gray-500">
                            By {{ $competition->user->name ?? 'Unknown' }} &bull; 
                            {{ $competition->created_at->diffForHumans() }}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        
        <div class="mt-8">
            {{ $competitions->links() }}
        </div>
    @else
        <div class="text-center py-12 bg-white rounded-lg shadow-md">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
            </svg>
            <h3 class="mt-4 text-lg font-medium text-gray-900">No competitions found</h3>
            <p class="mt-2 text-gray-500">Get started by creating a new competition.</p>
            <div class="mt-6">
                <a href="{{ route('competitions.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    Create Competition
                </a>
            </div>
        </div>
    @endif
</div>
@endsection
