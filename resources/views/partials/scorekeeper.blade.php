{{--
    Shot-by-shot scorekeeper shared by competitions and training rounds:
    "who are you scoring for" picker, hole entry, UDisc-style scorecard and stats.

    Expects $scoring with players, holes, startingHole, scoringOpen, closed,
    startsAt, closedMessage, storageKey, shotUrl / undoUrl (with __HOLE__
    placeholder) and an optional dataUrl that is polled for other scorers' throws.
--}}
    {{-- Step 1: choose who you are keeping score for --}}
    <section id="picker" class="ds-card hidden">
        <div class="ds-card__head">
            <div>
                <h2 class="ds-card__title">Who are you scoring for?</h2>
                <p class="mt-1 text-sm text-gray-500">Pick the players whose throws you'll enter on this phone. Everyone's scores stay visible on the scorecard.</p>
            </div>
        </div>
        <div class="ds-card__body">
            <div id="picker-list" class="grid gap-2 sm:grid-cols-2"></div>
            <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
                <button type="button" id="picker-toggle-all" class="text-sm font-bold text-indigo-700 hover:text-indigo-900">Select everyone</button>
                <button type="button" id="picker-start" class="ds-btn ds-btn--flight">
                    Start scoring
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </button>
            </div>
        </div>
    </section>

    {{-- Step 2: scoring --}}
    <section id="scoring" class="hidden space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="ds-tabs" role="tablist">
                <button type="button" data-tab="score" class="is-active">Score</button>
                <button type="button" data-tab="card">Scorecard</button>
                <button type="button" data-tab="stats">Stats</button>
            </div>
            <button type="button" id="change-players" class="text-sm font-bold text-gray-500 hover:text-indigo-700">
                Scoring for <span id="scoring-for-count"></span> · change
            </button>
        </div>

        <div id="closed-note" class="ds-flash ds-flash--err hidden"><span class="ds-flash__icon">!</span><span></span></div>

        {{-- Score tab --}}
        <div data-panel="score" class="space-y-4">
            <div class="ds-card overflow-hidden">
                <div class="flex items-center justify-between gap-3 p-4 sm:p-5">
                    <button type="button" id="prev-hole" class="ds-hole-nav" aria-label="Previous hole">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <div class="text-center">
                        <p class="font-mono text-[0.65rem] font-bold uppercase tracking-[0.2em] text-gray-400">Hole</p>
                        <p class="font-display text-5xl font-extrabold leading-none tracking-tight text-ink" id="hole-number">1</p>
                        <p class="mt-1 text-sm font-semibold text-gray-500">Par <span id="hole-par">3</span><span id="hole-distance"></span></p>
                    </div>
                    <button type="button" id="next-hole" class="ds-hole-nav" aria-label="Next hole">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
                <div id="hole-strip" class="ds-hole-strip"></div>
            </div>

            <div id="hole-players" class="grid gap-4 md:grid-cols-2"></div>

            <button type="button" id="advance-hole" class="ds-submit hidden">
                Next hole
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </button>
        </div>

        {{-- Scorecard tab --}}
        <div data-panel="card" class="hidden">
            <div class="ds-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="ds-scorecard" id="scorecard"></table>
                </div>
                <div class="flex flex-wrap gap-x-4 gap-y-2 border-t border-dashed border-line px-5 py-3 text-xs font-semibold text-gray-500">
                    <span class="flex items-center gap-1.5"><span class="sc sc--eagle sc--sm">2</span>Eagle+</span>
                    <span class="flex items-center gap-1.5"><span class="sc sc--birdie sc--sm">3</span>Birdie</span>
                    <span class="flex items-center gap-1.5"><span class="sc sc--par sc--sm">4</span>Par</span>
                    <span class="flex items-center gap-1.5"><span class="sc sc--bogey sc--sm">5</span>Bogey</span>
                    <span class="flex items-center gap-1.5"><span class="sc sc--double sc--sm">6</span>Double+</span>
                </div>
            </div>
        </div>

        {{-- Stats tab --}}
        <div data-panel="stats" class="hidden space-y-4">
            <div id="stats-players" class="flex flex-wrap gap-2"></div>
            <div id="stats-body"></div>
        </div>
    </section>
</div>

<div id="toast" class="ds-toast" role="status" aria-live="polite"></div>

@push('scripts')
<script>
(() => {
    const state = @json($scoring);
    const csrf = '{{ csrf_token() }}';
    const storageKey = state.storageKey;

    const RESULTS = [
        { key: 'fairway', label: 'Fairway', short: 'FW', cls: 'is-fairway' },
        { key: 'off_fairway', label: 'Off fairway', short: 'Rough', cls: 'is-rough' },
        { key: 'circle_2', label: 'Circle 2', short: 'C2', cls: 'is-c2' },
        { key: 'circle_1', label: 'Circle 1', short: 'C1', cls: 'is-c1' },
        { key: 'out_of_bounds', label: 'OB', short: 'OB', cls: 'is-ob' },
        { key: 'in_basket', label: 'In the basket', short: 'In', cls: 'is-in' },
    ];
    const resultByKey = Object.fromEntries(RESULTS.map(r => [r.key, r]));
    const SCORE_NAMES = { '-3': 'Albatross', '-2': 'Eagle', '-1': 'Birdie', '0': 'Par', '1': 'Bogey', '2': 'Double bogey', '3': 'Triple bogey' };

    // Holes in the order this card plays them (shotgun start wraps around).
    const playOrder = () => {
        const start = state.holes.findIndex(h => h.number === state.startingHole);
        return start <= 0 ? state.holes : [...state.holes.slice(start), ...state.holes.slice(0, start)];
    };

    // Nothing to choose when you're the only player
    let selectedIds = state.players.length === 1 ? [state.players[0].id] : loadSelection();
    let activeHoleId = null;
    let activeTab = 'score';
    let statsPlayerId = (state.players.find(p => p.isMe) || state.players[0] || {}).id;
    let pending = 0;

    // ---------- helpers ----------
    const $ = id => document.getElementById(id);
    const esc = value => { const el = document.createElement('div'); el.textContent = value ?? ''; return el.innerHTML; };
    const shotsOf = (hole, userId) => hole.shots[userId] || [];
    const isFinished = (hole, userId) => { const s = shotsOf(hole, userId); return s.length > 0 && s[s.length - 1].result === 'in_basket'; };
    const holeStrokes = (hole, userId) => shotsOf(hole, userId).reduce((sum, s) => sum + s.strokes, 0);
    const fmtRel = rel => rel === 0 ? 'E' : (rel > 0 ? '+' + rel : String(rel));
    const relClass = rel => rel < 0 ? 'is-under' : rel > 0 ? 'is-over' : 'is-even';
    const scoreClass = (strokes, par) => {
        if (strokes === 1) return 'sc--ace';
        const rel = strokes - par;
        return rel <= -2 ? 'sc--eagle' : rel === -1 ? 'sc--birdie' : rel === 0 ? 'sc--par' : rel === 1 ? 'sc--bogey' : 'sc--double';
    };
    const avatar = (player, size = 'h-10 w-10') => player.avatarUrl
        ? `<img src="${player.avatarUrl}" alt="" class="${size} shrink-0 rounded-full object-cover">`
        : `<span class="ds-avatar ${size} shrink-0"><span>${esc(player.name.charAt(0).toUpperCase())}</span></span>`;
    const coursePar = state.holes.reduce((sum, h) => sum + h.par, 0);

    function totals(userId) {
        let strokes = 0, par = 0, thru = 0;
        state.holes.forEach(hole => {
            if (!isFinished(hole, userId)) return;
            strokes += holeStrokes(hole, userId);
            par += hole.par;
            thru++;
        });
        return { strokes, rel: strokes - par, thru };
    }

    function toast(message) {
        const el = $('toast');
        el.textContent = message;
        el.classList.add('is-visible');
        clearTimeout(toast.timer);
        toast.timer = setTimeout(() => el.classList.remove('is-visible'), 3200);
    }

    // ---------- player picker ----------
    function loadSelection() {
        try {
            const saved = JSON.parse(localStorage.getItem(storageKey) || 'null');
            if (Array.isArray(saved)) return saved.filter(id => state.players.some(p => p.id === id));
        } catch (e) {}
        return null;
    }

    function saveSelection() {
        try { localStorage.setItem(storageKey, JSON.stringify(selectedIds)); } catch (e) {}
    }

    function renderPicker() {
        const current = selectedIds || state.players.map(p => p.id);
        $('picker-list').innerHTML = state.players.map(player => `
            <label class="ds-pick">
                <input type="checkbox" value="${player.id}" ${current.includes(player.id) ? 'checked' : ''}>
                ${avatar(player)}
                <span class="min-w-0 flex-1">
                    <span class="block truncate font-bold text-ink">${esc(player.name)}${player.isMe ? ' <span class="text-xs font-semibold text-indigo-600">(you)</span>' : ''}</span>
                    <span class="block font-mono text-[0.68rem] uppercase tracking-wider text-gray-500">${esc(player.division)}</span>
                </span>
                <span class="ds-pick__check">✓</span>
            </label>`).join('');
    }

    function showPicker() {
        renderPicker();
        $('picker').classList.remove('hidden');
        $('scoring').classList.add('hidden');
    }

    function showScoring() {
        $('picker').classList.add('hidden');
        $('scoring').classList.remove('hidden');
        $('change-players').classList.toggle('hidden', state.players.length === 1);
        $('scoring-for-count').textContent = selectedIds.length === state.players.length
            ? 'everyone'
            : selectedIds.length + ' player' + (selectedIds.length === 1 ? '' : 's');
        renderAll();
    }

    $('picker-toggle-all').addEventListener('click', () => {
        const boxes = [...$('picker-list').querySelectorAll('input')];
        const allOn = boxes.every(b => b.checked);
        boxes.forEach(b => { b.checked = !allOn; });
    });

    $('picker-start').addEventListener('click', () => {
        const ids = [...$('picker-list').querySelectorAll('input:checked')].map(b => Number(b.value));
        if (!ids.length) { toast('Pick at least one player to score for.'); return; }
        selectedIds = ids;
        saveSelection();
        activeHoleId = firstOpenHole().id;
        showScoring();
    });

    $('change-players').addEventListener('click', showPicker);

    // ---------- score tab ----------
    function currentHole() {
        return state.holes.find(h => h.id === activeHoleId) || playOrder()[0];
    }

    function firstOpenHole() {
        return playOrder().find(hole => !selectedIds.every(id => isFinished(hole, id))) || playOrder()[playOrder().length - 1];
    }

    function moveHole(step) {
        const order = playOrder();
        const index = order.findIndex(h => h.id === currentHole().id);
        const next = order[index + step];
        if (next) { activeHoleId = next.id; renderAll(); window.scrollTo({ top: $('scoring').offsetTop - 90, behavior: 'smooth' }); }
    }

    function renderHoleStrip() {
        const hole = currentHole();
        $('hole-strip').innerHTML = playOrder().map(h => {
            const done = state.players.every(p => isFinished(h, p.id));
            const started = state.players.some(p => shotsOf(h, p.id).length);
            return `<button type="button" data-hole="${h.id}" class="${h.id === hole.id ? 'is-active' : ''} ${done ? 'is-done' : started ? 'is-started' : ''}">${h.number}</button>`;
        }).join('');
        $('hole-strip').querySelectorAll('[data-hole]').forEach(btn => btn.addEventListener('click', () => { activeHoleId = Number(btn.dataset.hole); renderAll(); }));
        $('hole-strip').querySelector('.is-active')?.scrollIntoView({ block: 'nearest', inline: 'center' });
    }

    function renderScoreTab() {
        const hole = currentHole();
        const order = playOrder();
        const index = order.findIndex(h => h.id === hole.id);
        $('hole-number').textContent = hole.number;
        $('hole-par').textContent = hole.par;
        $('hole-distance').textContent = hole.distanceM ? ` · ${hole.distanceM} m` : '';
        $('prev-hole').disabled = index === 0;
        $('next-hole').disabled = index === order.length - 1;
        renderHoleStrip();

        const canScore = state.scoringOpen;
        const players = state.players.filter(p => selectedIds.includes(p.id));

        $('hole-players').innerHTML = players.map(player => {
            const shots = shotsOf(hole, player.id);
            const strokes = holeStrokes(hole, player.id);
            const finished = isFinished(hole, player.id);
            const t = totals(player.id);
            const rel = strokes - hole.par;
            const scoreName = finished ? (strokes === 1 ? 'Ace!' : SCORE_NAMES[rel] || (rel > 0 ? '+' + rel : rel)) : (shots.length ? 'In play' : 'On the tee');
            const lie = shots.length ? resultByKey[shots[shots.length - 1].result] : null;

            return `<article class="ds-player ${finished ? 'is-finished' : ''}" data-player="${player.id}">
                <header class="flex items-center gap-3">
                    ${avatar(player)}
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-bold text-ink">${esc(player.name)}</p>
                        <p class="font-mono text-[0.68rem] uppercase tracking-wider text-gray-500">
                            <span class="ds-rel ${relClass(t.rel)}">${t.thru ? fmtRel(t.rel) : '—'}</span>
                            ${t.thru ? 'thru ' + t.thru : esc(player.division)}
                        </p>
                    </div>
                    <div class="text-right">
                        <span class="sc ${finished ? scoreClass(strokes, hole.par) : 'sc--pending'} sc--lg">${strokes || '–'}</span>
                        <p class="mt-1 text-[0.7rem] font-bold uppercase tracking-wider text-gray-500">${scoreName}</p>
                    </div>
                </header>
                <div class="ds-shotlog">
                    ${shots.map(s => `<span class="ds-chip ${resultByKey[s.result]?.cls || ''}">${s.shotNumber}<b>${resultByKey[s.result]?.short || s.result}</b></span>`).join('')
                      || '<span class="text-xs text-gray-400">Tap where the drive landed.</span>'}
                </div>
                ${finished ? '' : `<p class="mb-2 text-xs font-semibold text-gray-500">${lie ? 'Shot ' + (shots.length + 1) + ' from ' + lie.label.toLowerCase() : 'Drive'}</p>`}
                ${finished ? '' : `<div class="ds-shots">
                    ${RESULTS.map(r => `<button type="button" data-result="${r.key}" class="ds-shot ${r.cls}" ${canScore ? '' : 'disabled'}>${r.label}</button>`).join('')}
                </div>`}
                <button type="button" data-undo class="mt-3 text-xs font-bold text-gray-400 hover:text-red-600 disabled:opacity-40" ${!canScore || !shots.length ? 'disabled' : ''}>Undo last throw</button>
            </article>`;
        }).join('');

        $('hole-players').querySelectorAll('[data-player]').forEach(card => {
            const userId = Number(card.dataset.player);
            card.querySelectorAll('[data-result]').forEach(btn => btn.addEventListener('click', () => addShot(hole, userId, btn.dataset.result)));
            card.querySelector('[data-undo]').addEventListener('click', () => undoShot(hole, userId));
        });

        const allDone = players.length && players.every(p => isFinished(hole, p.id));
        $('advance-hole').classList.toggle('hidden', !allDone || index === order.length - 1);
    }

    async function send(url, body) {
        pending++;
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(body),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(data.message || 'Could not save — check your connection.');
            return data;
        } finally {
            pending--;
        }
    }

    async function addShot(hole, userId, result) {
        try {
            const data = await send(state.shotUrl.replace('__HOLE__', hole.id), { user_id: userId, result });
            (hole.shots[userId] = hole.shots[userId] || []).push({ shotNumber: data.shot.shotNumber ?? data.shot.shot_number, result: data.shot.result, strokes: data.shot.strokes });
            renderAll();
            if (result === 'in_basket' && navigator.vibrate) navigator.vibrate(30);
        } catch (e) {
            toast(e.message);
            refresh();
        }
    }

    async function undoShot(hole, userId) {
        try {
            await send(state.undoUrl.replace('__HOLE__', hole.id), { user_id: userId });
            shotsOf(hole, userId).pop();
            renderAll();
        } catch (e) {
            toast(e.message);
            refresh();
        }
    }

    // ---------- scorecard tab ----------
    function renderScorecard() {
        const holes = state.holes;
        const head = `<thead>
            <tr><th class="ds-sticky">Hole</th>${holes.map(h => `<th>${h.number}</th>`).join('')}<th class="ds-total">Tot</th><th class="ds-total">+/-</th></tr>
            <tr class="ds-par"><td class="ds-sticky">Par</td>${holes.map(h => `<td>${h.par}</td>`).join('')}<td class="ds-total">${coursePar}</td><td class="ds-total"></td></tr>
        </thead>`;

        const ranked = [...state.players].sort((a, b) => {
            const ta = totals(a.id), tb = totals(b.id);
            return (tb.thru > 0) - (ta.thru > 0) || ta.rel - tb.rel;
        });

        const body = ranked.map(player => {
            const t = totals(player.id);
            return `<tr>
                <td class="ds-sticky"><span class="flex items-center gap-2">${avatar(player, 'h-7 w-7')}<span class="max-w-[8rem] truncate">${esc(player.name)}</span></span></td>
                ${holes.map(h => {
                    const strokes = holeStrokes(h, player.id);
                    if (!strokes) return '<td><span class="sc sc--empty">·</span></td>';
                    return `<td><span class="sc ${isFinished(h, player.id) ? scoreClass(strokes, h.par) : 'sc--pending'}">${strokes}</span></td>`;
                }).join('')}
                <td class="ds-total font-bold">${t.thru ? t.strokes : '–'}</td>
                <td class="ds-total"><span class="ds-rel ${relClass(t.rel)}">${t.thru ? fmtRel(t.rel) : '–'}</span></td>
            </tr>`;
        }).join('');

        $('scorecard').innerHTML = head + `<tbody>${body}</tbody>`;
    }

    // ---------- stats tab (UDisc-style) ----------
    function playerStats(userId) {
        const holes = state.holes.filter(h => isFinished(h, userId));
        const dist = { eagle: 0, birdie: 0, par: 0, bogey: 0, double: 0 };
        let fairwayHits = 0, c1r = 0, c2r = 0, obs = 0, scrambles = 0, scrambleChances = 0;
        let c1Attempts = 0, c1Makes = 0, c2Attempts = 0, c2Makes = 0, aces = 0;

        holes.forEach(hole => {
            const shots = shotsOf(hole, userId);
            const strokes = holeStrokes(hole, userId);
            const rel = strokes - hole.par;
            if (strokes === 1) aces++;
            dist[rel <= -2 ? 'eagle' : rel === -1 ? 'birdie' : rel === 0 ? 'par' : rel === 1 ? 'bogey' : 'double']++;

            if (['fairway', 'circle_2', 'circle_1', 'in_basket'].includes(shots[0].result)) fairwayHits++;

            // "In regulation" = in the circle with a putt left for birdie
            let running = 0, hitC1 = false, hitC2 = false, troubled = false;
            shots.forEach((shot, i) => {
                running += shot.strokes;
                const inReg = running <= hole.par - 2;
                if (inReg && ['circle_1', 'in_basket'].includes(shot.result)) hitC1 = true;
                if (inReg && ['circle_2', 'circle_1', 'in_basket'].includes(shot.result)) hitC2 = true;
                if (shot.result === 'out_of_bounds') obs++;
                if (['out_of_bounds', 'off_fairway'].includes(shot.result)) troubled = true;
                const lie = i > 0 ? shots[i - 1].result : null;
                if (lie === 'circle_1') { c1Attempts++; if (shot.result === 'in_basket') c1Makes++; }
                if (lie === 'circle_2') { c2Attempts++; if (shot.result === 'in_basket') c2Makes++; }
            });
            if (hitC1) c1r++;
            if (hitC2) c2r++;
            if (troubled) { scrambleChances++; if (rel <= 0) scrambles++; }
        });

        const t = totals(userId);
        return { holes: holes.length, dist, fairwayHits, c1r, c2r, obs, scrambles, scrambleChances, c1Attempts, c1Makes, c2Attempts, c2Makes, aces, ...t };
    }

    const pct = (n, d) => d ? Math.round((n / d) * 100) : null;

    function ring(label, value, detail, color) {
        const v = value ?? 0;
        return `<div class="ds-ring-stat">
            <div class="ds-ring" style="--p:${v};--c:${color}"><span>${value === null ? '–' : v + '%'}</span></div>
            <p class="mt-2 text-sm font-bold text-ink">${label}</p>
            <p class="font-mono text-[0.68rem] text-gray-500">${detail}</p>
        </div>`;
    }

    function renderStats() {
        $('stats-players').innerHTML = state.players.map(p =>
            `<button type="button" data-stats-player="${p.id}" class="ds-tabchip ${p.id === statsPlayerId ? 'is-active' : ''}">${esc(p.name)}</button>`).join('');
        $('stats-players').querySelectorAll('[data-stats-player]').forEach(btn =>
            btn.addEventListener('click', () => { statsPlayerId = Number(btn.dataset.statsPlayer); renderStats(); }));

        const player = state.players.find(p => p.id === statsPlayerId);
        if (!player) { $('stats-body').innerHTML = ''; return; }
        const s = playerStats(player.id);

        if (!s.holes) {
            $('stats-body').innerHTML = `<div class="ds-card p-10 text-center text-sm text-gray-500">No finished holes yet for ${esc(player.name)}. Stats appear after the first hole is holed out.</div>`;
            return;
        }

        const distRows = [
            ['Eagle+', s.dist.eagle, '#f5b700'], ['Birdie', s.dist.birdie, '#22a268'], ['Par', s.dist.par, '#a39d8e'],
            ['Bogey', s.dist.bogey, '#f26b3a'], ['Double+', s.dist.double, '#b91c1c'],
        ];

        $('stats-body').innerHTML = `
            <div class="grid gap-4 lg:grid-cols-[1fr_1.4fr]">
                <div class="ds-card p-5">
                    <p class="ds-stat__label">Round score</p>
                    <div class="mt-2 flex items-end gap-3">
                        <span class="ds-rel ds-rel--xl ${relClass(s.rel)}">${fmtRel(s.rel)}</span>
                        <span class="pb-1 text-sm font-semibold text-gray-500">${s.strokes} throws · thru ${s.thru}/${state.holes.length}</span>
                    </div>
                    <div class="mt-5 flex h-3 overflow-hidden rounded-full bg-gray-100">
                        ${distRows.map(([, n, c]) => n ? `<span style="width:${(n / s.holes) * 100}%;background:${c}"></span>` : '').join('')}
                    </div>
                    <dl class="mt-4 grid grid-cols-5 gap-2 text-center">
                        ${distRows.map(([label, n, c]) => `<div><dt class="text-[0.62rem] font-bold uppercase tracking-wide text-gray-500">${label}</dt><dd class="font-display text-2xl font-extrabold" style="color:${c}">${n}</dd></div>`).join('')}
                    </dl>
                    <div class="mt-4 grid grid-cols-3 gap-2 border-t border-dashed border-line pt-4 text-center">
                        <div><p class="ds-stat__label">OB</p><p class="font-display text-xl font-extrabold text-ink">${s.obs}</p></div>
                        <div><p class="ds-stat__label">Avg / hole</p><p class="font-display text-xl font-extrabold text-ink">${(s.strokes / s.holes).toFixed(1)}</p></div>
                        <div><p class="ds-stat__label">Aces</p><p class="font-display text-xl font-extrabold text-ink">${s.aces}</p></div>
                    </div>
                </div>
                <div class="ds-card p-5">
                    <p class="ds-stat__label">Driving &amp; approach</p>
                    <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3">
                        ${ring('Fairway hits', pct(s.fairwayHits, s.holes), `${s.fairwayHits}/${s.holes}`, '#22a268')}
                        ${ring('C1 in reg.', pct(s.c1r, s.holes), `${s.c1r}/${s.holes}`, '#0ea5e9')}
                        ${ring('C2 in reg.', pct(s.c2r, s.holes), `${s.c2r}/${s.holes}`, '#8b5cf6')}
                    </div>
                    <p class="ds-stat__label mt-6">Putting &amp; recovery</p>
                    <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3">
                        ${ring('C1 putting', pct(s.c1Makes, s.c1Attempts), `${s.c1Makes}/${s.c1Attempts}`, '#f26b3a')}
                        ${ring('C2 putting', pct(s.c2Makes, s.c2Attempts), `${s.c2Makes}/${s.c2Attempts}`, '#eab308')}
                        ${ring('Scramble', pct(s.scrambles, s.scrambleChances), `${s.scrambles}/${s.scrambleChances}`, '#14b8a6')}
                    </div>
                </div>
            </div>`;
    }

    // ---------- shell ----------
    function renderLivePill() {
        const pill = $('live-pill');
        if (!pill) return;
        if (state.closed) { pill.textContent = 'Final'; pill.className = 'ds-score-live is-final'; return; }
        if (state.scoringOpen) { pill.innerHTML = '<i></i>Live'; pill.className = 'ds-score-live is-live'; return; }
        const ms = new Date(state.startsAt) - new Date();
        if (ms <= 0) { refresh(); return; }
        const m = Math.floor(ms / 60000), s = Math.floor((ms % 60000) / 1000);
        pill.textContent = `Tee off in ${m}:${String(s).padStart(2, '0')}`;
        pill.className = 'ds-score-live';
    }

    function renderClosedNote() {
        const note = $('closed-note');
        const show = !state.scoringOpen;
        note.classList.toggle('hidden', !show);
        if (show) note.querySelector('span:last-child').textContent = state.closed
            ? state.closedMessage
            : `Scoring opens at tee off (${new Date(state.startsAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}). You can already pick who you're scoring for.`;
    }

    function renderAll() {
        if (!selectedIds) return;
        renderLivePill();
        renderClosedNote();
        if (activeTab === 'score') renderScoreTab();
        if (activeTab === 'card') renderScorecard();
        if (activeTab === 'stats') renderStats();
    }

    document.querySelectorAll('[data-tab]').forEach(btn => btn.addEventListener('click', () => {
        activeTab = btn.dataset.tab;
        document.querySelectorAll('[data-tab]').forEach(b => b.classList.toggle('is-active', b === btn));
        document.querySelectorAll('[data-panel]').forEach(p => p.classList.toggle('hidden', p.dataset.panel !== activeTab));
        renderAll();
    }));
    $('prev-hole').addEventListener('click', () => moveHole(-1));
    $('next-hole').addEventListener('click', () => moveHole(1));
    $('advance-hole').addEventListener('click', () => moveHole(1));

    // Pull the card's latest throws so scores entered by others on the card show up.
    async function refresh() {
        if (pending || !state.dataUrl) return;
        try {
            const response = await fetch(state.dataUrl, { headers: { 'Accept': 'application/json' } });
            if (!response.ok || pending) return;
            const data = await response.json();
            state.holes = data.holes;
            state.scoringOpen = data.scoringOpen;
            renderAll();
        } catch (e) {}
    }

    if ($('course-par')) $('course-par').textContent = coursePar;
    if (state.dataUrl) setInterval(refresh, 10000);
    renderLivePill();
    setInterval(() => { if (!state.scoringOpen) renderLivePill(); }, 1000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });

    if (selectedIds && selectedIds.length) {
        activeHoleId = firstOpenHole().id;
        showScoring();
    } else {
        selectedIds = null;
        showPicker();
    }
})();
</script>
@endpush

