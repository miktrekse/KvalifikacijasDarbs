@extends('layouts.dashboard')

@section('title', 'Public Exercises')

@section('content')
<div class="container mx-auto px-2 sm:px-4 py-4 sm:py-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Public Exercises</h1>
            <p class="text-gray-600 mt-1">Discover exercises shared by the community</p>
        </div>
        <a href="{{ route('exercises.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Create Exercise
        </a>
    </div>

    @if($exercises->count() > 0)
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach($exercises as $exercise)
                @php
                    $isSaved = Auth::user()->addedExercises()->where('exercise_id', $exercise->id)->exists() ?? false;
                @endphp
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
                        <button 
                            type="button"
                            class="save-btn p-2 rounded-lg transition transform hover:scale-110 @if($isSaved) text-yellow-500 bg-yellow-50 hover:bg-yellow-100 @else text-gray-400 hover:text-yellow-500 hover:bg-gray-100 @endif" 
                            data-exercise-id="{{ $exercise->id }}"
                            data-saved="{{ $isSaved ? 'true' : 'false' }}"
                            title="{{ $isSaved ? 'Remove from saved' : 'Save exercise' }}">
                            <svg class="save-icon w-5 h-5 {{ $isSaved ? 'fill-current' : 'fill-none' }}" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="mt-3 pt-3 border-t border-gray-100 text-xs text-gray-500">
                        By {{ $exercise->user->name ?? 'Unknown' }}
                    </div>
                </div>
            @endforeach
        </div>
        
        <div class="mt-8">
            {{ $exercises->links() }}
        </div>
    @else
        <div class="text-center py-12 bg-white rounded-lg shadow-md">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
            </svg>
            <h3 class="mt-4 text-lg font-medium text-gray-900">No public exercises yet</h3>
            <p class="mt-2 text-gray-500">Be the first to create an exercise and share it with the community!</p>
            <div class="mt-6">
                <a href="{{ route('exercises.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Create Exercise
                </a>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const saveButtons = document.querySelectorAll('.save-btn');
    
    saveButtons.forEach(button => {
        button.addEventListener('click', async function() {
            const exerciseId = this.dataset.exerciseId;
            const isCurrentlySaved = this.dataset.saved === 'true';
            const saveIcon = this.querySelector('.save-icon');
            

            this.disabled = true;
            
            try {
                const response = await fetch('{{ route("exercises.toggleSave") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        exercise_id: exerciseId
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.dataset.saved = data.isSaved ? 'true' : 'false';
                    
                    if (data.isSaved) {
                        this.classList.remove('text-gray-400', 'hover:text-yellow-500', 'hover:bg-gray-100');
                        this.classList.add('text-yellow-500', 'bg-yellow-50', 'hover:bg-yellow-100');
                        saveIcon.classList.add('fill-current');
                        saveIcon.classList.remove('fill-none');
                        
                        saveIcon.classList.add('animate-bounce');
                    } else {
                        this.classList.remove('text-yellow-500', 'bg-yellow-50', 'hover:bg-yellow-100');
                        this.classList.add('text-gray-400', 'hover:text-yellow-500', 'hover:bg-gray-100');
                        saveIcon.classList.remove('fill-current');
                        saveIcon.classList.add('fill-none');
                    }
                
                    showToast(data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Something went wrong. Please try again.', 'error');
            } finally {
                this.disabled = false;
                setTimeout(() => {
                    saveIcon.classList.remove('animate-bounce');
                }, 1000);
            }
        });
    });
    
    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `fixed bottom-4 right-4 px-6 py-3 rounded-lg shadow-lg transform transition-all duration-300 translate-y-0 z-50 ${
            type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
        }`;
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(20px)';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
});
</script>
@endpush
@endsection
