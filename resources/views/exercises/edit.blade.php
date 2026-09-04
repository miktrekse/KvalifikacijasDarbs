@extends('layouts.dashboard')

@section('title', 'Edit Exercise')

@section('content')
<div class="container mx-auto px-2 sm:px-4 py-4 sm:py-8">
    <div class="mb-6">
        <a href="{{ route('exercises.view', $exercise->id) }}" class="inline-flex items-center text-indigo-600 hover:text-indigo-800">
            <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Back to Exercise
        </a>
    </div>

    <h1 class="text-3xl font-bold text-gray-800 mb-6">Edit Exercise</h1>
    
    <form action="{{ route('exercises.update', $exercise->id) }}" method="POST" class="bg-white shadow-md rounded-lg p-6">
        @csrf
        @method('PUT')
        
        <div class="mb-6">
            <label for="title" class="block text-sm font-medium text-gray-700 mb-2">
                Exercise Title <span class="text-red-500">*</span>
            </label>
            <input type="text" name="title" id="title" required
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                placeholder="e.g., Perfect Your Putt"
                value="{{ old('title', $exercise->title) }}">
            @error('title')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label for="category_id" class="block text-sm font-medium text-gray-700 mb-2">
                    Category
                </label>
                <select name="category_id" id="category_id"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">Select a category</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ old('category_id', $exercise->category_id) == $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="difficulty" class="block text-sm font-medium text-gray-700 mb-2">
                    Difficulty Level <span class="text-red-500">*</span>
                </label>
                <select name="difficulty" id="difficulty" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="beginner" {{ old('difficulty', $exercise->difficulty) == 'beginner' ? 'selected' : '' }}>Beginner</option>
                    <option value="intermediate" {{ old('difficulty', $exercise->difficulty) == 'intermediate' ? 'selected' : '' }}>Intermediate</option>
                    <option value="advanced" {{ old('difficulty', $exercise->difficulty) == 'advanced' ? 'selected' : '' }}>Advanced</option>
                    <option value="expert" {{ old('difficulty', $exercise->difficulty) == 'expert' ? 'selected' : '' }}>Expert</option>
                </select>
                @error('difficulty')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label for="duration_minutes" class="block text-sm font-medium text-gray-700 mb-2">
                    Duration (minutes)
                </label>
                <div class="relative">
                    <input type="number" name="duration_minutes" id="duration_minutes" min="1" max="480"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        placeholder="e.g., 15"
                        value="{{ old('duration_minutes', $exercise->duration_minutes) }}">
                    <span class="absolute right-3 top-2 text-gray-500">min</span>
                </div>
                @error('duration_minutes')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="equipment" class="block text-sm font-medium text-gray-700 mb-2">
                    Equipment Needed
                </label>
                <input type="text" name="equipment" id="equipment"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    placeholder="e.g., Putters, Mid-ranges"
                    value="{{ old('equipment', $exercise->equipment) }}">
                @error('equipment')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="mb-6">
            <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                Brief Description
            </label>
            <textarea name="description" id="description" rows="3"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                placeholder="A short summary of what this exercise teaches...">{{ old('description', $exercise->description) }}</textarea>
            @error('description')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-6">
            <label for="instructions" class="block text-sm font-medium text-gray-700 mb-2">
                Detailed Instructions
            </label>
            <textarea name="instructions" id="instructions" rows="8"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                placeholder="Step-by-step instructions for performing this exercise...">{{ old('instructions', $exercise->instructions) }}</textarea>
            <p class="mt-1 text-sm text-gray-500">Use numbered steps or bullet points for clarity.</p>
            @error('instructions')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-6">
            <label for="tags" class="block text-sm font-medium text-gray-700 mb-2">
                Tags
            </label>
            <input type="text" name="tags_input" id="tags"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                placeholder="putting, form, technique (comma separated)"
                value="{{ old('tags_input', implode(', ', $exercise->tags ?? [])) }}">
            <p class="mt-1 text-sm text-gray-500">Separate tags with commas.</p>
        </div>

        <div class="mb-6">
            <label class="flex items-center">
                <input type="checkbox" name="is_public" value="1" 
                    class="w-5 h-5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                    {{ old('is_public', $exercise->is_public) ? 'checked' : '' }}>
                <span class="ml-2 text-sm text-gray-700">Make this exercise public</span>
            </label>
            <p class="mt-1 text-sm text-gray-500 ml-7">Public exercises can be viewed and saved by all users.</p>
        </div>

        <div class="flex items-center justify-end space-x-4">
            <a href="{{ route('exercises.view', $exercise->id) }}" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                Update Exercise
            </button>
        </div>
    </form>
</div>
@endsection
