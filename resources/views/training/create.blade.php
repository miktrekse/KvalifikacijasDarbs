@extends('layouts.dashboard')

@section('title', 'Start Training Round')

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <a href="{{ route('training.index') }}" class="ds-back">
        <svg fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M15 19l-7-7 7-7"/></svg>
        Training rounds
    </a>

    <section class="ds-hero ds-hero--form">
        <p class="ds-eyebrow">New round</p>
        <h1 class="mt-4">Tee up a<br><em>training round.</em></h1>
        <p class="mt-3 max-w-lg text-sm sm:text-base">Pick a course, invite the players you're throwing with, then track every shot hole by hole.</p>
        <nav class="ds-hero__steps mt-6" aria-label="Form sections">
            <a href="#course"><b>1</b>Course</a>
            <a href="#players"><b>2</b>Players</a>
        </nav>
    </section>

    @if($errors->any())
        <div class="ds-flash ds-flash--err mx-auto max-w-[56rem] !items-start" role="alert">
            <span class="ds-flash__icon">!</span>
            <ul class="space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('training.store') }}" class="ds-formstack">
        @csrf

        <section id="course" class="ds-section">
            <div class="ds-section__head">
                <span class="ds-section__num" style="--accent: #22a268">01</span>
                <div>
                    <h2 class="ds-section__title">Course</h2>
                    <p class="ds-section__text">Verified layouts bring real par and distances for every hole.</p>
                </div>
            </div>

            <input type="hidden" name="course_lat" id="course_lat" value="{{ old('course_lat') }}">
            <input type="hidden" name="course_lon" id="course_lon" value="{{ old('course_lon') }}">
            <input type="hidden" name="course_locality" id="course_locality" value="{{ old('course_locality') }}">
            <input type="hidden" name="holes_data" id="holes_data" value="{{ old('holes_data') }}">

            <div class="ds-fieldgrid">
                <div class="competition-course-picker is-wide">
                    <div class="competition-course-picker__heading">
                        <div>
                            <span class="competition-course-picker__eyebrow">COURSE FINDER</span>
                            <h4>Choose a course from the map</h4>
                            <p id="training-course-status">Allow location access for nearby recommendations.</p>
                        </div>
                        <button type="button" id="training-near-me" class="competition-course-picker__nearby">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="1.5"/></svg>
                            Use my location
                        </button>
                    </div>
                    <div class="competition-course-picker__tools">
                        <input type="search" id="training-course-search" placeholder="Search course names" autocomplete="off">
                        <input type="text" id="training-country-search" list="training-country-options" placeholder="Search country for farther courses" autocomplete="off">
                        <datalist id="training-country-options">
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
                        <button type="button" id="training-country-load">Search country</button>
                    </div>
                    <div class="competition-course-picker__body">
                        <div id="training-course-list" class="competition-course-picker__list">
                            <p>Nearby courses will appear here.</p>
                        </div>
                        <div id="training-course-map" class="competition-course-picker__map" aria-label="Choose a course on the map"></div>
                    </div>
                </div>

                <div class="ds-field">
                    <label for="course_name" class="ds-field__label">Course name <i>*</i></label>
                    <input type="text" name="course_name" id="course_name" value="{{ old('course_name') }}" required
                        class="ds-field__input @error('course_name') is-invalid @enderror" placeholder="Mežaparks Disc Golf Course">
                </div>
                <div class="ds-field">
                    <label for="holes_count" class="ds-field__label">Holes <i>*</i></label>
                    <input type="number" name="holes_count" id="holes_count" min="1" max="36" required value="{{ old('holes_count', 18) }}"
                        class="ds-field__input @error('holes_count') is-invalid @enderror">
                    <p class="ds-field__hint">No basket details online? Every hole starts as a Par 3, 100m.</p>
                </div>

                <div class="training-layout-picker is-wide" id="training-layout-picker" hidden></div>
            </div>
        </section>

        <section id="players" class="ds-section">
            <div class="ds-section__head">
                <span class="ds-section__num" style="--accent: #f26b3a">02</span>
                <div>
                    <h2 class="ds-section__title">Who's playing?</h2>
                    <p class="ds-section__text">Optional &mdash; you can also log a solo round.</p>
                </div>
            </div>

            <div class="mb-3 flex flex-wrap items-center gap-2" id="training-player-chips">
                <span class="ds-invite is-you">
                    <span class="ds-avatar">
                        @if(Auth::user()->avatar)
                            <img src="{{ asset('storage/' . Auth::user()->avatar) }}" alt="">
                        @else
                            <span>{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</span>
                        @endif
                    </span>
                    {{ Auth::user()->name }} <small>(you)</small>
                </span>
            </div>

            <div class="relative">
                <div class="ds-filters__search">
                    <svg class="h-4 w-4 shrink-0 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
                    <input type="search" id="training-player-search" autocomplete="off" placeholder="Search players by name…" aria-label="Search players">
                </div>
                <div id="training-player-results" class="ds-results hidden"></div>
            </div>
        </section>

        <div class="ds-formbar">
            <p class="ds-formbar__note">Fields marked <span class="font-bold text-flight">*</span> are required.</p>
            <div class="ds-formbar__actions">
                <a href="{{ route('training.index') }}" class="ds-btn ds-btn--line">Cancel</a>
                <button type="submit" class="ds-btn ds-btn--flight">
                    Start round
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
        const map = L.map('training-course-map').setView([30, 0], 2);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            subdomains: ['a', 'b', 'c'],
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        const courseLayer = L.layerGroup().addTo(map);
        const list = document.getElementById('training-course-list');
        const status = document.getElementById('training-course-status');
        const search = document.getElementById('training-course-search');
        const countrySearch = document.getElementById('training-country-search');
        const countryOptions = [...document.querySelectorAll('#training-country-options option')];
        let courses = [];

        function escapeHtml(value) {
            const element = document.createElement('div');
            element.textContent = value || '';
            return element.innerHTML;
        }

        function selectCourse(course) {
            document.getElementById('course_name').value = course.name;
            document.getElementById('course_lat').value = course.lat;
            document.getElementById('course_lon').value = course.lon;
            document.getElementById('course_locality').value = course.address || course.locality || '';
            if (course.holes) document.getElementById('holes_count').value = course.holes;
            renderLayoutPicker(course.layouts || []);
            map.flyTo([course.lat, course.lon], 13, { duration: 0.6 });
        }

        function applyLayout(layout) {
            document.getElementById('holes_count').value = layout.holes_count;
            document.getElementById('holes_data').value = JSON.stringify((layout.hole_details || []).map(hole => ({
                number: hole.number,
                par: hole.par,
                distance_m: hole.length_m,
            })));
            const summary = document.getElementById('training-layout-summary');
            if (summary) summary.textContent = `${layout.holes_count} holes · Par ${layout.par}`;
        }

        function renderLayoutPicker(layouts) {
            const container = document.getElementById('training-layout-picker');
            if (!layouts.length) {
                container.hidden = true;
                container.innerHTML = '';
                document.getElementById('holes_data').value = '';
                return;
            }

            container.hidden = false;
            const tabs = layouts.length > 1
                ? `<div class="training-layout-picker__tabs">${layouts.map((layout, i) => `<button type="button" class="training-layout-picker__tab${i === 0 ? ' is-active' : ''}" data-layout-index="${i}">${escapeHtml(layout.name)}</button>`).join('')}</div>`
                : `<p class="training-layout-picker__single">${escapeHtml(layouts[0].name)}</p>`;
            container.innerHTML = `<span class="training-layout-picker__label">Verified layout &mdash; real par &amp; distances will be used</span>${tabs}<p class="training-layout-picker__summary" id="training-layout-summary"></p>`;

            const tabButtons = [...container.querySelectorAll('.training-layout-picker__tab')];
            tabButtons.forEach(tab => {
                tab.addEventListener('click', () => {
                    tabButtons.forEach(t => t.classList.remove('is-active'));
                    tab.classList.add('is-active');
                    applyLayout(layouts[Number(tab.dataset.layoutIndex)]);
                });
            });

            applyLayout(layouts[0]);
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

        document.getElementById('training-near-me').addEventListener('click', requestNearbyCourses);
        document.getElementById('training-country-load').addEventListener('click', () => {
            const option = countryOptions.find(item => item.value.toLowerCase() === countrySearch.value.trim().toLowerCase());
            if (option) loadCourses({ country: option.dataset.code }, `${option.value} courses`);
            else status.textContent = 'Choose a country from the suggestions.';
        });
        search.addEventListener('input', renderCourses);
        setTimeout(() => map.invalidateSize(), 100);
        requestNearbyCourses();

        // Player search & invite chips
        const playerSearchInput = document.getElementById('training-player-search');
        const playerResults = document.getElementById('training-player-results');
        const playerChips = document.getElementById('training-player-chips');
        const selectedPlayers = new Map();
        let searchTimeout = null;

        function avatarHtml(player) {
            return `<span class="ds-avatar">${player.avatar_url ? `<img src="${escapeHtml(player.avatar_url)}" alt="">` : `<span>${escapeHtml(player.name.charAt(0).toUpperCase())}</span>`}</span>`;
        }

        function renderChips() {
            [...playerChips.querySelectorAll('[data-player-chip]')].forEach(chip => chip.remove());
            selectedPlayers.forEach((player, id) => {
                const chip = document.createElement('span');
                chip.dataset.playerChip = id;
                chip.className = 'ds-invite';
                chip.innerHTML = `${avatarHtml(player)} ${escapeHtml(player.name)} <button type="button" aria-label="Remove">&times;</button><input type="hidden" name="player_ids[]" value="${id}">`;
                chip.querySelector('button').addEventListener('click', () => {
                    selectedPlayers.delete(id);
                    renderChips();
                });
                playerChips.appendChild(chip);
            });
        }

        function renderPlayerResults(players) {
            playerResults.innerHTML = '';
            if (!players.length) {
                playerResults.classList.add('hidden');
                return;
            }
            players.forEach(player => {
                if (selectedPlayers.has(player.id)) return;
                const row = document.createElement('button');
                row.type = 'button';
                row.innerHTML = `${avatarHtml(player)} ${escapeHtml(player.name)}`;
                row.addEventListener('click', () => {
                    selectedPlayers.set(player.id, player);
                    renderChips();
                    playerResults.classList.add('hidden');
                    playerSearchInput.value = '';
                });
                playerResults.appendChild(row);
            });
            playerResults.classList.toggle('hidden', playerResults.children.length === 0);
        }

        playerSearchInput.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            const query = playerSearchInput.value.trim();
            if (query.length < 2) { playerResults.classList.add('hidden'); return; }
            searchTimeout = setTimeout(async () => {
                try {
                    const response = await fetch(`{{ route('training.players.search') }}?q=${encodeURIComponent(query)}`);
                    const data = await response.json();
                    renderPlayerResults(data.players || []);
                } catch (error) {
                    playerResults.classList.add('hidden');
                }
            }, 250);
        });

        document.addEventListener('click', (event) => {
            if (!playerResults.contains(event.target) && event.target !== playerSearchInput) {
                playerResults.classList.add('hidden');
            }
        });
    })();
</script>
@endpush
