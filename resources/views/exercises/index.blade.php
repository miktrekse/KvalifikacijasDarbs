@extends('layouts.dashboard')

@section('title', 'Exercises')

@section('content')
@use('App\Http\Controllers\ExerciseController')
@php
    $selectedDifficulties = $filters['difficulty'] ?? [];
    $difficultyLevel = array_flip(ExerciseController::DIFFICULTIES);
    $difficultyColors = ['beginner' => '#22a268', 'intermediate' => '#0ea5e9', 'advanced' => '#f26b3a', 'expert' => '#b91c1c'];
    $activeCategory = $categories->firstWhere('id', (int) ($filters['category'] ?? 0));

    // Removable chips for every active filter (search and sort excluded)
    $chips = collect();
    if ($activeCategory) $chips->push(['label' => $activeCategory->name, 'drop' => 'category']);
    foreach ($selectedDifficulties as $d) $chips->push(['label' => ucfirst($d), 'drop' => 'difficulty', 'value' => $d]);
    if (!empty($filters['style'])) $chips->push(['label' => ucfirst($filters['style']), 'drop' => 'style']);
    if (!empty($filters['equipment'])) $chips->push(['label' => ExerciseController::EQUIPMENT[$filters['equipment']], 'drop' => 'equipment']);
    if (!empty($filters['duration'])) $chips->push(['label' => ExerciseController::DURATIONS[$filters['duration']]['label'], 'drop' => 'duration']);
    if (!empty($filters['saved'])) $chips->push(['label' => 'Saved only', 'drop' => 'saved']);

    $without = function (string $key, ?string $value = null) {
        $query = request()->except(['page']);
        if ($value !== null && is_array($query[$key] ?? null)) {
            $query[$key] = array_values(array_diff($query[$key], [$value]));
        } else {
            unset($query[$key]);
        }
        return url()->current() . (($q = http_build_query($query)) ? '?' . $q : '');
    };
    $isFiltering = $chips->isNotEmpty() || !empty($filters['q']);
@endphp

<div class="space-y-6">
    <section class="ds-hero">
        <div class="flex flex-wrap items-end justify-between gap-6">
            <div>
                <p class="ds-eyebrow">Drill library</p>
                <h1 class="mt-4">Find your<br><em>next drill.</em></h1>
                <p class="mt-4 max-w-md text-sm sm:text-base">{{ $totalPublic }} practice exercises for putting, driving, approaches and scrambling — filter for exactly what you want to work on.</p>
            </div>
            @unless(Auth::user()->isGuest())
                <a href="{{ route('exercises.create') }}" class="ds-btn ds-btn--flight">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    Create exercise
                </a>
            @endunless
        </div>
    </section>

    <form method="GET" action="{{ route('exercises.index') }}" id="exercise-filters" class="ds-filters">
        <div class="ds-filters__search">
            <svg class="h-5 w-5 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search drills, e.g. “hyzer”, “circle 2”, “wind”" autocomplete="off">
            <button type="submit" class="ds-btn ds-btn--line !py-2 shrink-0">Search</button>
        </div>

        {{-- Category --}}
        <div class="ds-filters__row">
            <span class="ds-filters__label">Category</span>
            <div class="flex flex-wrap gap-2">
                <label class="ds-fchip">
                    <input type="radio" name="category" value="" @checked(!$activeCategory)>
                    <span>All</span>
                </label>
                @foreach($categories as $category)
                    <label class="ds-fchip" style="--chip: {{ $category->color }}">
                        <input type="radio" name="category" value="{{ $category->id }}" @checked($activeCategory?->id === $category->id)>
                        <span><i></i>{{ $category->name }} <small>{{ $category->exercises_count }}</small></span>
                    </label>
                @endforeach
            </div>
        </div>

        {{-- Difficulty (multi-select) --}}
        <div class="ds-filters__row">
            <span class="ds-filters__label">Difficulty</span>
            <div class="flex flex-wrap gap-2">
                @foreach(ExerciseController::DIFFICULTIES as $difficulty)
                    <label class="ds-fchip" style="--chip: {{ $difficultyColors[$difficulty] }}">
                        <input type="checkbox" name="difficulty[]" value="{{ $difficulty }}" @checked(in_array($difficulty, $selectedDifficulties, true))>
                        <span>
                            <span class="ds-meter" aria-hidden="true">@for($i = 0; $i < 4; $i++)<b @class(['is-on' => $i <= $difficultyLevel[$difficulty]])></b>@endfor</span>
                            {{ ucfirst($difficulty) }}
                        </span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="ds-filters__grid">
            <label class="ds-fselect">
                <span>Throwing style</span>
                <select name="style">
                    <option value="">Any style</option>
                    <option value="backhand" @selected(($filters['style'] ?? '') === 'backhand')>Backhand</option>
                    <option value="forehand" @selected(($filters['style'] ?? '') === 'forehand')>Forehand</option>
                </select>
            </label>
            <label class="ds-fselect">
                <span>Discs</span>
                <select name="equipment">
                    <option value="">Any discs</option>
                    @foreach(ExerciseController::EQUIPMENT as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['equipment'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="ds-fselect">
                <span>Time</span>
                <select name="duration">
                    <option value="">Any length</option>
                    @foreach(ExerciseController::DURATIONS as $value => $duration)
                        <option value="{{ $value }}" @selected(($filters['duration'] ?? '') === $value)>{{ $duration['label'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="ds-fselect">
                <span>Sort by</span>
                <select name="sort">
                    <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>Newest</option>
                    <option value="popular" @selected(($filters['sort'] ?? '') === 'popular')>Most saved</option>
                    <option value="easiest" @selected(($filters['sort'] ?? '') === 'easiest')>Easiest first</option>
                    <option value="hardest" @selected(($filters['sort'] ?? '') === 'hardest')>Hardest first</option>
                    <option value="shortest" @selected(($filters['sort'] ?? '') === 'shortest')>Shortest first</option>
                </select>
            </label>
            @unless(Auth::user()->isGuest())
                <label class="ds-ftoggle">
                    <input type="checkbox" name="saved" value="1" @checked(!empty($filters['saved']))>
                    <span class="ds-ftoggle__track"><span></span></span>
                    <span class="text-sm font-bold text-ink">Saved only</span>
                </label>
            @endunless
        </div>
    </form>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-500">
            <strong class="font-display text-lg font-extrabold text-ink">{{ $exercises->total() }}</strong>
            {{ Str::plural('drill', $exercises->total()) }}
            @if(!empty($filters['q'])) matching “{{ $filters['q'] }}” @endif
        </p>
        @if($isFiltering)
            <div class="flex flex-wrap items-center gap-2">
                @foreach($chips as $chip)
                    <a href="{{ $without($chip['drop'], $chip['value'] ?? null) }}" class="ds-activechip" title="Remove filter">{{ $chip['label'] }} <span aria-hidden="true">×</span></a>
                @endforeach
                <a href="{{ route('exercises.index') }}" class="text-sm font-bold text-flight hover:underline">Clear all</a>
            </div>
        @endif
    </div>

    @if($exercises->count() > 0)
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach($exercises as $exercise)
                @php
                    $isSaved = in_array($exercise->id, $savedIds, true);
                    $color = $exercise->category->color ?? '#22a268';
                    $level = $difficultyLevel[$exercise->difficulty] ?? 0;
                    $discs = collect(explode(',', (string) $exercise->equipment))->map(fn ($e) => trim($e))->filter()
                        ->map(fn ($e) => ExerciseController::EQUIPMENT[$e] ?? $e);
                @endphp
                <article class="ds-drill" style="--drill: {{ $color }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-2">
                            @if($exercise->category)
                                <span class="ds-pill" style="background: {{ $color }}1f; color: {{ $color }}">{{ $exercise->category->name }}</span>
                            @endif
                            <span class="ds-drill__level" title="{{ ucfirst($exercise->difficulty) }}" style="--lvl: {{ $difficultyColors[$exercise->difficulty] ?? '#a39d8e' }}">
                                <span class="ds-meter">@for($i = 0; $i < 4; $i++)<b @class(['is-on' => $i <= $level])></b>@endfor</span>
                                {{ ucfirst($exercise->difficulty) }}
                            </span>
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
                        <span class="truncate text-xs text-gray-500">By {{ $exercise->user->name ?? 'Unknown' }}</span>
                        <a href="{{ route('exercises.view', $exercise->id) }}" class="shrink-0 text-sm font-bold text-indigo-700 hover:text-indigo-900">Open drill →</a>
                    </div>
                </article>
            @endforeach
        </div>

        <div>{{ $exercises->links() }}</div>
    @else
        <div class="ds-card px-6 py-14 text-center">
            <p class="font-display text-2xl font-extrabold text-ink">{{ $isFiltering ? 'No drills match those filters' : 'No public exercises yet' }}</p>
            <p class="mt-2 text-sm text-gray-500">{{ $isFiltering ? 'Try removing a filter or searching for something broader.' : 'Be the first to create an exercise and share it with the community!' }}</p>
            @if($isFiltering)
                <a href="{{ route('exercises.index') }}" class="ds-btn ds-btn--line mt-6">Clear all filters</a>
            @endif
        </div>
    @endif
</div>

@push('scripts')
<script>
(() => {
    const form = document.getElementById('exercise-filters');
    // Chips and selects apply instantly; the search box applies on Enter / Search
    form.querySelectorAll('input[type=radio], input[type=checkbox], select').forEach(el => el.addEventListener('change', () => form.requestSubmit()));
    form.addEventListener('submit', () => {
        // Keep URLs tidy: drop empty values and the default sort
        form.querySelectorAll('input, select').forEach(el => {
            if ((el.value === '' || (el.name === 'sort' && el.value === 'newest')) && el.type !== 'checkbox' && el.type !== 'radio') el.disabled = true;
            if (el.type === 'radio' && el.checked && el.value === '') el.disabled = true;
        });
    });

})();
</script>
@endpush
@endsection
