@extends('layouts.dashboard')

@section('title', 'Start Training Round')

@section('content')
<div class="container mx-auto px-2 sm:px-4 py-4 sm:py-8">
    <div class="max-w-full sm:max-w-3xl mx-auto">
        <div class="mb-6">
            <a href="{{ route('training.index') }}" class="text-blue-600 hover:text-blue-800 flex items-center">
                <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Back to Training Rounds
            </a>
        </div>

        <div class="bg-white rounded-lg shadow-lg p-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-1">Start a Training Round</h1>
            <p class="text-sm text-gray-500 mb-6">Pick a course, invite the players you're throwing with, then track every shot hole by hole.</p>

            @if($errors->any())
                <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg text-sm">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('training.store') }}" class="space-y-6">
                @csrf

                <div class="border-b border-gray-200 pb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Course</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                        <div class="sm:col-span-2">
                            <label for="course_name" class="block text-sm font-medium text-gray-700 mb-1">Course Name *</label>
                            <input type="text" name="course_name" id="course_name" value="{{ old('course_name') }}" required
                                class="w-full rounded-lg border-gray-300 border px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Mežaparks Disc Golf Course">
                        </div>

                        <input type="hidden" name="course_lat" id="course_lat" value="{{ old('course_lat') }}">
                        <input type="hidden" name="course_lon" id="course_lon" value="{{ old('course_lon') }}">
                        <input type="hidden" name="course_locality" id="course_locality" value="{{ old('course_locality') }}">

                        <div class="sm:col-span-2 competition-course-picker">
                            <div class="competition-course-picker__heading">
                                <div>
                                    <span class="competition-course-picker__eyebrow">COURSE FINDER</span>
                                    <h4>Choose a course from the map</h4>
                                    <p id="training-course-status">Allow location access for nearby recommendations.</p>
                                </div>
                                <button type="button" id="training-near-me" class="competition-course-picker__nearby">Use my location</button>
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

                        <div>
                            <label for="holes_count" class="block text-sm font-medium text-gray-700 mb-1">Number of Holes *</label>
                            <input type="number" name="holes_count" id="holes_count" min="1" max="36" required value="{{ old('holes_count', 18) }}"
                                class="w-full rounded-lg border-gray-300 border px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <p class="mt-1 text-xs text-gray-500">Can't find basket details online? Every hole starts as a Par 3, 100m &mdash; you can still play it your way.</p>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Who's playing?</h3>

                    <div class="flex flex-wrap items-center gap-2 mb-3" id="training-player-chips">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-100 text-indigo-800 rounded-full text-sm font-medium">
                            {{ Auth::user()->name }} <span class="text-xs text-indigo-500">(you)</span>
                        </span>
                    </div>

                    <div class="relative">
                        <input type="search" id="training-player-search" autocomplete="off" placeholder="Search players by name..."
                            class="w-full rounded-lg border-gray-300 border px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <div id="training-player-results" class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg hidden max-h-56 overflow-y-auto"></div>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Optional &mdash; you can also log a solo round.</p>
                </div>

                <div class="flex items-center justify-end pt-4">
                    <a href="{{ route('training.index') }}" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition mr-3">
                        Cancel
                    </a>
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        Start Round
                    </button>
                </div>
            </form>
        </div>
    </div>
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

        async function loadCourses(parameters, label) {
            status.textContent = `Finding ${label}...`;
            list.innerHTML = '<p class="competition-course-picker__empty">Loading courses...</p>';
            try {
                const response = await fetch(`/courses/data?${new URLSearchParams(parameters)}`);
                if (!response.ok) throw new Error('Course search failed');
                const data = await response.json();
                courses = (data.courses || []).filter(course => Number.isFinite(Number(course.lat)) && Number.isFinite(Number(course.lon)));
                status.textContent = `${courses.length} courses available${parameters.lat ? ' within 150 km' : ''}`;
                renderCourses();
                if (courses.length) map.fitBounds(courses.map(course => [course.lat, course.lon]), { padding: [24, 24], maxZoom: parameters.lat ? 10 : 7 });
            } catch (error) {
                status.textContent = 'Course search is temporarily unavailable.';
                list.innerHTML = '<p class="competition-course-picker__empty">Try again or search another country.</p>';
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

        function renderChips() {
            [...playerChips.querySelectorAll('[data-player-chip]')].forEach(chip => chip.remove());
            selectedPlayers.forEach((player, id) => {
                const chip = document.createElement('span');
                chip.dataset.playerChip = id;
                chip.className = 'inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 text-gray-800 rounded-full text-sm font-medium';
                chip.innerHTML = `${escapeHtml(player.name)} <button type="button" class="text-gray-400 hover:text-red-600 leading-none" aria-label="Remove">&times;</button><input type="hidden" name="player_ids[]" value="${id}">`;
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
                row.className = 'flex w-full items-center gap-2 px-3 py-2 text-left hover:bg-gray-50 text-sm';
                row.innerHTML = `${player.avatar_url ? `<img src="${player.avatar_url}" class="h-6 w-6 rounded-full object-cover">` : `<span class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700">${escapeHtml(player.name.charAt(0).toUpperCase())}</span>`} ${escapeHtml(player.name)}`;
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
