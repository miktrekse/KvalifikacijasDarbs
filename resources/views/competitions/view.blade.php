@extends('layouts.dashboard')

@section('title', $competition->name)

@section('content')
<div class="container mx-auto px-2 sm:px-4 py-4 sm:py-8">
    <div class="mb-6">
        <a href="{{ route('competitions.index') }}" class="text-blue-600 hover:text-blue-800 flex items-center">
            <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Back to Competitions
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="px-3 py-1 rounded-full text-sm font-semibold uppercase
                    @if($competition->status == 'upcoming') bg-blue-100 text-blue-700
                    @elseif($competition->status == 'ongoing') bg-green-100 text-green-700
                    @elseif($competition->status == 'completed') bg-gray-100 text-gray-700
                    @else bg-red-100 text-red-700 @endif">
                    {{ $competition->status }}
                </span>
                @if(!$competition->is_approved)
                    <span class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-sm font-semibold">
                        Pending Approval
                    </span>
                @endif
            </div>

            <div class="flex items-center gap-2">
                @auth
                    @if(Auth::user()->isAdmin())
                        <a href="{{ route('competitions.edit', $competition->id) }}" class="inline-flex items-center px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            Edit
                        </a>
                        
                        @if(!$competition->is_approved)
                            <form method="POST" action="{{ route('competitions.approve', $competition->id) }}" class="inline">
                                @csrf
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Approve
                                </button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('competitions.unapprove', $competition->id) }}" class="inline">
                                @csrf
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition">
                                    Unapprove
                                </button>
                            </form>
                        @endif
                    @endif
                @endauth
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
        <div class="lg:col-span-2 space-y-4 sm:space-y-6">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 mb-2">{{ $competition->name }}</h1>
                
                <div class="flex flex-wrap items-center gap-4 text-gray-600 mb-4">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        {{ \Carbon\Carbon::parse($competition->event_date)->format('l, F j, Y') }}
                    </div>
                    @if($competition->location)
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        {{ $competition->location }}
                    </div>
                    @endif
                </div>

                @if($competition->description)
                    <div class="prose max-w-none text-gray-700">
                        <h3 class="text-lg font-semibold mb-2">About</h3>
                        <p>{{ $competition->description }}</p>
                    </div>
                @endif
            </div>

            @if($competition->course_name)
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Course Information</h2>
                <div class="flex items-center">
                    <svg class="w-6 h-6 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                    <div>
                        <p class="font-semibold text-gray-800">{{ $competition->course_name }}</p>
                        <p class="text-sm text-gray-500">{{ $competition->holes }} holes</p>
                    </div>
                </div>
            </div>
            @endif
        </div>

        <div class="space-y-6">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Competition Details</h2>
                
                <div class="space-y-4">
                    <div class="flex justify-between items-center pb-3 border-b border-gray-100">
                        <span class="text-gray-600">Format</span>
                        <span class="font-semibold text-gray-800">{{ ucfirst($competition->format) }}</span>
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
                                <span class="px-2 py-1 bg-purple-100 text-purple-800 text-xs rounded-full">{{ trim($division) }}</span>
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

            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Registration</h2>
                
                @if($competition->registration_deadline)
                    <div class="mb-4">
                        <p class="text-sm text-gray-500">Registration Deadline</p>
                        <p class="font-semibold text-gray-800">{{ \Carbon\Carbon::parse($competition->registration_deadline)->format('l, F j, Y g:i A') }}</p>
                    </div>
                @endif

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

            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Organizer</h2>
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
@endsection
