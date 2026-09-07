@extends('layouts.dashboard')

@section('title', 'Course Finder')

@section('content')
<div class="course-finder">
    <header class="course-finder__header">
        <div>
            <p class="course-finder__eyebrow">DISC GOLF DIRECTORY</p>
            <h1>Find your next round</h1>
            <p class="course-finder__intro">Explore courses across the world with live locations and essential details.</p>
        </div>
        <div class="course-finder__status" id="course-status" aria-live="polite">
            <span class="course-finder__status-dot"></span>
            <span id="course-status-text">Loading courses</span>
        </div>
    </header>

    <section class="course-finder__toolbar" aria-label="Course filters">
        <label class="course-finder__search">
            <span class="sr-only">Search courses</span>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 21-4.35-4.35m2.1-5.4a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z" /></svg>
            <input id="course-search" type="search" placeholder="Search by course or town" autocomplete="off">
        </label>
        <label class="course-finder__select">
            <span>Country</span>
            <input id="country-filter" list="country-options" value="" placeholder="Search country" autocomplete="off">
            <datalist id="country-options">
                <option data-code="GB" value="United Kingdom"></option>
                <option data-code="US" value="United States"></option>
                <option data-code="CA" value="Canada"></option>
                <option data-code="AU" value="Australia"></option>
                <option data-code="DE" value="Germany"></option>
                <option data-code="SE" value="Sweden"></option>
                <option data-code="FI" value="Finland"></option>
                <option data-code="NL" value="Netherlands"></option>
                <option data-code="NZ" value="New Zealand"></option>
                <option data-code="NO" value="Norway"></option>
                <option data-code="DK" value="Denmark"></option>
                <option data-code="AT" value="Austria"></option>
                <option data-code="CH" value="Switzerland"></option>
                <option data-code="FR" value="France"></option>
                <option data-code="ES" value="Spain"></option>
                <option data-code="IT" value="Italy"></option>
                <option data-code="IE" value="Ireland"></option>
                <option data-code="LV" value="Latvia"></option>
                <option data-code="World" value="World"></option>
            </datalist>
        </label>
        <button type="button" class="course-finder__location" id="locate-me" title="Center map on my location">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2v3m0 14v3M2 12h3m14 0h3m-4.5 0a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0Z" /></svg>
            Near me
        </button>
    </section>

    <div class="course-finder__workspace">
        <aside class="course-finder__list-panel">
            <div class="course-finder__list-heading">
                <div>
                    <span class="course-finder__label">COURSES</span>
                    <strong id="course-count">0 found</strong>
                </div>
                <span class="course-finder__hint">Select a pin or course</span>
            </div>
            <div class="course-finder__list" id="course-list"></div>
        </aside>
        <div class="course-finder__map-wrap">
            <div id="course-map" aria-label="Map of disc golf courses"></div>
            <div class="course-finder__map-note">Map and course data © OpenStreetMap contributors</div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
    const courseDataUrl = '/courses/data';
    const map = L.map('course-map', { zoomControl: false, worldCopyJump: true, minZoom: 2 }).setView([30, 0], 2);
    L.control.zoom({ position: 'bottomright' }).addTo(map);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        subdomains: ['a', 'b', 'c'],
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    const markerLayer = L.layerGroup().addTo(map);
    const searchInput = document.getElementById('course-search');
    const countryFilter = document.getElementById('country-filter');
    const countryOptions = [...document.querySelectorAll('#country-options option')];
    const courseList = document.getElementById('course-list');
    const courseCount = document.getElementById('course-count');
    const statusText = document.getElementById('course-status-text');
    let courseItems = [];

    function courseLabel(course) {
        return course.locality || course.region_code || course.country_code || 'Location unavailable';
    }

    function renderCourses() {
        const query = searchInput.value.trim().toLowerCase();
        const visibleCourses = courseItems.filter(course =>
            `${course.name} ${course.locality || ''} ${course.region_code || ''}`.toLowerCase().includes(query)
        );
        courseCount.textContent = `${visibleCourses.length} found`;
        markerLayer.clearLayers();
        courseList.innerHTML = '';

        if (!visibleCourses.length) {
            courseList.innerHTML = '<div class="course-finder__empty">No courses match this search.</div>';
            return;
        }

        visibleCourses.forEach(course => {
            const marker = L.circleMarker([course.lat, course.lon], {
                radius: 8, color: '#f6f2e9', weight: 3, fillColor: '#e4572e', fillOpacity: 1
            }).addTo(markerLayer);
            marker.bindPopup(coursePopup(course), { maxWidth: 290, minWidth: 240, className: 'course-popup' });

            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'course-finder__course';
            item.innerHTML = `<span class="course-finder__course-marker">${course.name.charAt(0)}</span><span><strong>${escapeHtml(course.name)}</strong><small>${escapeHtml(courseLabel(course))} · ${course.holes || 'Layout details'}${course.holes ? ' holes' : ''}</small></span><span class="course-finder__arrow">↗</span>`;
            item.addEventListener('click', () => {
                map.flyTo([course.lat, course.lon], 12, { duration: 0.8 });
                marker.openPopup();
            });
            courseList.appendChild(item);
        });
    }

    function escapeHtml(value) {
        const element = document.createElement('div');
        element.textContent = value || '';
        return element.innerHTML;
    }

    function coursePopup(course) {
        const location = courseLabel(course);
        const holes = course.holes ? `${escapeHtml(course.holes)} holes` : 'Hole count not listed';
        const website = course.website
            ? `<a class="course-popup__link" href="${escapeHtml(course.website)}" target="_blank" rel="noopener">Visit course website <span>↗</span></a>`
            : '';
        const osmLink = course.osm_url
            ? `<a class="course-popup__map-link" href="${escapeHtml(course.osm_url)}" target="_blank" rel="noopener">View on OpenStreetMap</a>`
            : '';

        return `<div class="course-popup__body"><span class="course-popup__eyebrow">DISC GOLF COURSE</span><strong class="course-popup__title">${escapeHtml(course.name)}</strong><span class="course-popup__location">${escapeHtml(location)}</span><div class="course-popup__stats"><span><b>${holes}</b><small>LAYOUT</small></span><span><b>${escapeHtml(course.country_code)}</b><small>REGION</small></span></div>${website}${osmLink}</div>`;
    }

    async function loadCourses(latitude = null, longitude = null) {
        const isNearbySearch = latitude !== null && longitude !== null;
        statusText.textContent = isNearbySearch ? 'Finding courses within 150 km' : 'Choose a country or use Near me';
        courseList.innerHTML = '<div class="course-finder__empty">Loading the course directory...</div>';
        try {
            const selectedCountry = countryOptions.find(option => option.value.toLowerCase() === countryFilter.value.trim().toLowerCase());
            if (!isNearbySearch && !selectedCountry) {
                statusText.textContent = 'Choose a country';
                courseCount.textContent = '0 found';
                markerLayer.clearLayers();
                courseList.innerHTML = '<div class="course-finder__empty">Choose a country from the search suggestions to load courses.</div>';
                return;
            }

            const countryCode = selectedCountry?.dataset.code;
            const params = isNearbySearch
                ? new URLSearchParams({ lat: latitude, lon: longitude })
                : new URLSearchParams({ country: countryCode, limit: 500 });
            const response = await fetch(`${courseDataUrl}?${params}`);
            if (!response.ok) throw new Error('Unable to load courses');
            const data = await response.json();
            courseItems = (data.courses || []).filter(course => Number.isFinite(Number(course.lat)) && Number.isFinite(Number(course.lon)));
            statusText.textContent = isNearbySearch
                ? `${data.total || courseItems.length} courses within 150 km`
                : `${data.total || courseItems.length} courses indexed`;
            renderCourses();
            if (courseItems.length && (isNearbySearch || countryCode !== 'World')) {
                map.fitBounds(courseItems.map(course => [course.lat, course.lon]), { padding: [36, 36], maxZoom: 7 });
            } else {
                map.setView([20, 0], 2);
            }
        } catch (error) {
            statusText.textContent = 'Could not load courses';
            courseList.innerHTML = '<div class="course-finder__empty">Course data is temporarily unavailable. Please try again.</div>';
        }
    }

    countryFilter.addEventListener('change', loadCourses);
    countryFilter.addEventListener('input', () => {
        const selectedCountry = countryOptions.find(option => option.value.toLowerCase() === countryFilter.value.trim().toLowerCase());
        if (selectedCountry) loadCourses();
    });
    searchInput.addEventListener('input', renderCourses);
    document.getElementById('locate-me').addEventListener('click', () => {
        if (!navigator.geolocation) {
            statusText.textContent = 'Location is not supported by this browser';
            return;
        }

        countryFilter.value = '';
        statusText.textContent = 'Requesting your location';
        navigator.geolocation.getCurrentPosition(
            position => loadCourses(position.coords.latitude, position.coords.longitude),
            () => { statusText.textContent = 'Location permission was not granted'; },
            { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 }
        );
    });
    loadCourses();
</script>
@endpush