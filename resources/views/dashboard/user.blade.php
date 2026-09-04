@extends('layouts.dashboard')

@section('title', 'Dashboard')

@section('content')
<div class="space-y-6">
    <div class="bg-white shadow px-4 py-5 sm:rounded-lg sm:p-6">
        <div class="md:grid md:grid-cols-3 md:gap-6">
            <div class="md:col-span-1">
                <h3 class="text-lg font-medium leading-6 text-gray-900">Welcome, {{ Auth::user()->name }}</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Manage your disc golf training exercises and track your progress.
                </p>
            </div>
            <div class="mt-5 md:mt-0 md:col-span-2">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="relative rounded-lg border border-gray-300 bg-white px-6 py-5 shadow-sm flex items-center space-x-3 hover:border-gray-400">
                        <div class="flex-1 min-w-0">
                            <a href="#" class="focus:outline-none">
                                <span class="absolute inset-0" aria-hidden="true"></span>
                                <p class="text-sm font-medium text-gray-900">My Exercises</p>
                                <p class="text-sm text-gray-500 truncate">{{ $myExercises->count() }} created</p>
                            </a>
                        </div>
                    </div>
                    <div class="relative rounded-lg border border-gray-300 bg-white px-6 py-5 shadow-sm flex items-center space-x-3 hover:border-gray-400">
                        <div class="flex-1 min-w-0">
                            <a href="#" class="focus:outline-none">
                                <span class="absolute inset-0" aria-hidden="true"></span>
                                <p class="text-sm font-medium text-gray-900">Saved Exercises</p>
                                <p class="text-sm text-gray-500 truncate">{{ $addedExercises->count() }} saved</p>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white shadow px-4 py-5 sm:rounded-lg sm:p-6">
        <div class="md:grid md:grid-cols-3 md:gap-6">
            <div class="md:col-span-1">
                <h3 class="text-lg font-medium leading-6 text-gray-900">Your Categories</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Browse exercises by category.
                </p>
            </div>
            <div class="mt-5 md:mt-0 md:col-span-2">
                @if($categories->count() > 0)
                    <div class="flex flex-wrap gap-2">
                        @foreach($categories as $category)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                                style="background-color: {{ $category->color }}20; color: {{ $category->color }}">
                                {{ $category->name }}
                            </span>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-500">No categories yet.</p>
                @endif
            </div>
        </div>
    </div>

    <div class="bg-white shadow px-4 py-5 sm:rounded-lg sm:p-6">
        <div class="md:grid md:grid-cols-3 md:gap-6">
            <div class="md:col-span-1">
                <h3 class="text-lg font-medium leading-6 text-gray-900">Quick Actions</h3>
            </div>
            <div class="mt-5 md:mt-0 md:col-span-2">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <a href="/exercises/create" class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        Create Exercise
                    </a>
                    <a href="/exercises/index" class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Browse Public
                    </a>
                    <a href="/exercises/saved" class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        My Saved Exercises
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection