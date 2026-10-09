@extends('layouts.dashboard')

@section('title', 'Edit Exercise (Admin)')

@section('content')
@use('App\Http\Controllers\ExerciseController')
@php
    $selectedDifficulty = old('difficulty', $exercise->difficulty);
@endphp
<div class="mx-auto max-w-4xl space-y-6">
    <a href="{{ route('admin.exercises') }}" class="ds-back">
        <svg fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M15 19l-7-7 7-7"/></svg>
        Exercises
    </a>

    <section class="ds-hero ds-hero--form">
        <div class="flex flex-wrap items-center gap-2">
            <p class="ds-eyebrow">Edit drill</p>
            <span class="ds-pill bg-amber-100 text-amber-800">Admin mode</span>
        </div>
        <h1 class="mt-4">{{ $exercise->title }}</h1>
        <p class="mt-3 max-w-lg text-sm sm:text-base">Editing as admin — you can modify any exercise.</p>
    </section>

    <form action="{{ route('admin.exercises.update', $exercise->id) }}" method="POST" class="ds-formstack">
        @csrf
        @method('PUT')

        <section class="ds-section">
            <div class="ds-section__head">
                <span class="ds-section__num" style="--accent: #22a268">01</span>
                <div>
                    <h2 class="ds-section__title">The drill</h2>
                    <p class="ds-section__text">Title, summary and step-by-step instructions.</p>
                </div>
            </div>
            <div class="ds-fieldgrid">
                <div class="ds-field is-wide">
                    <label for="title" class="ds-field__label">Exercise title <i>*</i></label>
                    <input type="text" name="title" id="title" required maxlength="255" value="{{ old('title', $exercise->title) }}"
                        class="ds-field__input @error('title') is-invalid @enderror">
                    @error('title') <p class="ds-field__error">{{ $message }}</p> @enderror
                </div>
                <div class="ds-field is-wide">
                    <label for="description" class="ds-field__label">Brief description</label>
                    <textarea name="description" id="description" rows="3" maxlength="5000" class="ds-field__input">{{ old('description', $exercise->description) }}</textarea>
                </div>
                <div class="ds-field is-wide">
                    <label for="instructions" class="ds-field__label">Detailed instructions</label>
                    <textarea name="instructions" id="instructions" rows="8" maxlength="10000" class="ds-field__input">{{ old('instructions', $exercise->instructions) }}</textarea>
                </div>
            </div>
        </section>

        <section class="ds-section">
            <div class="ds-section__head">
                <span class="ds-section__num" style="--accent: #0ea5e9">02</span>
                <div>
                    <h2 class="ds-section__title">Setup &amp; sharing</h2>
                    <p class="ds-section__text">Category, difficulty, equipment and visibility.</p>
                </div>
            </div>
            <div class="ds-fieldgrid">
                <div class="ds-field">
                    <label for="category_id" class="ds-field__label">Category</label>
                    <select name="category_id" id="category_id" class="ds-field__input">
                        <option value="">Select a category</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ old('category_id', $exercise->category_id) == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ds-field">
                    <label for="duration_minutes" class="ds-field__label">Duration</label>
                    <div class="ds-suffix">
                        <input type="number" name="duration_minutes" id="duration_minutes" min="1" max="480" value="{{ old('duration_minutes', $exercise->duration_minutes) }}" class="ds-field__input">
                        <span>MIN</span>
                    </div>
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
                <div class="ds-field">
                    <label for="equipment" class="ds-field__label">Equipment needed</label>
                    <input type="text" name="equipment" id="equipment" value="{{ old('equipment', $exercise->equipment) }}" class="ds-field__input" placeholder="putters, midranges">
                </div>
                <div class="ds-field">
                    <label for="tags" class="ds-field__label">Tags</label>
                    <input type="text" name="tags_input" id="tags" maxlength="500" value="{{ old('tags_input', implode(', ', $exercise->tags ?? [])) }}" class="ds-field__input" placeholder="putting, form, technique">
                </div>
                <label class="ds-togglecard is-wide">
                    <span>
                        <strong>Public exercise</strong>
                        <small>Public exercises can be viewed and saved by all users.</small>
                    </span>
                    <span class="ds-ftoggle">
                        <input type="checkbox" name="is_public" value="1" @checked(old('is_public', $exercise->is_public))>
                        <span class="ds-ftoggle__track"><span></span></span>
                    </span>
                </label>
            </div>
        </section>

        <div class="ds-formbar">
            <p class="ds-formbar__note">Fields marked <span class="font-bold text-flight">*</span> are required.</p>
            <div class="ds-formbar__actions">
                <a href="{{ route('admin.exercises') }}" class="ds-btn ds-btn--line">Cancel</a>
                <button type="submit" class="ds-btn ds-btn--flight">Save changes</button>
            </div>
        </div>
    </form>
</div>
@endsection
