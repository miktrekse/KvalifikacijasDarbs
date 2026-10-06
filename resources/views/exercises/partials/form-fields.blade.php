{{-- Shared fields for creating and editing an exercise; pass $exercise when editing --}}
@use('App\Http\Controllers\ExerciseController')
@php
    $exercise = $exercise ?? null;
    $check = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>';
    $selectedDifficulty = old('difficulty', $exercise->difficulty ?? 'beginner');
    $savedEquipment = old('equipment_options', array_filter(array_map('trim', explode(',', $exercise->equipment ?? ''))));
    $savedStyles = old('throwing_styles', $exercise->throwing_styles ?? []);
@endphp

<section id="drill" class="ds-section">
    <div class="ds-section__head">
        <span class="ds-section__num" style="--accent: #22a268">01</span>
        <div>
            <h2 class="ds-section__title">The drill</h2>
            <p class="ds-section__text">A clear title and steps anyone can follow on the field.</p>
        </div>
    </div>
    <div class="ds-fieldgrid">
        <div class="ds-field is-wide">
            <label for="title" class="ds-field__label">Exercise title <i>*</i></label>
            <input type="text" name="title" id="title" required value="{{ old('title', $exercise->title ?? '') }}"
                class="ds-field__input @error('title') is-invalid @enderror" placeholder="e.g., Perfect Your Putt">
            @error('title') <p class="ds-field__error">{{ $message }}</p> @enderror
        </div>
        <div class="ds-field is-wide">
            <label for="description" class="ds-field__label">Brief description</label>
            <textarea name="description" id="description" rows="3"
                class="ds-field__input @error('description') is-invalid @enderror" placeholder="A short summary of what this exercise teaches…">{{ old('description', $exercise->description ?? '') }}</textarea>
            @error('description') <p class="ds-field__error">{{ $message }}</p> @enderror
        </div>
        <div class="ds-field is-wide">
            <label for="instructions" class="ds-field__label">Detailed instructions</label>
            <textarea name="instructions" id="instructions" rows="8"
                class="ds-field__input @error('instructions') is-invalid @enderror" placeholder="Step-by-step instructions for performing this exercise…">{{ old('instructions', $exercise->instructions ?? '') }}</textarea>
            <p class="ds-field__hint">Use numbered steps or bullet points for clarity.</p>
            @error('instructions') <p class="ds-field__error">{{ $message }}</p> @enderror
        </div>
    </div>
</section>

<section id="setup" class="ds-section">
    <div class="ds-section__head">
        <span class="ds-section__num" style="--accent: #0ea5e9">02</span>
        <div>
            <h2 class="ds-section__title">Setup</h2>
            <p class="ds-section__text">Used by the exercise library filters.</p>
        </div>
    </div>
    <div class="ds-fieldgrid">
        <div class="ds-field">
            <label for="category_id" class="ds-field__label">Category</label>
            <select name="category_id" id="category_id" class="ds-field__input">
                <option value="">Select a category</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" {{ old('category_id', $exercise->category_id ?? null) == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="ds-field">
            <label for="duration_minutes" class="ds-field__label">Duration</label>
            <div class="ds-suffix">
                <input type="number" name="duration_minutes" id="duration_minutes" min="1" max="480" value="{{ old('duration_minutes', $exercise->duration_minutes ?? '') }}"
                    class="ds-field__input @error('duration_minutes') is-invalid @enderror" placeholder="e.g., 15">
                <span>MIN</span>
            </div>
            @error('duration_minutes') <p class="ds-field__error">{{ $message }}</p> @enderror
        </div>

        <fieldset class="ds-field is-wide">
            <legend class="ds-field__label">Difficulty <i>*</i></legend>
            <div class="flex flex-wrap gap-2">
                @foreach(ExerciseController::DIFFICULTIES as $level => $difficulty)
                    <label class="ds-fchip" style="--chip: {{ ExerciseController::DIFFICULTY_COLORS[$difficulty] }}">
                        <input type="radio" name="difficulty" value="{{ $difficulty }}" @checked($selectedDifficulty === $difficulty) @required($loop->first)>
                        <span>
                            <span class="ds-meter" aria-hidden="true">@for($i = 0; $i < 4; $i++)<b @class(['is-on' => $i <= $level])></b>@endfor</span>
                            {{ ucfirst($difficulty) }}
                        </span>
                    </label>
                @endforeach
            </div>
            @error('difficulty') <p class="ds-field__error">{{ $message }}</p> @enderror
        </fieldset>

        <fieldset class="ds-field is-wide">
            <legend class="ds-field__label">Equipment needed</legend>
            <input type="hidden" name="equipment" value="">
            <div class="ds-choices ds-choices--4">
                @foreach(ExerciseController::EQUIPMENT as $value => $label)
                    <label class="ds-choice">
                        <input type="checkbox" name="equipment_options[]" value="{{ $value }}" @checked(in_array($value, $savedEquipment, true))>
                        <span class="ds-choice__check">{!! $check !!}</span>
                        <span class="ds-choice__body"><strong>{{ $label }}</strong></span>
                    </label>
                @endforeach
            </div>
            @error('equipment') <p class="ds-field__error">{{ $message }}</p> @enderror
        </fieldset>

        <fieldset class="ds-field is-wide">
            <legend class="ds-field__label">Throwing style</legend>
            <div class="ds-choices">
                @foreach(['backhand' => 'Backhand', 'forehand' => 'Forehand'] as $value => $label)
                    <label class="ds-choice">
                        <input type="checkbox" name="throwing_styles[]" value="{{ $value }}" @checked(in_array($value, $savedStyles, true))>
                        <span class="ds-choice__check">{!! $check !!}</span>
                        <span class="ds-choice__body"><strong>{{ $label }}</strong></span>
                    </label>
                @endforeach
            </div>
            <p id="putting-style-help" class="ds-field__hint">Choose one or both. Throwing style does not apply to Putting exercises.</p>
            @error('throwing_styles') <p class="ds-field__error">{{ $message }}</p> @enderror
        </fieldset>
    </div>
</section>

<section id="sharing" class="ds-section">
    <div class="ds-section__head">
        <span class="ds-section__num" style="--accent: #f26b3a">03</span>
        <div>
            <h2 class="ds-section__title">Tags &amp; sharing</h2>
            <p class="ds-section__text">Help players find it, and choose who can see it.</p>
        </div>
    </div>
    <div class="ds-fieldgrid">
        <div class="ds-field is-wide">
            <label for="tags" class="ds-field__label">Tags</label>
            <input type="text" name="tags_input" id="tags" value="{{ old('tags_input', implode(', ', $exercise->tags ?? [])) }}"
                class="ds-field__input" placeholder="putting, form, technique">
            <p class="ds-field__hint">Separate tags with commas.</p>
        </div>
        @if(Auth::user()->canPublish())
            <label class="ds-togglecard is-wide">
                <span>
                    <strong>Public exercise</strong>
                    <small>Public exercises can be viewed and saved by all users.</small>
                </span>
                <span class="ds-ftoggle">
                    <input type="checkbox" name="is_public" value="1" @checked(old('is_public', $exercise->is_public ?? true))>
                    <span class="ds-ftoggle__track"><span></span></span>
                </span>
            </label>
        @else
            <p class="is-wide rounded-2xl border border-dashed border-line bg-gray-50 px-4 py-3 text-sm text-gray-500">This exercise will be visible only to you. Verified users can publish exercises.</p>
        @endif
    </div>
</section>

@push('scripts')
<script>
    (() => {
        const category = document.getElementById('category_id');
        const throwingStyles = [...document.querySelectorAll('input[name="throwing_styles[]"]')];
        const help = document.getElementById('putting-style-help');
        function updateThrowingStyles() {
            const isPutting = category.options[category.selectedIndex]?.text.trim().toLowerCase() === 'putting';
            throwingStyles.forEach(input => { input.checked = isPutting ? false : input.checked; input.disabled = isPutting; });
            help.textContent = isPutting ? 'Throwing style is not used for Putting exercises.' : 'Choose one or both. Backhand and forehand can be selected together.';
        }
        category.addEventListener('change', updateThrowingStyles);
        updateThrowingStyles();
    })();
</script>
@endpush
