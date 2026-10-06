{{--
    Shot-by-shot scorekeeper shared by competitions and training rounds:
    "who are you tracking stats for" picker, hole entry, UDisc-style scorecard and stats.
    Picked players are tracked shot by shot; everyone else on the card gets a quick
    per-hole score (a plain number, like UDisc) that counts for totals but not stats.

    Expects $scoring with players, holes, startingHole, scoringOpen, closed,
    startsAt, closedMessage, storageKey, shotUrl / undoUrl / scoreUrl (with __HOLE__
    placeholder) and an optional dataUrl that is polled for other scorers' throws.
--}}
    {{-- Step 1: choose who you are keeping score for --}}
    <section id="picker" class="ds-card hidden">
        <div class="ds-card__head">
            <div>
                <h2 class="ds-card__title">Who are you tracking stats for?</h2>
                <p class="mt-1 text-sm text-gray-500">Picked players are tracked throw by throw for full stats. You keep score for everyone else on the card too — just enter a number per hole, like 3 or 4.</p>
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
                Stats for <span id="scoring-for-count"></span> · change
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

            {{-- Everyone not tracked shot by shot: just the hole score --}}
            <div id="quick-scores" class="ds-card hidden">
                <div class="ds-card__head">
                    <div>
                        <h3 class="ds-card__title">Scores</h3>
                        <p class="mt-0.5 text-xs text-gray-500">Throws on this hole. Starts at par — tap it to confirm, or use − / +.</p>
                    </div>
                </div>
                <div id="quick-list" class="ds-card__body !pt-3"></div>
            </div>

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

    // Where play resumes after an OB throw (its penalty stroke is already counted)
    const OB_LIES = [
        { key: 'circle_1', label: 'Circle 1', short: 'C1' },
        { key: 'circle_2', label: 'Circle 2', short: 'C2' },
        { key: 'fairway', label: 'Fairway', short: 'FW' },
        { key: 'off_fairway', label: 'Off fairway', short: 'Rough' },
        { key: 'tee', label: 'Re-tee', short: 'Tee' },
        { key: 'drop_zone', label: 'Drop zone', short: 'DZ' },
    ];
    const obLieByKey = Object.fromEntries(OB_LIES.map(l => [l.key, l]));
    const LIE_TEXT = { tee: 'the tee', fairway: 'the fairway', off_fairway: 'off the fairway', circle_2: 'Circle 2', circle_1: 'Circle 1', drop_zone: 'the drop zone' };
    const PUTTING_LIES = ['circle_1', 'circle_2'];
    // Where the disc ended up; for OB, where play resumes
    const positionOf = shot => shot.result === 'out_of_bounds' ? (shot.obLie || null) : shot.result;
    // Where the next throw comes from (null = an older OB recorded without its lie)
    const lieFor = shots => shots.length ? positionOf(shots[shots.length - 1]) : 'tee';
    const chipLabel = shot => shot.scoreOnly ? shot.strokes + ' throws · score only' : shot.result === 'out_of_bounds'
        ? 'OB' + (shot.obLie ? '→' + obLieByKey[shot.obLie].short : '')
        : (shot.result === 'in_basket' && shot.distanceM ? 'In ' + shot.distanceM + 'm' : (resultByKey[shot.result]?.short || shot.result));
    const normaliseShot = shot => ({
        shotNumber: shot.shotNumber ?? shot.shot_number,
        result: shot.result,
        strokes: shot.strokes,
        obLie: shot.obLie ?? shot.ob_lie ?? null,
        distanceM: shot.distanceM ?? shot.distance_m ?? null,
        scoreOnly: Boolean(shot.scoreOnly ?? shot.score_only),
    });
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
    // An open follow-up question for one player's throw: { userId, holeId, type: 'ob' | 'distance' }
    let prompt = null;
    // Quick scores waiting to be saved, keyed "holeId:userId", so fast taps send one request
    const scoreTimers = new Map();

    // ---------- helpers ----------
    const $ = id => document.getElementById(id);
    const esc = value => { const el = document.createElement('div'); el.textContent = value ?? ''; return el.innerHTML; };
    const shotsOf = (hole, userId) => hole.shots[userId] || [];
    const isFinished = (hole, userId) => { const s = shotsOf(hole, userId); return s.length > 0 && s[s.length - 1].result === 'in_basket'; };
    const holeStrokes = (hole, userId) => shotsOf(hole, userId).reduce((sum, s) => sum + s.strokes, 0);
    const isScoreOnly = (hole, userId) => shotsOf(hole, userId)[0]?.scoreOnly === true;
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
            : selectedIds.length ? selectedIds.length + ' player' + (selectedIds.length === 1 ? '' : 's') : 'nobody (scores only)';
        renderAll();
    }

    $('picker-toggle-all').addEventListener('click', () => {
        const boxes = [...$('picker-list').querySelectorAll('input')];
        const allOn = boxes.every(b => b.checked);
        boxes.forEach(b => { b.checked = !allOn; });
    });

    $('picker-start').addEventListener('click', () => {
        selectedIds = [...$('picker-list').querySelectorAll('input:checked')].map(b => Number(b.value));
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
        return playOrder().find(hole => !state.players.every(p => isFinished(hole, p.id))) || playOrder()[playOrder().length - 1];
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
            const lie = lieFor(shots);
            const asking = prompt && prompt.userId === player.id && prompt.holeId === hole.id ? prompt.type : null;
            const shotArea = asking === 'ob'
                ? `<div class="ds-prompt">
                        <p class="ds-prompt__title">Out of bounds — where is the next throw from?</p>
                        <div class="ds-shots ds-shots--3">${OB_LIES.map(l => `<button type="button" data-ob-lie="${l.key}" class="ds-shot is-oblie">${l.label}</button>`).join('')}</div>
                        <button type="button" data-cancel class="ds-prompt__cancel">Cancel</button>
                    </div>`
                : asking === 'distance'
                ? `<form class="ds-prompt" data-distance-form>
                        <p class="ds-prompt__title">In from ${LIE_TEXT[lie] || 'distance'}! How far was the throw?</p>
                        <div class="flex items-center gap-2">
                            <input type="number" name="distance" min="1" max="300" inputmode="numeric" placeholder="e.g. 18" class="ds-prompt__input" aria-label="Distance in metres">
                            <span class="font-bold text-gray-500">m</span>
                            <button type="submit" class="ds-btn ds-btn--flight !py-2">Save</button>
                        </div>
                        <div class="mt-2 flex gap-4">
                            <button type="button" data-skip class="ds-prompt__cancel">Skip distance</button>
                            <button type="button" data-cancel class="ds-prompt__cancel">Cancel</button>
                        </div>
                    </form>`
                : `<div class="ds-shots">
                        ${RESULTS.map(r => `<button type="button" data-result="${r.key}" class="ds-shot ${r.cls}" ${canScore ? '' : 'disabled'}>${r.label}</button>`).join('')}
                    </div>`;

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
                    ${shots.map(s => `<span class="ds-chip ${s.scoreOnly ? '' : resultByKey[s.result]?.cls || ''}">${s.scoreOnly ? '' : s.shotNumber}<b>${esc(chipLabel(s))}</b></span>`).join('')
                      || '<span class="text-xs text-gray-400">Tap where the drive landed.</span>'}
                </div>
                ${finished ? '' : `<p class="mb-2 text-xs font-semibold text-gray-500">${shots.length ? 'Shot ' + (shots.length + 1) + ' from ' + (LIE_TEXT[lie] || 'after OB') : 'Drive from the tee'}</p>`}
                ${finished ? '' : shotArea}
                <button type="button" data-undo class="mt-3 text-xs font-bold text-gray-400 hover:text-red-600 disabled:opacity-40" ${!canScore || !shots.length ? 'disabled' : ''}>Undo last throw</button>
            </article>`;
        }).join('');

        $('hole-players').querySelectorAll('[data-player]').forEach(card => {
            const userId = Number(card.dataset.player);
            card.querySelectorAll('[data-result]').forEach(btn => btn.addEventListener('click', () => chooseResult(hole, userId, btn.dataset.result)));
            card.querySelectorAll('[data-ob-lie]').forEach(btn => btn.addEventListener('click', () => {
                prompt = null;
                addShot(hole, userId, 'out_of_bounds', { ob_lie: btn.dataset.obLie });
            }));
            card.querySelector('[data-cancel]')?.addEventListener('click', () => { prompt = null; renderAll(); });
            const distanceForm = card.querySelector('[data-distance-form]');
            if (distanceForm) {
                distanceForm.addEventListener('submit', event => {
                    event.preventDefault();
                    const metres = parseInt(distanceForm.distance.value, 10);
                    prompt = null;
                    addShot(hole, userId, 'in_basket', metres > 0 ? { distance_m: Math.min(metres, 300) } : {});
                });
                distanceForm.querySelector('[data-skip]').addEventListener('click', () => { prompt = null; addShot(hole, userId, 'in_basket'); });
                distanceForm.distance.focus();
            }
            card.querySelector('[data-undo]').addEventListener('click', () => undoShot(hole, userId));
        });

        renderQuickScores(hole, canScore);

        const allDone = state.players.every(p => isFinished(hole, p.id));
        $('advance-hole').classList.toggle('hidden', !allDone || index === order.length - 1);
    }

    function renderQuickScores(hole, canScore) {
        const players = state.players.filter(p => !selectedIds.includes(p.id));
        $('quick-scores').classList.toggle('hidden', !players.length);
        $('quick-list').innerHTML = players.map(player => {
            const t = totals(player.id);
            const strokes = holeStrokes(hole, player.id);
            const finished = isFinished(hole, player.id);
            const tracked = shotsOf(hole, player.id).length && !isScoreOnly(hole, player.id);
            // Someone is logging this player throw by throw on another phone: show it, don't overwrite it
            const control = tracked
                ? `<div class="text-right">
                        <span class="sc ${finished ? scoreClass(strokes, hole.par) : 'sc--pending'}">${strokes}</span>
                        <p class="mt-0.5 text-[0.65rem] font-bold uppercase tracking-wider text-gray-400">${finished ? 'Tracked' : 'In play'}</p>
                   </div>`
                : `<div class="ds-stepper ${finished ? 'is-set' : ''}">
                        <button type="button" data-step="-1" aria-label="One less" ${canScore && (!finished || strokes > 1) ? '' : 'disabled'}>−</button>
                        <button type="button" data-step="0" class="ds-stepper__value ${finished ? scoreClass(strokes, hole.par) : ''}" aria-label="${finished ? strokes + ' throws, tap to clear' : 'Confirm par'}" ${canScore ? '' : 'disabled'}>${finished ? strokes : hole.par}</button>
                        <button type="button" data-step="1" aria-label="One more" ${canScore && strokes < {{ \App\Models\TrainingRoundShot::MAX_HOLE_SCORE }} ? '' : 'disabled'}>+</button>
                   </div>`;

            return `<div class="ds-quick" data-quick="${player.id}">
                ${avatar(player)}
                <div class="min-w-0 flex-1">
                    <p class="truncate font-bold text-ink">${esc(player.name)}${player.isMe ? ' <span class="text-xs font-semibold text-indigo-600">(you)</span>' : ''}</p>
                    <p class="font-mono text-[0.68rem] uppercase tracking-wider text-gray-500">
                        <span class="ds-rel ${relClass(t.rel)}">${t.thru ? fmtRel(t.rel) : '—'}</span>
                        ${t.thru ? 'thru ' + t.thru : esc(player.division)}
                    </p>
                </div>
                ${control}
            </div>`;
        }).join('');

        $('quick-list').querySelectorAll('[data-quick]').forEach(row => {
            const userId = Number(row.dataset.quick);
            row.querySelectorAll('[data-step]').forEach(btn => btn.addEventListener('click', () => stepScore(hole, userId, Number(btn.dataset.step))));
        });
    }

    // −/+ move from the current score (or from par when the hole is still empty); the middle
    // button confirms par on an empty hole and clears a scored one.
    function stepScore(hole, userId, step) {
        const current = isFinished(hole, userId) ? holeStrokes(hole, userId) : null;
        const next = step === 0
            ? (current ? null : hole.par)
            : Math.min(Math.max((current ?? hole.par) + step, 1), {{ \App\Models\TrainingRoundShot::MAX_HOLE_SCORE }});

        hole.shots[userId] = next ? [{ shotNumber: 1, result: 'in_basket', strokes: next, obLie: null, distanceM: null, scoreOnly: true }] : [];
        renderAll();

        const key = hole.id + ':' + userId;
        clearTimeout(scoreTimers.get(key));
        scoreTimers.set(key, setTimeout(async () => {
            try {
                await send(state.scoreUrl.replace('__HOLE__', hole.id), { user_id: userId, strokes: next });
            } catch (e) {
                toast(e.message);
                scoreTimers.delete(key);
                refresh();
                return;
            }
            scoreTimers.delete(key);
        }, 450));
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

    // OB asks where play resumes; a make from outside Circle 2 asks how far it was
    function chooseResult(hole, userId, result) {
        const lie = lieFor(shotsOf(hole, userId));
        if (result === 'out_of_bounds' || (result === 'in_basket' && !PUTTING_LIES.includes(lie))) {
            prompt = { userId, holeId: hole.id, type: result === 'out_of_bounds' ? 'ob' : 'distance' };
            renderAll();
            return;
        }
        addShot(hole, userId, result);
    }

    async function addShot(hole, userId, result, extra = {}) {
        try {
            const data = await send(state.shotUrl.replace('__HOLE__', hole.id), { user_id: userId, result, ...extra });
            (hole.shots[userId] = hole.shots[userId] || []).push(normaliseShot(data.shot));
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
        let c1Attempts = 0, c1Makes = 0, c2Attempts = 0, c2Makes = 0, aces = 0, longestIn = 0;

        let tracked = 0;
        holes.forEach(hole => {
            const shots = shotsOf(hole, userId);
            const strokes = holeStrokes(hole, userId);
            const rel = strokes - hole.par;
            if (strokes === 1) aces++;
            dist[rel <= -2 ? 'eagle' : rel === -1 ? 'birdie' : rel === 0 ? 'par' : rel === 1 ? 'bogey' : 'double']++;
            // A hole kept as a plain number has no throws to judge
            if (shots[0].scoreOnly) return;
            tracked++;

            if (['fairway', 'circle_2', 'circle_1', 'in_basket'].includes(shots[0].result)) fairwayHits++;

            // "In regulation" = in the circle with a putt left for birdie
            let running = 0, hitC1 = false, hitC2 = false, troubled = false;
            shots.forEach((shot, i) => {
                running += shot.strokes;
                const inReg = running <= hole.par - 2;
                const position = positionOf(shot);
                if (inReg && ['circle_1', 'in_basket'].includes(position)) hitC1 = true;
                if (inReg && ['circle_2', 'circle_1', 'in_basket'].includes(position)) hitC2 = true;
                if (shot.result === 'in_basket' && shot.distanceM) longestIn = Math.max(longestIn, shot.distanceM);
                if (shot.result === 'out_of_bounds') obs++;
                if (['out_of_bounds', 'off_fairway'].includes(shot.result)) troubled = true;
                const lie = i > 0 ? positionOf(shots[i - 1]) : null;
                if (lie === 'circle_1') { c1Attempts++; if (shot.result === 'in_basket') c1Makes++; }
                if (lie === 'circle_2') { c2Attempts++; if (shot.result === 'in_basket') c2Makes++; }
            });
            if (hitC1) c1r++;
            if (hitC2) c2r++;
            if (troubled) { scrambleChances++; if (rel <= 0) scrambles++; }
        });

        const t = totals(userId);
        return { holes: holes.length, tracked, dist, fairwayHits, c1r, c2r, obs, scrambles, scrambleChances, c1Attempts, c1Makes, c2Attempts, c2Makes, aces, longestIn, ...t };
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
                    <div class="mt-4 grid grid-cols-4 gap-2 border-t border-dashed border-line pt-4 text-center">
                        <div><p class="ds-stat__label">OB</p><p class="font-display text-xl font-extrabold text-ink">${s.obs}</p></div>
                        <div><p class="ds-stat__label">Avg / hole</p><p class="font-display text-xl font-extrabold text-ink">${(s.strokes / s.holes).toFixed(1)}</p></div>
                        <div><p class="ds-stat__label">Aces</p><p class="font-display text-xl font-extrabold text-ink">${s.aces}</p></div>
                        <div><p class="ds-stat__label">Longest in</p><p class="font-display text-xl font-extrabold text-ink">${s.longestIn ? s.longestIn + " m" : "–"}</p></div>
                    </div>
                </div>
                <div class="ds-card p-5">
                    ${s.tracked < s.holes ? `<p class="mb-4 rounded-xl bg-gray-50 px-3 py-2 text-xs font-semibold text-gray-500">${s.tracked ? `Shot stats cover the ${s.tracked} of ${s.holes} holes tracked throw by throw.` : `Only scores were kept for ${esc(player.name)} — shot stats need throw-by-throw tracking.`}</p>` : ''}
                    <p class="ds-stat__label">Driving &amp; approach</p>
                    <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3">
                        ${ring('Fairway hits', pct(s.fairwayHits, s.tracked), `${s.fairwayHits}/${s.tracked}`, '#22a268')}
                        ${ring('C1 in reg.', pct(s.c1r, s.tracked), `${s.c1r}/${s.tracked}`, '#0ea5e9')}
                        ${ring('C2 in reg.', pct(s.c2r, s.tracked), `${s.c2r}/${s.tracked}`, '#8b5cf6')}
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
        if (pending || scoreTimers.size || !state.dataUrl || prompt) return;
        try {
            const response = await fetch(state.dataUrl, { headers: { 'Accept': 'application/json' } });
            if (!response.ok || pending || scoreTimers.size) return;
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

    if (selectedIds) {
        activeHoleId = firstOpenHole().id;
        showScoring();
    } else {
        selectedIds = null;
        showPicker();
    }
})();
</script>
@endpush

