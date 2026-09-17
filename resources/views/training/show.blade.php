@extends('layouts.dashboard')

@section('title', $round->course_name)

@section('content')
<div class="container mx-auto px-2 sm:px-4 py-4 sm:py-8">
    <div class="mb-6">
        <a href="{{ route('training.index') }}" class="text-blue-600 hover:text-blue-800 flex items-center">
            <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Back to Training Rounds
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-gray-800">{{ $round->course_name }}</h1>
                <p class="text-sm text-gray-500">{{ $round->holes_count }} holes @if($round->course_locality) &middot; {{ $round->course_locality }} @endif</p>
            </div>
            <div class="flex items-center gap-2">
                <span id="round-status-badge" class="px-3 py-1 rounded-full text-xs font-semibold uppercase
                    @if($round->status === 'completed') bg-gray-100 text-gray-600 @else bg-green-100 text-green-700 @endif">
                    {{ $round->status === 'completed' ? 'Completed' : 'In Progress' }}
                </span>
                @if(!$round->isCompleted())
                    <form method="POST" action="{{ route('training.complete', $round->id) }}" onsubmit="return confirm('Finish this round?');">
                        @csrf
                        <button type="submit" class="px-3.5 py-1.5 bg-gray-800 text-white text-sm font-medium rounded-lg hover:bg-gray-900 transition">
                            Finish Round
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    {{-- Scorecard overview --}}
    <div class="bg-white rounded-lg shadow-sm p-4 mb-6 overflow-x-auto">
        <table class="min-w-full text-sm" id="scorecard-table">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-gray-500">
                    <th class="px-2 py-2 sticky left-0 bg-white">Hole</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    {{-- Active hole shot entry --}}
    <div class="bg-white rounded-lg shadow-md p-5">
        <div class="flex items-center justify-between gap-3 mb-5">
            <button type="button" id="prev-hole" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            </button>
            <div class="text-center">
                <p class="text-xs uppercase tracking-wide text-gray-400">Hole</p>
                <p class="text-2xl font-bold text-gray-800"><span id="active-hole-number">1</span> <span class="text-base font-normal text-gray-400">/ {{ $round->holes_count }}</span></p>
                <p class="text-sm text-gray-500">Par <span id="active-hole-par">3</span> &middot; <span id="active-hole-distance">100</span>m</p>
            </div>
            <button type="button" id="next-hole" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </button>
        </div>

        <div id="hole-players" class="space-y-5"></div>
    </div>
</div>

<template id="player-hole-template">
    <div class="border border-gray-100 rounded-lg p-4" data-player-panel>
        <div class="flex items-center justify-between gap-3 mb-3">
            <div class="flex items-center gap-2 min-w-0">
                <span data-avatar></span>
                <span class="font-semibold text-gray-800 truncate" data-player-name></span>
            </div>
            <div class="text-right shrink-0">
                <p class="text-lg font-bold text-gray-800"><span data-hole-strokes>0</span> <span class="text-xs font-normal text-gray-400">strokes</span></p>
                <p class="text-xs font-semibold" data-round-total></p>
            </div>
        </div>
        <div class="flex flex-wrap gap-1.5 mb-3" data-shot-log></div>
        <div class="grid grid-cols-3 sm:grid-cols-6 gap-1.5" data-shot-buttons>
            <button type="button" data-result="fairway" class="shot-btn bg-sky-50 text-sky-700 hover:bg-sky-100 border border-sky-200">Fairway</button>
            <button type="button" data-result="off_fairway" class="shot-btn bg-amber-50 text-amber-700 hover:bg-amber-100 border border-amber-200">Off Fairway</button>
            <button type="button" data-result="circle_2" class="shot-btn bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200">Circle 2</button>
            <button type="button" data-result="circle_1" class="shot-btn bg-teal-50 text-teal-700 hover:bg-teal-100 border border-teal-200">Circle 1</button>
            <button type="button" data-result="out_of_bounds" class="shot-btn bg-red-50 text-red-700 hover:bg-red-100 border border-red-200">OB</button>
            <button type="button" data-result="in_basket" class="shot-btn bg-emerald-600 text-white hover:bg-emerald-700 border border-emerald-600 font-semibold col-span-3 sm:col-span-1">In Basket</button>
        </div>
        <button type="button" data-undo class="mt-3 text-xs font-medium text-gray-400 hover:text-red-600">Undo last shot</button>
    </div>
</template>
@endsection

@php
    $roundData = [
        'id' => $round->id,
        'status' => $round->status,
        'holesCount' => $round->holes_count,
        'players' => $round->players->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'avatarUrl' => $p->avatar ? asset('storage/' . $p->avatar) : null,
        ]),
        'holes' => $round->holes->map(fn ($hole) => [
            'id' => $hole->id,
            'number' => $hole->number,
            'par' => $hole->par,
            'distanceM' => $hole->distance_m,
            'shots' => $hole->shots->groupBy('user_id')->map(fn ($shots) => $shots->map(fn ($shot) => [
                'shotNumber' => $shot->shot_number,
                'result' => $shot->result,
                'label' => $shot->label,
                'strokes' => $shot->strokes,
            ])->values()),
        ]),
    ];
@endphp

@push('scripts')
<script>
(() => {
    const round = @json($roundData);

    const resultLabels = {
        fairway: 'Fairway', off_fairway: 'Off Fairway', circle_2: 'C2',
        circle_1: 'C1', out_of_bounds: 'OB', in_basket: 'In',
    };
    const resultColors = {
        fairway: 'bg-sky-100 text-sky-700', off_fairway: 'bg-amber-100 text-amber-700',
        circle_2: 'bg-blue-100 text-blue-700', circle_1: 'bg-teal-100 text-teal-700',
        out_of_bounds: 'bg-red-100 text-red-700', in_basket: 'bg-emerald-100 text-emerald-700',
    };

    const isReadOnly = round.status === 'completed';
    let activeHoleIndex = 0;
    const firstUnfinished = round.holes.findIndex(hole => !round.players.every(player => holeIsFinished(hole, player.id)));
    if (firstUnfinished !== -1) activeHoleIndex = firstUnfinished;

    function holeShots(hole, userId) {
        return hole.shots[userId] || [];
    }

    function holeIsFinished(hole, userId) {
        const shots = holeShots(hole, userId);
        return shots.length > 0 && shots[shots.length - 1].result === 'in_basket';
    }

    function holeStrokes(hole, userId) {
        return holeShots(hole, userId).reduce((sum, shot) => sum + shot.strokes, 0);
    }

    function playerTotals(userId) {
        let strokes = 0, par = 0, holesPlayed = 0;
        round.holes.forEach(hole => {
            const shots = holeShots(hole, userId);
            if (!shots.length) return;
            strokes += holeStrokes(hole, userId);
            par += hole.par;
            holesPlayed += 1;
        });
        return { strokes, relative: strokes - par, holesPlayed };
    }

    function formatRelative(relative, holesPlayed) {
        if (!holesPlayed) return 'No shots yet';
        if (relative === 0) return 'E thru ' + holesPlayed;
        return (relative > 0 ? '+' : '') + relative + ' thru ' + holesPlayed;
    }

    function escapeHtml(value) {
        const el = document.createElement('div');
        el.textContent = value || '';
        return el.innerHTML;
    }

    // --- Scorecard table ---
    function renderScorecard() {
        const thead = document.querySelector('#scorecard-table thead tr');
        const tbody = document.querySelector('#scorecard-table tbody');
        thead.innerHTML = '<th class="px-2 py-2 sticky left-0 bg-white">Hole</th>' +
            round.holes.map(hole => `<th class="px-2 py-2 text-center cursor-pointer hover:text-blue-600" data-hole-jump="${hole.number - 1}">${hole.number}</th>`).join('') +
            '<th class="px-2 py-2 text-center font-bold">Total</th>';

        tbody.innerHTML = round.players.map(player => {
            const totals = playerTotals(player.id);
            const cells = round.holes.map((hole, index) => {
                const strokes = holeStrokes(hole, player.id);
                const active = index === activeHoleIndex ? 'bg-blue-50 font-semibold' : '';
                return `<td class="px-2 py-2 text-center cursor-pointer ${active}" data-hole-jump="${index}">${strokes || '&ndash;'}</td>`;
            }).join('');
            return `<tr class="border-t border-gray-100">
                <td class="px-2 py-2 sticky left-0 bg-white font-medium text-gray-700 whitespace-nowrap">${escapeHtml(player.name)}</td>
                ${cells}
                <td class="px-2 py-2 text-center font-bold text-gray-800">${totals.strokes || '&ndash;'}</td>
            </tr>`;
        }).join('');

        [...tbody.querySelectorAll('[data-hole-jump]'), ...thead.querySelectorAll('[data-hole-jump]')].forEach(cell => {
            cell.addEventListener('click', () => { activeHoleIndex = Number(cell.dataset.holeJump); renderAll(); });
        });
    }

    // --- Active hole panel ---
    const holePlayersContainer = document.getElementById('hole-players');
    const template = document.getElementById('player-hole-template');

    function renderShotLog(container, hole, player) {
        const shots = holeShots(hole, player.id);
        container.innerHTML = shots.map(shot =>
            `<span class="px-2 py-1 rounded text-xs font-medium ${resultColors[shot.result] || 'bg-gray-100 text-gray-600'}">${shot.shotNumber}. ${resultLabels[shot.result] || shot.result}</span>`
        ).join('') || '<span class="text-xs text-gray-400">No shots logged yet.</span>';
    }

    async function postShot(hole, player, result) {
        const response = await fetch(`/training/${round.id}/holes/${hole.id}/shots`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            body: JSON.stringify({ user_id: player.id, result }),
        });
        if (!response.ok) { alert('Could not save that shot. Please try again.'); return; }
        const data = await response.json();
        hole.shots[player.id] = hole.shots[player.id] || [];
        hole.shots[player.id].push({ shotNumber: data.shot.shot_number, result: data.shot.result, label: data.shot.label, strokes: data.shot.strokes });
        renderAll();
    }

    async function undoShot(hole, player) {
        const shots = holeShots(hole, player.id);
        if (!shots.length) return;
        const response = await fetch(`/training/${round.id}/holes/${hole.id}/shots/undo`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            body: JSON.stringify({ user_id: player.id }),
        });
        if (!response.ok) { alert('Could not undo that shot. Please try again.'); return; }
        shots.pop();
        renderAll();
    }

    function renderHolePanel() {
        const hole = round.holes[activeHoleIndex];
        document.getElementById('active-hole-number').textContent = hole.number;
        document.getElementById('active-hole-par').textContent = hole.par;
        document.getElementById('active-hole-distance').textContent = hole.distanceM;
        document.getElementById('prev-hole').disabled = activeHoleIndex === 0;
        document.getElementById('next-hole').disabled = activeHoleIndex === round.holes.length - 1;

        holePlayersContainer.innerHTML = '';
        round.players.forEach(player => {
            const node = template.content.cloneNode(true);
            const panel = node.querySelector('[data-player-panel]');
            const avatar = node.querySelector('[data-avatar]');
            avatar.innerHTML = player.avatarUrl
                ? `<img src="${player.avatarUrl}" class="h-8 w-8 rounded-full object-cover">`
                : `<span class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700">${escapeHtml(player.name.charAt(0).toUpperCase())}</span>`;
            node.querySelector('[data-player-name]').textContent = player.name;
            node.querySelector('[data-hole-strokes]').textContent = holeStrokes(hole, player.id);
            const totals = playerTotals(player.id);
            node.querySelector('[data-round-total]').textContent = formatRelative(totals.relative, totals.holesPlayed);

            renderShotLog(node.querySelector('[data-shot-log]'), hole, player);

            const finished = holeIsFinished(hole, player.id);
            const buttons = node.querySelectorAll('.shot-btn');
            buttons.forEach(button => {
                button.disabled = isReadOnly || finished;
                if (button.disabled) button.classList.add('opacity-40', 'cursor-not-allowed');
                button.addEventListener('click', () => postShot(hole, player, button.dataset.result));
            });

            const undoButton = node.querySelector('[data-undo]');
            undoButton.disabled = isReadOnly || holeShots(hole, player.id).length === 0;
            if (undoButton.disabled) undoButton.classList.add('opacity-40', 'cursor-not-allowed');
            undoButton.addEventListener('click', () => undoShot(hole, player));

            holePlayersContainer.appendChild(node);
        });
    }

    function renderAll() {
        renderScorecard();
        renderHolePanel();
    }

    document.getElementById('prev-hole').addEventListener('click', () => { if (activeHoleIndex > 0) { activeHoleIndex--; renderAll(); } });
    document.getElementById('next-hole').addEventListener('click', () => { if (activeHoleIndex < round.holes.length - 1) { activeHoleIndex++; renderAll(); } });

    renderAll();
})();
</script>
@endpush
