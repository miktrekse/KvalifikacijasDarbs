@extends('layouts.dashboard')

@section('title', 'Admin Dashboard')

@section('content')
<div class="space-y-6">
    <div class="bg-white shadow px-4 py-5 sm:rounded-lg sm:p-6">
        <div class="md:grid md:grid-cols-3 md:gap-6">
            <div class="md:col-span-1">
                <h3 class="text-lg font-medium leading-6 text-gray-900">Admin Panel</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Welcome, {{ Auth::user()->name }}! You have full control over the platform.
                </p>
            </div>
            <div class="mt-5 md:mt-0 md:col-span-2">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <div class="bg-indigo-50 overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <dt class="text-sm font-medium text-indigo-600 truncate">Total Users</dt>
                            <dd class="mt-1 text-3xl font-semibold text-gray-900">{{ $totalUsers }}</dd>
                        </div>
                    </div>
                    <div class="bg-green-50 overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <dt class="text-sm font-medium text-green-600 truncate">Total Exercises</dt>
                            <dd class="mt-1 text-3xl font-semibold text-gray-900">{{ $totalExercises }}</dd>
                        </div>
                    </div>
                    <div class="bg-yellow-50 overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <dt class="text-sm font-medium text-yellow-600 truncate">Public Exercises</dt>
                            <dd class="mt-1 text-3xl font-semibold text-gray-900">{{ $publicExercises }}</dd>
                        </div>
                    </div>
                    <div class="bg-blue-50 overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <dt class="text-sm font-medium text-blue-600 truncate">Total Comments</dt>
                            <dd class="mt-1 text-3xl font-semibold text-gray-900">{{ $totalComments }}</dd>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white shadow px-4 py-5 sm:rounded-lg sm:p-6">
        <div class="md:grid md:grid-cols-3 md:gap-6">
            <div class="md:col-span-1">
                <h3 class="text-lg font-medium leading-6 text-gray-900">Quick Actions</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Manage platform content and users.
                </p>
            </div>
            <div class="mt-5 md:mt-0 md:col-span-2">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <a href="{{ route('admin.users') }}" class="flex flex-row items-center justify-center p-3 border-2 border-indigo-100 rounded-lg hover:border-indigo-500 hover:bg-indigo-50 transition gap-3">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        <span class="text-sm font-medium text-gray-700">Manage Users</span>
                    </a>
                    <a href="{{ route('admin.exercises') }}" class="flex flex-row items-center justify-center p-3 border-2 border-green-100 rounded-lg hover:border-green-500 hover:bg-green-50 transition gap-3">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
                        </svg>
                        <span class="text-sm font-medium text-gray-700">Manage Exercises</span>
                    </a>
                    <a href="{{ route('admin.categories') }}" class="flex flex-row items-center justify-center p-3 border-2 border-yellow-100 rounded-lg hover:border-yellow-500 hover:bg-yellow-50 transition gap-3">
                        <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                        </svg>
                        <span class="text-sm font-medium text-gray-700">Categories</span>
                    </a>
                    <a href="{{ route('admin.comments') }}" class="flex flex-row items-center justify-center p-3 border-2 border-blue-100 rounded-lg hover:border-blue-500 hover:bg-blue-50 transition gap-3">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        <span class="text-sm font-medium text-gray-700">Comments</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white shadow px-4 py-5 sm:rounded-lg sm:p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium leading-6 text-gray-900">Recent Users</h3>
            <a href="{{ route('admin.users') }}" class="text-sm text-indigo-600 hover:text-indigo-800">View all</a>
        </div>
        @if($recentUsers->count() > 0)
            <ul class="divide-y divide-gray-200">
                @foreach($recentUsers as $user)
                    <li class="py-3 flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center">
                                <span class="text-indigo-600 font-semibold text-sm">{{ substr($user->name, 0, 1) }}</span>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-gray-900">{{ $user->name }}</p>
                                <p class="text-xs text-gray-500">{{ $user->email }}</p>
                            </div>
                        </div>
                        <div class="flex items-center">
                            @if($user->isAdmin())
                                <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Admin</span>
                            @else
                                <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">User</span>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-sm text-gray-500">No users yet.</p>
        @endif
    </div>

    <div class="bg-white shadow px-4 py-5 sm:rounded-lg sm:p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium leading-6 text-gray-900">Recent Exercises</h3>
            <a href="{{ route('admin.exercises') }}" class="text-sm text-indigo-600 hover:text-indigo-800">View all</a>
        </div>
        @if($recentExercises->count() > 0)
            <ul class="divide-y divide-gray-200">
                @foreach($recentExercises as $exercise)
                    <li class="py-3 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $exercise->title }}</p>
                            <p class="text-xs text-gray-500">By {{ $exercise->user->name ?? 'Unknown' }}</p>
                        </div>
                        <div class="flex items-center space-x-2">
                            @if($exercise->is_public)
                                <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Public</span>
                            @else
                                <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">Private</span>
                            @endif
                            <a href="{{ route('admin.exercises.edit', $exercise->id) }}" class="text-gray-400 hover:text-indigo-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                            </a>
                        </div>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-sm text-gray-500">No exercises yet.</p>
        @endif
    </div>
</div>
@endsection
