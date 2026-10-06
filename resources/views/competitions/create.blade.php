@extends('layouts.dashboard')

@section('title', 'Create Competition')

@section('content')
@php
    $check = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>';
@endphp
<div class="mx-auto max-w-4xl space-y-6">
    <a href="{{ route('competitions.index') }}" class="ds-back">
        <svg fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M15 19l-7-7 7-7"/></svg>
        Competitions
    </a>

    <section class="ds-hero ds-hero--form">
        <p class="ds-eyebrow">New tournament</p>
        <h1 class="mt-4">Host your<br><em>next event.</em></h1>
        <p class="mt-3 max-w-lg text-sm sm:text-base">Set the date, pick a course and open registration. Players are drawn onto cards {{ \App\Models\Competition::GROUPS_DRAWN_MINUTES_BEFORE }} minutes before tee off.</p>
        <nav class="ds-hero__steps mt-6" aria-label="Form sections">
            <a href="#basics"><b>1</b>Basics</a>
            <a href="#when-where"><b>2</b>When &amp; where</a>
            <a href="#format"><b>3</b>Format</a>
            <a href="#registration"><b>4</b>Registration</a>
        </nav>
    </section>

    <form method="POST" action="{{ route('competitions.store') }}" class="ds-formstack">
        @csrf

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
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required
                        class="ds-field__input @error('name') is-invalid @enderror" placeholder="Summer Open 2026">
                    @error('name') <p class="ds-field__error">{{ $message }}</p> @enderror
                </div>
                <div class="ds-field is-wide">
                    <label for="description" class="ds-field__label">Description</label>
                    <textarea name="description" id="description" rows="4"
                        class="ds-field__input @error('description') is-invalid @enderror" placeholder="Rules, prizes, schedule…">{{ old('description') }}</textarea>
                    @error('description') <p class="ds-field__error">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <section id="when-where" class="ds-section">
            <div class="ds-section__head">
                <span class="ds-section__num" style="--accent: #0ea5e9">02</span>
                <div>
                    <h2 class="ds-section__title">When &amp; where</h2>
                    <p class="ds-section__text">Pick a course from the map to fill in the location and hole count.</p>
                </div>
            </div>
            <div class="ds-fieldgrid">
                <div class="ds-field">
                    <label for="event_date" class="ds-field__label">Event date <i>*</i></label>
                    <input type="date" name="event_date" id="event_date" value="{{ old('event_date') }}" required
                        class="ds-field__input @error('event_date') is-invalid @enderror">
                    @error('event_date') <p class="ds-field__error">{{ $message }}</p> @enderror
                </div>
                <div class="ds-field">
                    <label for="start_time" class="ds-field__label">Tee off time <i>*</i></label>
                    <input type="time" name="start_time" id="start_time" value="{{ old('start_time', \App\Models\Competition::DEFAULT_START_TIME) }}" required
                        class="ds-field__input @error('start_time') is-invalid @enderror">
                    <p class="ds-field__hint">Scoring opens at tee off.</p>
                    @error('start_time') <p class="ds-field__error">{{ $message }}</p> @enderror
                </div>

                <div class="competition-course-picker is-wide">
                    <div class="competition-course-picker__heading">
                        <div>
                            <span class="competition-course-picker__eyebrow">COURSE FINDER</span>
                            <h4>Choose a course from the map</h4>
                            <p id="competition-course-status">Allow location access for nearby recommendations.</p>
                        </div>
                        <button type="button" id="competition-near-me" class="competition-course-picker__nearby">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="1.5"/></svg>
                            Use my location
                        </button>
                    </div>
                    <div class="competition-course-picker__tools">
                        <input type="search" id="competition-course-search" placeholder="Search course names" autocomplete="off">
                        <input type="text" id="competition-country-search" list="competition-country-options" placeholder="Search country for farther courses" autocomplete="off">
                        <datalist id="competition-country-options">
                            <option data-code="GB" value="United Kingdom"></option>
                            <option data-code="US" value="United States"></option>
                            <option data-code="CA" value="Canada"></option>
                            <option data-code="LV" value="Latvia"></option>
                            <option data-code="DE" value="Germany"></option>
                            <option data-code="SE" value="Sweden"></option>
                            <option data-code="FI" value="Finland"></option>
                            <option data-code="AU" value="Australia"></option>
                            <option data-code="NZ" value="New Zealand"></option>
                            <option data-code="FR" value="France"></option>
                            <option data-code="ES" value="Spain"></option>
                            <option data-code="IT" value="Italy"></option>
                        </datalist>
                        <button type="button" id="competition-country-load">Search country</button>
                    </div>
                    <div class="competition-course-picker__body">
                        <div id="competition-course-list" class="competition-course-picker__list">
                            <p>Nearby courses will appear here.</p>
                        </div>
                        <div id="competition-course-map" class="competition-course-picker__map" aria-label="Choose a competition course on the map"></div>
                    </div>
                </div>

                <div class="ds-field">
                    <label for="course_name" class="ds-field__label">Course name</label>
                    <input type="text" name="course_name" id="course_name" value="{{ old('course_name') }}"
                        class="ds-field__input @error('course_name') is-invalid @enderror" placeholder="Mežaparks Disc Golf Course">
                    @error('course_name') <p class="ds-field__error">{{ $message }}</p> @enderror
                </div>
                <div class="ds-field">
                    <label for="location" class="ds-field__label">Location</label>
                    <input type="text" name="location" id="location" value="{{ old('location') }}"
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
                        <option value="singles" {{ old('competition_type', 'singles') == 'singles' ? 'selected' : '' }}>Singles</option>
                        <option value="doubles" {{ old('competition_type') == 'doubles' ? 'selected' : '' }}>Doubles</option>
                    </select>
                </div>
                <div class="ds-field">
                    <label for="format_select" class="ds-field__label">Format <i>*</i></label>
                    <select name="format" id="format_select" required class="ds-field__input @error('format') is-invalid @enderror"></select>
                    @error('format') <p class="ds-field__error">{{ $message }}</p> @enderror
                </div>
                <div class="ds-field">
                    <label for="holes" class="ds-field__label">Holes <i>*</i></label>
                    <input type="number" name="holes" id="holes" min="1" max="99" required value="{{ old('holes', 18) }}"
                        class="ds-field__input @error('holes') is-invalid @enderror">
                    @error('holes') <p class="ds-field__error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="ds-subsection">
                <p class="ds-field__label">Divisions</p>
                <input type="hidden" name="divisions" value="">
                <div class="ds-choices ds-choices--4">
                    @foreach([
                        'MPO' => 'Open · any rating', 'MA1' => 'Rated 929 or below', 'MA2' => 'Rated 879 or below',
                        'MA3' => 'Rated 819 or below', 'MA4' => 'Rated 749 or below', 'FPO' => 'Women only · any rating',
                        'FA2' => 'Women only · 879 or below', 'FA3' => 'Women only · 819 or below', 'FA4' => 'Women only · 749 or below',
                        'MP60' => 'Age 60+', 'MP50' => 'Age 50+', 'MP40' => 'Age 40+',
                        'FP40' => 'Women only · age 40+', 'MJ18' => 'Boys · under 18', 'MJ15' => 'Boys · under 15', 'FJ18' => 'Girls · under 18'
                    ] as $division => $threshold)
                        <label class="ds-choice">
                            <input type="checkbox" name="division_options[]" value="{{ $division }}" {{ in_array($division, old('division_options', []), true) ? 'checked' : '' }}>
                            <span class="ds-choice__check">{!! $check !!}</span>
                            <span class="ds-choice__body"><strong>{{ $division }}</strong><small>{{ $threshold }}</small></span>
                        </label>
                    @endforeach
                </div>
                <p class="ds-field__hint">Amateur divisions are capped by rating: players can play up, but not down. Unrated players can enter any division.</p>
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
                <div id="division-rules" class="mt-3 space-y-2"></div>
            </div>
        </section>

        <section id="registration" class="ds-section">
            <div class="ds-section__head">
                <span class="ds-section__num" style="--accent: #8b5cf6">04</span>
                <div>
                    <h2 class="ds-section__title">Registration</h2>
                    <p class="ds-section__text">Fees, limits and who can find the event.</p>
                </div>
            </div>
            <div class="ds-fieldgrid">
                <div class="ds-field">
                    <label for="entry_fee" class="ds-field__label">Entry fee</label>
                    <div class="ds-inputgroup">
                        <input type="number" name="entry_fee" id="entry_fee" value="{{ old('entry_fee', 0) }}" step="0.01" min="0"
                            class="ds-field__input @error('entry_fee') is-invalid @enderror" placeholder="0.00">
                        <select name="currency" aria-label="Currency" class="ds-field__input">
                            <option value="EUR" {{ old('currency') == 'EUR' ? 'selected' : '' }}>EUR</option>
                            <option value="USD" {{ old('currency') == 'USD' ? 'selected' : '' }}>USD</option>
                            <option value="GBP" {{ old('currency') == 'GBP' ? 'selected' : '' }}>GBP</option>
                        </select>
                    </div>
                    @error('entry_fee') <p class="ds-field__error">{{ $message }}</p> @enderror
                </div>
                <div class="ds-field">
                    <label for="max_participants" class="ds-field__label">Max participants</label>
                    <input type="number" name="max_participants" id="max_participants" value="{{ old('max_participants') }}" min="1"
                        class="ds-field__input @error('max_participants') is-invalid @enderror" placeholder="Unlimited">
                    @error('max_participants') <p class="ds-field__error">{{ $message }}</p> @enderror
                </div>
                <div class="ds-field">
                    <label for="registration_deadline" class="ds-field__label">Registration deadline</label>
                    <input type="datetime-local" name="registration_deadline" id="registration_deadline" value="{{ old('registration_deadline') }}"
                        class="ds-field__input @error('registration_deadline') is-invalid @enderror">
                    @error('registration_deadline') <p class="ds-field__error">{{ $message }}</p> @enderror
                </div>
                <div class="ds-field">
                    <label for="registration_link" class="ds-field__label">External registration link</label>
                    <input type="url" name="registration_link" id="registration_link" value="{{ old('registration_link') }}"
                        class="ds-field__input @error('registration_link') is-invalid @enderror" placeholder="https://…">
                    @error('registration_link') <p class="ds-field__error">{{ $message }}</p> @enderror
                </div>
                <label class="ds-togglecard is-wide">
                    <span>
                        <strong>Public competition</strong>
                        <small>Turn off to keep it private — only reachable via direct link.</small>
                    </span>
                    <span class="ds-ftoggle">
                        <input type="checkbox" name="is_public" id="is_public" value="1" {{ old('is_public', '1') ? 'checked' : '' }}>
                        <span class="ds-ftoggle__track"><span></span></span>
                    </span>
                </label>
            </div>
        </section>

        <div class="ds-formbar">
            <p class="ds-formbar__note">Fields marked <span class="font-bold text-flight">*</span> are required.</p>
            <div class="ds-formbar__actions">
                <a href="{{ route('competitions.index') }}" class="ds-btn ds-btn--line">Cancel</a>
                <button type="submit" class="ds-btn ds-btn--flight">
                    Create competition
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
    (() => {
        const map = L.map('competition-course-map').setView([30, 0], 2);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            subdomains: ['a', 'b', 'c'],
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        const courseLayer = L.layerGroup().addTo(map);
        const list = document.getElementById('competition-course-list');
        const status = document.getElementById('competition-course-status');
        const search = document.getElementById('competition-course-search');
        const countrySearch = document.getElementById('competition-country-search');
        const countryOptions = [...document.querySelectorAll('#competition-country-options option')];
        let courses = [];

        function escapeHtml(value) {
            const element = document.createElement('div');
            element.textContent = value || '';
            return element.innerHTML;
        }

        function selectCourse(course) {
            document.getElementById('course_name').value = course.name;
            if (course.address || course.locality) document.getElementById('location').value = course.address || course.locality;
            if (course.holes) document.getElementById('holes').value = course.holes;
            map.flyTo([course.lat, course.lon], 13, { duration: 0.6 });
        }

        function renderCourses() {
            const query = search.value.trim().toLowerCase();
            const visible = courses.filter(course => course.name.toLowerCase().includes(query));
            courseLayer.clearLayers();
            list.innerHTML = '';
            if (!visible.length) {
                list.innerHTML = '<p class="competition-course-picker__empty">No matching courses found.</p>';
                return;
            }

            visible.forEach(course => {
                const marker = L.marker([course.lat, course.lon]).addTo(courseLayer);
                marker.bindPopup(`<strong>${escapeHtml(course.name)}</strong><br>${escapeHtml(course.locality || 'Location from OpenStreetMap')}`);
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'competition-course-picker__course';
                button.innerHTML = `<strong>${escapeHtml(course.name)}</strong><small>${escapeHtml(course.locality || 'Location unavailable')} ${course.distance_km ? `· ${course.distance_km} km away` : ''}</small>`;
                button.addEventListener('click', () => { selectCourse(course); marker.openPopup(); });
                list.appendChild(button);
            });
        }

        // Only one course request at a time; leaving the page cancels it so navigation isn't held up
        let courseRequest = null;
        window.addEventListener('pagehide', () => courseRequest?.abort());

        async function loadCourses(parameters, label) {
            courseRequest?.abort();
            courseRequest = new AbortController();
            const { signal } = courseRequest;
            status.textContent = `Finding ${label}...`;
            list.innerHTML = '<p class="competition-course-picker__empty">Loading courses… the first search for a country can take up to half a minute.</p>';
            try {
                const response = await fetch(`/courses/data?${new URLSearchParams(parameters)}`, { signal });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(data.message || 'Course search is temporarily unavailable.');
                courses = (data.courses || []).filter(course => Number.isFinite(Number(course.lat)) && Number.isFinite(Number(course.lon)));
                status.textContent = `${courses.length} courses available${parameters.lat ? ' within 150 km' : ''}`;
                renderCourses();
                if (courses.length) map.fitBounds(courses.map(course => [course.lat, course.lon]), { padding: [24, 24], maxZoom: parameters.lat ? 10 : 7 });
            } catch (error) {
                if (error.name === 'AbortError') return;
                status.textContent = 'Course search is temporarily unavailable.';
                const note = document.createElement('p');
                note.className = 'competition-course-picker__empty';
                note.textContent = error.message;
                list.replaceChildren(note);
            }
        }

        function requestNearbyCourses() {
            if (!navigator.geolocation) { status.textContent = 'Location is not supported by this browser.'; return; }
            status.textContent = 'Requesting your location...';
            navigator.geolocation.getCurrentPosition(
                position => loadCourses({ lat: position.coords.latitude, lon: position.coords.longitude }, 'nearby courses'),
                () => { status.textContent = 'Location permission was not granted.'; },
                { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 }
            );
        }

        document.getElementById('competition-near-me').addEventListener('click', requestNearbyCourses);
        document.getElementById('competition-country-load').addEventListener('click', () => {
            const option = countryOptions.find(item => item.value.toLowerCase() === countrySearch.value.trim().toLowerCase());
            if (option) loadCourses({ country: option.dataset.code }, `${option.value} courses`);
            else status.textContent = 'Choose a country from the suggestions.';
        });
        search.addEventListener('input', renderCourses);
        const type = document.getElementById('competition_type');
        const format = document.getElementById('format_select');
        const selectedFormat = "{{ old('format', 'stroke_play') }}";
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
        let divisionRuleIndex = 0;
        function addDivisionRule() {
            const row = document.createElement('div');
            row.className = 'competition-division-rule';
            row.innerHTML = `<input name="division_rules[${divisionRuleIndex}][name]" placeholder="Division name" maxlength="40" required><select name="division_rules[${divisionRuleIndex}][gender]"><option value="any">Any gender</option><option value="male">Male only</option><option value="female">Female only</option></select><input type="number" name="division_rules[${divisionRuleIndex}][min_age]" min="0" max="120" placeholder="Min age"><input type="number" name="division_rules[${divisionRuleIndex}][max_age]" min="0" max="120" placeholder="Max age"><input type="number" name="division_rules[${divisionRuleIndex}][min_rating]" min="0" max="1100" placeholder="Min rating"><button type="button" class="remove-division-rule" aria-label="Remove division">&times;</button>`;
            row.querySelector('.remove-division-rule').addEventListener('click', () => row.remove());
            divisionRules.appendChild(row);
            divisionRuleIndex++;
        }
        document.getElementById('add-division-rule').addEventListener('click', addDivisionRule);
        setTimeout(() => map.invalidateSize(), 100);
        requestNearbyCourses();
    })();
</script>
@endpush
