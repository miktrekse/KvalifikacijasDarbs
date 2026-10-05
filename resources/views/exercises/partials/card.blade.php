{{--
    One exercise card. Expects $exercise (with category, user, users_count) and $isSaved.
    On the saved list the exercise comes through the pivot, which adds "saved …" info.
--}}
@use('App\Http\Controllers\ExerciseController')
@php
    $color = $exercise->category->color ?? '#22a268';
    $level = array_flip(ExerciseController::DIFFICULTIES)[$exercise->difficulty] ?? 0;
    $discs = collect(explode(',', (string) $exercise->equipment))->map(fn ($e) => trim($e))->filter()
        ->map(fn ($e) => ExerciseController::EQUIPMENT[$e] ?? $e);
    $savedAt = $exercise->pivot?->created_at;
    $isMine = $exercise->user_id === Auth::id();
@endphp
<article class="ds-drill" style="--drill: {{ $color }}">
    <div class="flex items-start justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            @if($exercise->category)
                <span class="ds-pill" style="background: {{ $color }}1f; color: {{ $color }}">{{ $exercise->category->name }}</span>
            @endif
            <span class="ds-drill__level" title="{{ ucfirst($exercise->difficulty) }}" style="--lvl: {{ ExerciseController::DIFFICULTY_COLORS[$exercise->difficulty] ?? '#a39d8e' }}">
                <span class="ds-meter">@for($i = 0; $i < 4; $i++)<b @class(['is-on' => $i <= $level])></b>@endfor</span>
                {{ ucfirst($exercise->difficulty) }}
            </span>
            @if(!$exercise->is_public)
                <span class="ds-pill bg-gray-100 text-gray-600">Private</span>
            @endif
        </div>
        <button type="button" class="ds-save {{ $isSaved ? 'is-saved' : '' }}" data-save-exercise="{{ $exercise->id }}" data-save-url="{{ route('exercises.toggleSave') }}" data-saved="{{ $isSaved ? 'true' : 'false' }}" title="{{ $isSaved ? 'Remove from saved' : 'Save exercise' }}" aria-pressed="{{ $isSaved ? 'true' : 'false' }}">
            <svg class="h-5 w-5" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
        </button>
    </div>

    <a href="{{ route('exercises.view', $exercise->id) }}" class="mt-3 block">
        <h2 class="font-display text-xl font-extrabold leading-tight tracking-tight text-ink">{{ $exercise->title }}</h2>
        <p class="mt-2 line-clamp-2 text-sm text-gray-600">{{ $exercise->description }}</p>
    </a>

    <dl class="ds-drill__meta">
        <div>
            <dt>Time</dt>
            <dd>{{ $exercise->duration_minutes ? $exercise->duration_minutes . ' min' : '—' }}</dd>
        </div>
        <div>
            <dt>Style</dt>
            <dd>{{ $exercise->throwing_styles ? collect($exercise->throwing_styles)->map(fn ($s) => $s === 'backhand' ? 'BH' : 'FH')->implode(' · ') : 'Putt' }}</dd>
        </div>
        <div>
            <dt>Saved</dt>
            <dd data-saves-count="{{ $exercise->id }}">{{ $exercise->users_count }}</dd>
        </div>
    </dl>

    @if($discs->isNotEmpty())
        <div class="mt-3 flex flex-wrap gap-1.5">
            @foreach($discs as $disc)
                <span class="ds-tag">{{ $disc }}</span>
            @endforeach
        </div>
    @endif

    <div class="mt-auto flex items-center justify-between gap-3 border-t border-dashed border-line pt-4">
        <span class="truncate text-xs text-gray-500">
            {{ $isMine ? 'Your drill' : 'By ' . ($exercise->user->name ?? 'Unknown') }}@if($savedAt) · saved {{ $savedAt->diffForHumans() }}@endif
        </span>
        <a href="{{ route('exercises.view', $exercise->id) }}" class="shrink-0 text-sm font-bold text-indigo-700 hover:text-indigo-900">Open drill →</a>
    </div>
</article>
