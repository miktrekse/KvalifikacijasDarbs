{{--
    Exercise filter panel + result count + removable filter chips.
    Used by the public library and the saved list.

    Expects: $filters, $categories (with exercises_count), $exercises (paginator),
    $action (form URL), $sorts ([value => label], first is the default),
    $showSavedToggle (bool), $noun (e.g. "drill" / "saved drill").
--}}
@use('App\Http\Controllers\ExerciseController')
@php
    $selectedDifficulties = $filters['difficulty'] ?? [];
    $difficultyLevel = array_flip(ExerciseController::DIFFICULTIES);
    $difficultyColors = ExerciseController::DIFFICULTY_COLORS;
    $activeCategory = $categories->firstWhere('id', (int) ($filters['category'] ?? 0));
    $defaultSort = array_key_first($sorts);

    // Removable chips for every active filter (search and sort excluded)
    $chips = collect();
    if ($activeCategory) $chips->push(['label' => $activeCategory->name, 'drop' => 'category']);
    foreach ($selectedDifficulties as $d) $chips->push(['label' => ucfirst($d), 'drop' => 'difficulty', 'value' => $d]);
    if (!empty($filters['style'])) $chips->push(['label' => ucfirst($filters['style']), 'drop' => 'style']);
    if (!empty($filters['equipment'])) $chips->push(['label' => ExerciseController::EQUIPMENT[$filters['equipment']], 'drop' => 'equipment']);
    if (!empty($filters['duration'])) $chips->push(['label' => ExerciseController::DURATIONS[$filters['duration']]['label'], 'drop' => 'duration']);
    if (!empty($filters['saved'])) $chips->push(['label' => 'Saved only', 'drop' => 'saved']);
    if (!empty($filters['q'])) $chips->push(['label' => '“' . $filters['q'] . '”', 'drop' => 'q']);

    $without = function (string $key, ?string $value = null) use ($action) {
        $query = request()->except(['page']);
        if ($value !== null && is_array($query[$key] ?? null)) {
            $query[$key] = array_values(array_diff($query[$key], [$value]));
        } else {
            unset($query[$key]);
        }
        return $action . (($q = http_build_query($query)) ? '?' . $q : '');
    };
@endphp

<form method="GET" action="{{ $action }}" data-exercise-filters data-default-sort="{{ $defaultSort }}" class="ds-filters">
    <div class="ds-filters__search">
        <svg class="h-5 w-5 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search {{ Str::plural($noun) }}, e.g. “hyzer”, “circle 2”, “wind”" autocomplete="off">
        <button type="submit" class="ds-btn ds-btn--line !py-2 shrink-0">Search</button>
    </div>

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
                @foreach($sorts as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['sort'] ?? $defaultSort) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        @if($showSavedToggle)
            <label class="ds-ftoggle">
                <input type="checkbox" name="saved" value="1" @checked(!empty($filters['saved']))>
                <span class="ds-ftoggle__track"><span></span></span>
                <span class="text-sm font-bold text-ink">Saved only</span>
            </label>
        @endif
    </div>
</form>

<div class="flex flex-wrap items-center justify-between gap-3">
    <p class="text-sm text-gray-500">
        <strong class="font-display text-lg font-extrabold text-ink">{{ $exercises->total() }}</strong>
        {{ Str::plural($noun, $exercises->total()) }}
    </p>
    @if($chips->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2">
            @foreach($chips as $chip)
                <a href="{{ $without($chip['drop'], $chip['value'] ?? null) }}" class="ds-activechip" title="Remove filter">{{ $chip['label'] }} <span aria-hidden="true">×</span></a>
            @endforeach
            <a href="{{ $action }}" class="text-sm font-bold text-flight hover:underline">Clear all</a>
        </div>
    @endif
</div>

@pushOnce('scripts')
<script>
document.querySelectorAll('[data-exercise-filters]').forEach(form => {
    // Chips and selects apply instantly; the search box applies on Enter / Search
    form.querySelectorAll('input[type=radio], input[type=checkbox], select').forEach(el => el.addEventListener('change', () => form.requestSubmit()));
    form.addEventListener('submit', () => {
        // Keep URLs tidy: drop empty values and the default sort
        form.querySelectorAll('input, select').forEach(el => {
            const isChoice = el.type === 'checkbox' || el.type === 'radio';
            if (!isChoice && (el.value === '' || (el.name === 'sort' && el.value === form.dataset.defaultSort))) el.disabled = true;
            if (el.type === 'radio' && el.checked && el.value === '') el.disabled = true;
        });
    });
});
</script>
@endPushOnce
