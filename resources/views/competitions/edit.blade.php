@extends('layouts.dashboard')

@section('title', 'Edit Competition')

@section('content')
@php
    $check = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>';
    $savedDivisions = old('division_options', is_array($competition->divisions) ? $competition->divisions : (json_decode($competition->divisions, true) ?? []));
    // Saved rules, plus any custom division names without rules (older events), so saving keeps them
    $standardDivisions = ['MPO', 'MA1', 'MA2', 'MA3', 'MA4', 'FPO', 'FA2', 'FA3', 'FA4', 'MP60', 'MP50', 'MP40', 'FP40', 'MJ18', 'MJ15', 'FJ18'];
    $customDivisionRules = $competition->division_rules ?? [];
    foreach ($competition->divisionsArray as $savedDivision) {
        if (!in_array($savedDivision, $standardDivisions, true) && !isset($customDivisionRules[$savedDivision])) {
            $customDivisionRules[$savedDivision] = ['gender' => 'any', 'min_age' => null, 'max_age' => null, 'min_rating' => null];
        }
    }
    if (old('division_rules')) {
        $customDivisionRules = collect(old('division_rules'))->mapWithKeys(fn ($rule) => [$rule['name'] ?? '' => $rule])->all();
    }
@endphp
<div class="mx-auto max-w-4xl space-y-6">
    <a href="{{ route('competitions.view', $competition->id) }}" class="ds-back">
        <svg fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M15 19l-7-7 7-7"/></svg>
        Back to competition
    </a>

    <section class="ds-hero ds-hero--form">
        <div class="flex flex-wrap items-center gap-2">
            <p class="ds-eyebrow">Edit tournament</p>
            <span class="ds-pill bg-amber-100 text-amber-800">Admin mode</span>
        </div>
        <h1 class="mt-4">{{ $competition->name }}</h1>
        <p class="mt-3 max-w-lg text-sm sm:text-base">Update event details, approval and status. Changes are visible to registered players right away.</p>
        <nav class="ds-hero__steps mt-6" aria-label="Form sections">
            <a href="#admin"><b>★</b>Admin</a>
            <a href="#basics"><b>1</b>Basics</a>
            <a href="#when-where"><b>2</b>When &amp; where</a>
            <a href="#format"><b>3</b>Format</a>
            <a href="#registration"><b>4</b>Registration</a>
        </nav>
    </section>

    <form method="POST" action="{{ route('competitions.update', $competition->id) }}" class="ds-formstack">
        @csrf
        @method('PUT')

        <section id="admin" class="ds-section ds-section--admin">
            <div class="ds-section__head">
                <span class="ds-section__num" style="--accent: #d97706">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6z"/></svg>
                </span>
                <div>
                    <h2 class="ds-section__title">Admin controls</h2>
                    <p class="ds-section__text">Approval, visibility and the event's current status.</p>
                </div>
            </div>
            <div class="ds-fieldgrid">
                <label class="ds-togglecard">
                    <span>
                        <strong>Approved</strong>
                        <small>Shown in public listings.</small>
                    </span>
                    <span class="ds-ftoggle">
                        <input type="checkbox" name="is_approved" id="is_approved" value="1" {{ $competition->is_approved ? 'checked' : '' }}>
                        <span class="ds-ftoggle__track"><span></span></span>
                    </span>
                </label>
                <label class="ds-togglecard">
                    <span>
                        <strong>Public</strong>
                        <small>Off keeps it reachable by direct link only.</small>
                    </span>
                    <span class="ds-ftoggle">
                        <input type="checkbox" name="is_public" id="is_public" value="1" {{ $competition->is_public ? 'checked' : '' }}>
                        <span class="ds-ftoggle__track"><span></span></span>
                    </span>
                </label>
                <div class="ds-field">
                    <label for="status" class="ds-field__label">Status</label>
                    <select name="status" id="status" class="ds-field__input">
                        <option value="upcoming" {{ $competition->status == 'upcoming' ? 'selected' : '' }}>Upcoming</option>
                        <option value="ongoing" {{ $competition->status == 'ongoing' ? 'selected' : '' }}>Ongoing</option>
                        <option value="completed" {{ $competition->status == 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="cancelled" {{ $competition->status == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>
            </div>
        </section>

        <section id="basics" class="ds-section">
            <div class="ds-section__head">
                <span class="ds-section__num" style="--accent: #22a268">01</span>
                <div>
                    <h2 class="ds-section__title">Basics</h2>
                    <p class="ds-section__text">What players will see on the event card.</p>
                </div>
            </div>
            <div class="ds-fieldgrid">
                <div class="ds-field is-wide">
                    <label for="name" class="ds-field__label">Competition name <i>*</i></label>
                    <input type="text" name="name" id="name" value="{{ old('name', $competition->name) }}" required
                        class="ds-field__input @error('name') is-invalid @enderror" placeholder="Summer Open 2026">
                    @error('name') <p class="ds-field__error">{{ $message }}</p> @enderror
                </div>
                <div class="ds-field is-wide">
                    <label for="description" class="ds-field__label">Description</label>
                    <textarea name="description" id="description" rows="4"
                        class="ds-field__input @error('description') is-invalid @enderror" placeholder="Rules, prizes, schedule…">{{ old('description', $competition->description) }}</textarea>
                    @error('description') <p class="ds-field__error">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <section id="when-where" class="ds-section">
            <div class="ds-section__head">
                <span class="ds-section__num" style="--accent: #0ea5e9">02</span>
                <div>
                    <h2 class="ds-section__title">When &amp; where</h2>
                    <p class="ds-section__text">Date, tee off and the course being played.</p>
                </div>
            </div>
            <div class="ds-fieldgrid">
                <div class="ds-field">
                    <label for="event_date" class="ds-field__label">Event date <i>*</i></label>
                    <input type="date" name="event_date" id="event_date" value="{{ old('event_date', $competition->event_date->format('Y-m-d')) }}" required
                        class="ds-field__input @error('event_date') is-invalid @enderror">
                    @error('event_date') <p class="ds-field__error">{{ $message }}</p> @enderror
                </div>
                <div class="ds-field">
                    <label for="start_time" class="ds-field__label">Tee off time</label>
                    <input type="time" name="start_time" id="start_time" value="{{ old('start_time', $competition->start_time ? substr($competition->start_time, 0, 5) : \App\Models\Competition::DEFAULT_START_TIME) }}"
                        class="ds-field__input @error('start_time') is-invalid @enderror">
                    <p class="ds-field__hint">
                        @if($competition->hasGroups())
                            Groups were drawn {{ $competition->groups_assigned_at->diffForHumans() }}.
                        @else
                            Groups are drawn {{ \App\Models\Competition::GROUPS_DRAWN_MINUTES_BEFORE }} minutes before; scoring opens at tee off.
                        @endif
                    </p>
                    @error('start_time') <p class="ds-field__error">{{ $message }}</p> @enderror
                </div>
                <div class="ds-field">
                    <label for="course_name" class="ds-field__label">Course name</label>
                    <input type="text" name="course_name" id="course_name" value="{{ old('course_name', $competition->course_name) }}"
                        class="ds-field__input @error('course_name') is-invalid @enderror" placeholder="Mežaparks Disc Golf Course">
                    @error('course_name') <p class="ds-field__error">{{ $message }}</p> @enderror
                </div>
                <div class="ds-field">
                    <label for="location" class="ds-field__label">Location</label>
                    <input type="text" name="location" id="location" value="{{ old('location', $competition->location) }}"
                        class="ds-field__input @error('location') is-invalid @enderror" placeholder="City, Country">
                    @error('location') <p class="ds-field__error">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <section id="format" class="ds-section">
            <div class="ds-section__head">
                <span class="ds-section__num" style="--accent: #f26b3a">03</span>
                <div>
                    <h2 class="ds-section__title">Format &amp; divisions</h2>
                    <p class="ds-section__text">How the event is played and who can enter which division.</p>
                </div>
            </div>
            <div class="ds-fieldgrid sm:!grid-cols-3">
                <div class="ds-field">
                    <label for="competition_type" class="ds-field__label">Players <i>*</i></label>
                    <select name="competition_type" id="competition_type" required class="ds-field__input @error('competition_type') is-invalid @enderror">
                        <option value="singles" {{ old('competition_type', $competition->competition_type ?? 'singles') == 'singles' ? 'selected' : '' }}>Singles</option>
                        <option value="doubles" {{ old('competition_type', $competition->competition_type ?? 'singles') == 'doubles' ? 'selected' : '' }}>Doubles</option>
                    </select>
                </div>
                <div class="ds-field">
                    <label for="format_select" class="ds-field__label">Format <i>*</i></label>
                    <select name="format" id="format_select" required class="ds-field__input @error('format') is-invalid @enderror"></select>
                    @error('format') <p class="ds-field__error">{{ $message }}</p> @enderror
                </div>
                <div class="ds-field">
                    <label for="holes" class="ds-field__label">Holes <i>*</i></label>
                    <input type="number" name="holes" id="holes" min="1" max="99" required value="{{ old('holes', $competition->holes) }}"
                        class="ds-field__input @error('holes') is-invalid @enderror">
                    @error('holes') <p class="ds-field__error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="ds-subsection">
                <p class="ds-field__label">Divisions</p>
                <input type="hidden" name="divisions" value="">
                <div class="ds-choices ds-choices--4">
                    @foreach([
                        'MPO' => 'Open · recommended 930+', 'MA1' => 'Recommended 929-880', 'MA2' => 'Recommended 879-820',
                        'MA3' => 'Recommended 819-750', 'MA4' => 'Recommended 749-0', 'FPO' => 'Women only · recommended 880+',
                        'FA2' => 'Women only · recommended 879-820', 'FA3' => 'Women only · recommended 819-750', 'FA4' => 'Women only · recommended 749-0',
                        'MP60' => 'Age 60+', 'MP50' => 'Age 50+', 'MP40' => 'Age 40+',
                        'FP40' => 'Women only · age 40+', 'MJ18' => 'Boys · under 18', 'MJ15' => 'Boys · under 15', 'FJ18' => 'Girls · under 18'
                    ] as $division => $threshold)
                        <label class="ds-choice">
                            <input type="checkbox" name="division_options[]" value="{{ $division }}" {{ in_array($division, $savedDivisions, true) ? 'checked' : '' }}>
                            <span class="ds-choice__check">{!! $check !!}</span>
                            <span class="ds-choice__body"><strong>{{ $division }}</strong><small>{{ $threshold }}</small></span>
                        </label>
                    @endforeach
                </div>
                <p class="ds-field__hint">Ratings are recommendations only. Gender and age requirements still apply where shown.</p>
                @error('divisions') <p class="ds-field__error">{{ $message }}</p> @enderror
            </div>

            <div class="ds-subsection">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="font-display text-base font-extrabold text-ink">Custom divisions</h3>
                        <p class="text-xs text-gray-500">Create your own division name and eligibility rules.</p>
                    </div>
                    <button type="button" id="add-division-rule" class="ds-btn ds-btn--line !py-2">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                        Add division
                    </button>
                </div>
                <div id="division-rules" class="mt-3 space-y-2">
                    @foreach($customDivisionRules as $index => $rule)
                        <div class="competition-division-rule">
                            <input name="division_rules[{{ $index }}][name]" value="{{ $index }}" maxlength="40" required placeholder="Division name">
                            <select name="division_rules[{{ $index }}][gender]"><option value="any" {{ ($rule['gender'] ?? 'any') === 'any' ? 'selected' : '' }}>Any gender</option><option value="male" {{ ($rule['gender'] ?? '') === 'male' ? 'selected' : '' }}>Male only</option><option value="female" {{ ($rule['gender'] ?? '') === 'female' ? 'selected' : '' }}>Female only</option></select>
                            <input type="number" name="division_rules[{{ $index }}][min_age]" value="{{ $rule['min_age'] ?? '' }}" min="0" max="120" placeholder="Min age">
                            <input type="number" name="division_rules[{{ $index }}][max_age]" value="{{ $rule['max_age'] ?? '' }}" min="0" max="120" placeholder="Max age">
                            <input type="number" name="division_rules[{{ $index }}][min_rating]" value="{{ $rule['min_rating'] ?? '' }}" min="0" max="1100" placeholder="Recommended rating">
                            <button type="button" class="remove-division-rule" aria-label="Remove division">&times;</button>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="registration" class="ds-section">
            <div class="ds-section__head">
                <span class="ds-section__num" style="--accent: #8b5cf6">04</span>
                <div>
                    <h2 class="ds-section__title">Registration</h2>
                    <p class="ds-section__text">Fees, limits and deadlines.</p>
                </div>
            </div>
            <div class="ds-fieldgrid">
                <div class="ds-field">
                    <label for="entry_fee" class="ds-field__label">Entry fee</label>
                    <div class="ds-inputgroup">
                        <input type="number" name="entry_fee" id="entry_fee" value="{{ old('entry_fee', $competition->entry_fee) }}" step="0.01" min="0"
                            class="ds-field__input @error('entry_fee') is-invalid @enderror" placeholder="0.00">
                        <select name="currency" aria-label="Currency" class="ds-field__input">
                            <option value="EUR" {{ old('currency', $competition->currency) == 'EUR' ? 'selected' : '' }}>EUR</option>
                            <option value="USD" {{ old('currency', $competition->currency) == 'USD' ? 'selected' : '' }}>USD</option>
                            <option value="GBP" {{ old('currency', $competition->currency) == 'GBP' ? 'selected' : '' }}>GBP</option>
                        </select>
                    </div>
                    @error('entry_fee') <p class="ds-field__error">{{ $message }}</p> @enderror
                </div>
                <div class="ds-field">
                    <label for="max_participants" class="ds-field__label">Max participants</label>
                    <input type="number" name="max_participants" id="max_participants" value="{{ old('max_participants', $competition->max_participants) }}" min="1"
                        class="ds-field__input @error('max_participants') is-invalid @enderror" placeholder="Unlimited">
                    @error('max_participants') <p class="ds-field__error">{{ $message }}</p> @enderror
                </div>
                <div class="ds-field">
                    <label for="registration_deadline" class="ds-field__label">Registration deadline</label>
                    <input type="datetime-local" name="registration_deadline" id="registration_deadline" value="{{ old('registration_deadline', $competition->registration_deadline ? $competition->registration_deadline->format('Y-m-d\TH:i') : '') }}"
                        class="ds-field__input @error('registration_deadline') is-invalid @enderror">
                    @error('registration_deadline') <p class="ds-field__error">{{ $message }}</p> @enderror
                </div>
                <div class="ds-field">
                    <label for="registration_link" class="ds-field__label">External registration link</label>
                    <input type="url" name="registration_link" id="registration_link" value="{{ old('registration_link', $competition->registration_link) }}"
                        class="ds-field__input @error('registration_link') is-invalid @enderror" placeholder="https://…">
                    @error('registration_link') <p class="ds-field__error">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <div class="ds-formbar">
            {{-- Submits the separate delete form below; forms can't be nested, or its DELETE would leak into this update --}}
            <button type="submit" form="delete-competition-form" class="ds-btn ds-btn--danger">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>
                Delete
            </button>
            <div class="ds-formbar__actions">
                <a href="{{ route('competitions.view', $competition->id) }}" class="ds-btn ds-btn--line">Cancel</a>
                <button type="submit" class="ds-btn ds-btn--flight">Save changes</button>
            </div>
        </div>
    </form>

    <form id="delete-competition-form" method="POST" action="{{ route('competitions.destroy', $competition->id) }}" class="hidden" onsubmit="return confirm('Are you sure you want to delete this competition?');">
        @csrf
        @method('DELETE')
    </form>
</div>
@endsection

@push('scripts')
<script>
    (() => {
        const type = document.getElementById('competition_type');
        const format = document.getElementById('format_select');
        const selectedFormat = "{{ old('format', $competition->format) }}";
        const formats = {
            singles: [['stroke_play', 'Stroke Play'], ['match_play', 'Match Play'], ['stableford', 'Stableford']],
            doubles: [['doubles_match_play', 'Doubles Match Play'], ['doubles_best_disc', 'Best Disc'], ['doubles_team', 'Team Competition']]
        };
        function updateFormats() {
            const options = formats[type.value] || formats.singles;
            format.innerHTML = options.map(([value, label]) => `<option value="${value}">${label}</option>`).join('');
            format.value = options.some(([value]) => value === selectedFormat) ? selectedFormat : options[0][0];
        }
        type.addEventListener('change', updateFormats);
        updateFormats();
        const divisionRules = document.getElementById('division-rules');
        let divisionRuleIndex = Number("{{ count($customDivisionRules) }}");
        document.getElementById('add-division-rule').addEventListener('click', () => {
            const row = document.createElement('div');
            row.className = 'competition-division-rule';
            row.innerHTML = `<input name="division_rules[${divisionRuleIndex}][name]" placeholder="Division name" maxlength="40" required><select name="division_rules[${divisionRuleIndex}][gender]"><option value="any">Any gender</option><option value="male">Male only</option><option value="female">Female only</option></select><input type="number" name="division_rules[${divisionRuleIndex}][min_age]" min="0" max="120" placeholder="Min age"><input type="number" name="division_rules[${divisionRuleIndex}][max_age]" min="0" max="120" placeholder="Max age"><input type="number" name="division_rules[${divisionRuleIndex}][min_rating]" min="0" max="1100" placeholder="Recommended rating"><button type="button" class="remove-division-rule" aria-label="Remove division">&times;</button>`;
            row.querySelector('.remove-division-rule').addEventListener('click', () => row.remove());
            divisionRules.appendChild(row);
            divisionRuleIndex++;
        });
        document.querySelectorAll('.remove-division-rule').forEach(button => button.addEventListener('click', () => button.parentElement.remove()));
    })();
</script>
@endpush
