{{--
    Scorecard editor shared by training rounds and competitions.
    Expects: $action, $backUrl, $holes (number, par, distance_m), $players (id, name, label?),
    $scores ([hole id][user id] => strokes|null), $intro, optional $courseName.
--}}
@if($errors->any())
    <div class="ds-flash ds-flash--err !items-start" role="alert">
        <span class="ds-flash__icon">!</span>
        <ul class="space-y-0.5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $action }}" class="space-y-5" id="round-editor">
    @csrf
    @method('PUT')

    <section class="ds-card">
        <div class="ds-card__head">
            <div>
                <p class="ds-eyebrow">Edit round</p>
                <h2 class="ds-card__title mt-2">Scorecard</h2>
            </div>
        </div>
        <div class="ds-card__body space-y-5">
            <p class="text-sm text-gray-500">{{ $intro }}</p>

            @isset($courseName)
                <label class="ds-field max-w-md">
                    <span class="ds-field__label">Course name</span>
                    <input type="text" name="course_name" value="{{ old('course_name', $courseName) }}" required maxlength="255" class="ds-field__input">
                </label>
            @endisset

            <div class="overflow-x-auto rounded-2xl border border-gray-200">
                <table class="w-full min-w-max text-sm">
                    <thead class="bg-gray-50 text-left font-mono text-[0.66rem] uppercase tracking-widest text-gray-500">
                        <tr>
                            <th class="px-3 py-2">Hole</th>
                            <th class="px-2 py-2">Par</th>
                            <th class="px-2 py-2">Dist (m)</th>
                            @foreach($players as $player)
                                <th class="px-2 py-2 normal-case tracking-normal">
                                    <span class="block max-w-[7rem] truncate font-sans text-xs font-bold text-ink" title="{{ $player['name'] }}">{{ $player['name'] }}</span>
                                    @if(!empty($player['label']))
                                        <span class="block text-[0.62rem] text-gray-400">{{ $player['label'] }}</span>
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($holes as $hole)
                            <tr>
                                <td class="px-3 py-1.5 font-display font-extrabold text-ink">{{ $hole->number }}</td>
                                <td class="px-2 py-1.5">
                                    <input type="number" name="holes[{{ $hole->id }}][par]" value="{{ old("holes.{$hole->id}.par", $hole->par) }}" min="1" max="10" required data-par
                                        class="w-14 rounded-lg border border-gray-300 px-2 py-1 text-center">
                                </td>
                                <td class="px-2 py-1.5">
                                    <input type="number" name="holes[{{ $hole->id }}][distance_m]" value="{{ old("holes.{$hole->id}.distance_m", $hole->distance_m) }}" min="1" max="2000"
                                        class="w-20 rounded-lg border border-gray-300 px-2 py-1 text-center">
                                </td>
                                @foreach($players as $player)
                                    <td class="px-2 py-1.5">
                                        <input type="number" name="scores[{{ $hole->id }}][{{ $player['id'] }}]" value="{{ old("scores.{$hole->id}.{$player['id']}", $scores[$hole->id][$player['id']] ?? null) }}"
                                            min="1" max="{{ \App\Models\TrainingRoundShot::MAX_HOLE_SCORE }}" placeholder="–" data-score="{{ $player['id'] }}"
                                            class="w-14 rounded-lg border border-gray-300 px-2 py-1 text-center font-bold">
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 font-bold text-ink">
                        <tr>
                            <td class="px-3 py-2 font-mono text-[0.66rem] uppercase tracking-widest text-gray-500">Total</td>
                            <td class="px-2 py-2 text-center" data-par-total></td>
                            <td></td>
                            @foreach($players as $player)
                                <td class="px-2 py-2 text-center" data-score-total="{{ $player['id'] }}"></td>
                            @endforeach
                        </tr>
                    </tfoot>
                </table>
            </div>

            <p class="text-xs text-gray-500">Leave a box empty for a hole that wasn't played. A changed score replaces that hole's shot-by-shot log with just the score.</p>
        </div>
    </section>

    <div class="flex flex-wrap items-center justify-end gap-2">
        <a href="{{ $backUrl }}" class="ds-btn ds-btn--line">Cancel</a>
        <button type="submit" class="ds-btn ds-btn--flight">Save changes</button>
    </div>
</form>

@push('scripts')
<script>
    (() => {
        const form = document.getElementById('round-editor');
        const sum = (inputs) => [...inputs].reduce((total, input) => total + (parseInt(input.value, 10) || 0), 0);
        const update = () => {
            form.querySelector('[data-par-total]').textContent = sum(form.querySelectorAll('[data-par]'));
            form.querySelectorAll('[data-score-total]').forEach((cell) => {
                const id = cell.dataset.scoreTotal;
                const total = sum(form.querySelectorAll(`[data-score="${id}"]`));
                cell.textContent = total || '–';
            });
        };
        form.addEventListener('input', update);
        update();
    })();
</script>
@endpush
