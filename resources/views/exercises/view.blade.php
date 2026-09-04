@extends('layouts.dashboard')

@section('title', $exercise->title)

@section('content')
<div class="container mx-auto px-2 sm:px-4 py-4 sm:py-8">
    <div class="mb-6">
        <a href="{{ url()->previous() }}" class="inline-flex items-center text-indigo-600 hover:text-indigo-800">
            <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Back
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <h1 class="text-3xl font-bold text-gray-800 mb-2">{{ $exercise->title }}</h1>
                            
                            <div class="flex flex-wrap items-center gap-3 text-sm text-gray-600">
                                @if($exercise->category)
                                    <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full">
                                        {{ $exercise->category->name }}
                                    </span>
                                @endif
                                
                                <span class="px-3 py-1 rounded-full 
                                    @if($exercise->difficulty === 'beginner') bg-green-100 text-green-800
                                    @elseif($exercise->difficulty === 'intermediate') bg-yellow-100 text-yellow-800
                                    @elseif($exercise->difficulty === 'advanced') bg-orange-100 text-orange-800
                                    @else bg-red-100 text-red-800 @endif">
                                    {{ ucfirst($exercise->difficulty) }}
                                </span>

                                @if($exercise->duration_minutes)
                                    <span class="flex items-center">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        {{ $exercise->duration_minutes }} min
                                    </span>
                                @endif

                                @if($exercise->equipment)
                                    <span class="flex items-center">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path>
                                        </svg>
                                        {{ $exercise->equipment }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <button 
                            type="button"
                            class="save-btn ml-4 flex items-center px-4 py-2 rounded-lg transition transform hover:scale-105
                            {{ $isSaved ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700 hover:bg-yellow-50' }}"
                            data-exercise-id="{{ $exercise->id }}"
                            data-saved="{{ $isSaved ? 'true' : 'false' }}">
                            <svg class="save-icon w-5 h-5 mr-1 transition-transform" 
                                fill="{{ $isSaved ? 'currentColor' : 'none' }}" 
                                stroke="currentColor" 
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                            </svg>
                            <span class="save-text">{{ $isSaved ? 'Saved' : 'Save' }}</span>
                        </button>
                    </div>
                </div>

                @if($exercise->description)
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-800 mb-3">Description</h2>
                        <p class="text-gray-600 whitespace-pre-line">{{ $exercise->description }}</p>
                    </div>
                @endif

                @if($exercise->instructions)
                    <div class="p-6">
                        <h2 class="text-lg font-semibold text-gray-800 mb-3">Instructions</h2>
                        <div class="prose max-w-none text-gray-600 whitespace-pre-line">
                            {{ $exercise->instructions }}
                        </div>
                    </div>
                @endif
            </div>

            <div class="bg-white rounded-lg shadow-md mt-6 p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">
                    Comments 
                    <span class="text-gray-500 text-sm font-normal">({{ $exercise->comments->count() }})</span>
                </h2>

                <form action="{{ route('exercises.comments.store', $exercise->id) }}" method="POST" class="mb-6">
                    @csrf
                    <div class="flex gap-3">
                        <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <span class="text-indigo-600 font-semibold">{{ substr(Auth::user()->name, 0, 1) }}</span>
                        </div>
                        <div class="flex-1">
                            <textarea 
                                name="content" 
                                rows="3" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="Write a comment..."
                                required></textarea>
                            @error('content')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <button type="submit" class="mt-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                                Post Comment
                            </button>
                        </div>
                    </div>
                </form>

                @if($exercise->comments->count() > 0)
                    <div class="space-y-4">
                        @foreach($exercise->comments as $comment)
                            <div class="flex gap-3 p-4 bg-gray-50 rounded-lg">
                                <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center flex-shrink-0">
                                    <span class="text-indigo-600 text-sm font-semibold">{{ substr($comment->user->name, 0, 1) }}</span>
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <span class="font-medium text-gray-800">{{ $comment->user->name }}</span>
                                            <span class="text-xs text-gray-500 ml-2">{{ $comment->created_at->diffForHumans() }}</span>
                                        </div>
                                        @if(Auth::id() === $comment->user_id || Auth::id() === $exercise->user_id)
                                            <form action="{{ route('exercises.comments.delete', $exercise->id) }}" method="POST">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="comment_id" value="{{ $comment->id }}">
                                                <button type="submit" class="text-gray-400 hover:text-red-500 transition p-1">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                    </svg>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                    <p class="mt-1 text-gray-600">{{ $comment->content }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-500 text-center py-4">No comments yet. Be the first to comment!</p>
                @endif
            </div>
        </div>

        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Created By</h3>
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center">
                        <span class="text-indigo-600 font-semibold">{{ substr($exercise->user->name, 0, 1) }}</span>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-800">{{ $exercise->user->name }}</p>
                        <p class="text-xs text-gray-500">{{ $exercise->created_at->diffForHumans() }}</p>
                    </div>
                </div>
            </div>

            @if($exercise->tags && count($exercise->tags) > 0)
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Tags</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach($exercise->tags as $tag)
                            <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm">
                                {{ $tag }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($isOwner)
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Your Exercise</h3>
                    <div class="space-y-3">
                        <a href="{{ route('exercises.edit', $exercise->id) }}" 
                            class="flex items-center justify-center w-full px-4 py-2 bg-indigo-100 text-indigo-700 rounded-lg hover:bg-indigo-200 transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            Edit Exercise
                        </a>
                        <form action="{{ route('exercises.destroy', $exercise->id) }}" method="POST" 
                            onsubmit="return confirm('Are you sure you want to delete this exercise?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" 
                                class="flex items-center justify-center w-full px-4 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                                Delete Exercise
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            <div class="bg-white rounded-lg shadow-md p-6 mt-6">
                <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">Status</h3>
                <div class="flex items-center">
                    @if($exercise->is_public)
                        <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="text-gray-700">Public</span>
                    @else
                        <svg class="w-5 h-5 text-gray-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                        <span class="text-gray-700">Private</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
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
            const saveText = this.querySelector('.save-text');
            const originalContent = this.innerHTML;
    
            this.disabled = true;
            this.classList.add('opacity-50', 'cursor-not-allowed');
            
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
                        this.classList.remove('bg-gray-100', 'text-gray-700', 'hover:bg-yellow-50');
                        this.classList.add('bg-yellow-100', 'text-yellow-700');
                        saveIcon.setAttribute('fill', 'currentColor');
                        saveText.textContent = 'Saved';
                        
                        saveIcon.classList.add('animate-bounce');
                    } else {
                        this.classList.remove('bg-yellow-100', 'text-yellow-700');
                        this.classList.add('bg-gray-100', 'text-gray-700', 'hover:bg-yellow-50');
                        saveIcon.setAttribute('fill', 'none');
                        saveText.textContent = 'Save';
                    }
                    

                    showToast(data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Something went wrong. Please try again.', 'error');
            } finally {
                this.disabled = false;
                this.classList.remove('opacity-50', 'cursor-not-allowed');
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
