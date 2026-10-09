<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Competition;
use App\Models\CompetitionRegistration;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Models\Course;
use App\Support\RatingEngine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CompetitionController extends Controller
{
    public const STANDARD_DIVISIONS = ['MPO', 'MA1', 'MA2', 'MA3', 'MA4', 'FPO', 'FA2', 'FA3', 'FA4', 'MP60', 'MP50', 'MP40', 'FP40', 'MJ18', 'MJ15', 'FJ18'];

    /** competitions.divisions is a VARCHAR(255) holding a JSON list of names. */
    public const MAX_DIVISIONS = 20;

    /** competition_holes.number is a TINYINT and training rounds allow 36 too. */
    public const MAX_HOLES = 36;

    /** Shared by create and admin edit; every bound fits the column the value is stored in. */
    private function rules(Request $request, bool $isUpdate): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'event_date' => $isUpdate ? 'required|date' : 'required|date|after_or_equal:today',
            'start_time' => ($isUpdate ? 'nullable' : 'required') . '|date_format:H:i',
            'location' => 'nullable|string|max:255',
            'course_name' => 'nullable|string|max:255',
            'course_lat' => 'nullable|numeric|between:-90,90|required_with:course_lon',
            'course_lon' => 'nullable|numeric|between:-180,180|required_with:course_lat',
            'holes_data' => 'nullable|string|max:20000',
            'competition_type' => 'required|in:singles,doubles',
            'format' => ['required', Rule::in($request->input('competition_type') === 'doubles'
                ? ['doubles_match_play', 'doubles_best_disc', 'doubles_team']
                : ['stroke_play', 'match_play', 'stableford'])],
            'divisions' => 'nullable|string|max:255',
            'division_options' => 'nullable|array',
            'division_options.*' => Rule::in(self::STANDARD_DIVISIONS),
            'division_rules' => 'nullable|array|max:10',
            'division_rules.*' => 'array',
            'division_rules.*.name' => 'required|string|max:40',
            'division_rules.*.gender' => 'required|in:any,male,female',
            'division_rules.*.min_age' => 'nullable|integer|min:0|max:120',
            'division_rules.*.max_age' => 'nullable|integer|min:0|max:120',
            'division_rules.*.min_rating' => 'nullable|integer|min:0|max:1100',
            'holes' => 'required|integer|min:1|max:' . self::MAX_HOLES,
            'entry_fee' => 'nullable|numeric|decimal:0,2|min:0|max:99999.99',
            'currency' => 'nullable|string|size:3|alpha',
            'max_participants' => 'nullable|integer|min:1|max:1000',
            'registration_link' => 'nullable|url|max:255',
            'registration_deadline' => 'nullable|date',
            'is_public' => 'boolean',
        ] + ($isUpdate ? [
            'status' => 'required|in:upcoming,ongoing,completed,cancelled',
            'is_approved' => 'boolean',
        ] : []);
    }

    /**
     * Turns a validated form into competition columns, with the checks a single field rule
     * can't express: a division's age range, how many divisions fit, deadline before start.
     */
    private function attributes(array $validated, Request $request): array
    {
        $divisionRules = $this->normalizeDivisionRules($validated['division_rules'] ?? []);

        $divisions = $validated['division_options'] ?? [];
        if (!$divisions && !empty($validated['divisions'])) {
            $divisions = array_filter(array_map('trim', explode(',', $validated['divisions'])));
        }
        $divisions = array_values(array_unique(array_merge($divisions, array_keys($divisionRules))));

        $errors = [];
        foreach ($divisionRules as $name => $rule) {
            if ($rule['min_age'] !== null && $rule['max_age'] !== null && $rule['min_age'] > $rule['max_age']) {
                $errors['division_rules'] = "Division {$name}: the minimum age can't be higher than the maximum age.";
            }
        }
        if (count($divisions) > self::MAX_DIVISIONS || strlen(json_encode($divisions)) > 255) {
            $errors['divisions'] = 'Too many divisions — use at most ' . self::MAX_DIVISIONS . ' with short names.';
        }

        $startsAt = (new Competition(['event_date' => $validated['event_date'], 'start_time' => $validated['start_time'] ?? null]))->startsAt();
        if (!empty($validated['registration_deadline'])
            && Carbon::parse($validated['registration_deadline'], config('app.competition_timezone'))->gt($startsAt)) {
            $errors['registration_deadline'] = 'The registration deadline must be before the round starts.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'event_date' => $validated['event_date'],
            'location' => $validated['location'] ?? null,
            'course_name' => $validated['course_name'] ?? null,
            'format' => $validated['format'],
            'competition_type' => $validated['competition_type'],
            'divisions' => json_encode($divisions),
            'division_rules' => $divisionRules,
            'holes' => (int) $validated['holes'],
            'entry_fee' => $validated['entry_fee'] ?? 0,
            'currency' => strtoupper($validated['currency'] ?? 'EUR'),
            'max_participants' => $validated['max_participants'] ?? null,
            'registration_link' => $validated['registration_link'] ?? null,
            'registration_deadline' => $validated['registration_deadline'] ?? null,
            'is_public' => $request->boolean('is_public', true),
        ];
    }

    /** Custom division rules keyed by name; a limit left out of the request means "no limit". */
    private function normalizeDivisionRules(array $rules): array
    {
        $number = fn (array $rule, string $key) => isset($rule[$key]) && $rule[$key] !== '' ? (int) $rule[$key] : null;

        return collect($rules)
            ->filter(fn ($rule) => is_array($rule) && trim($rule['name'] ?? '') !== '')
            ->mapWithKeys(fn (array $rule) => [trim($rule['name']) => [
                'gender' => $rule['gender'] ?? 'any',
                'min_age' => $number($rule, 'min_age'),
                'max_age' => $number($rule, 'max_age'),
                'min_rating' => $number($rule, 'min_rating'),
            ]])->all();
    }

    /**
     * The hole layout picked on the map (number, par, distance), limited to the event's hole
     * count. Stored with the event so it is played and rated on exactly this layout.
     */
    private function parseLayout(?string $holesData, int $holes): ?array
    {
        $decoded = $holesData ? json_decode($holesData, true) : null;
        if (!is_array($decoded)) {
            return null;
        }

        $layout = collect($decoded)
            ->filter(fn ($hole) => is_array($hole) && is_numeric($hole['number'] ?? null) && is_numeric($hole['par'] ?? null))
            ->map(fn (array $hole) => [
                'number' => (int) $hole['number'],
                'par' => max(1, min(10, (int) $hole['par'])),
                'distance_m' => is_numeric($hole['distance_m'] ?? null) ? max(1, min(2000, (int) $hole['distance_m'])) : null,
            ])
            ->filter(fn (array $hole) => $hole['number'] >= 1 && $hole['number'] <= $holes)
            ->unique('number')
            ->sortBy('number')
            ->values()
            ->all();

        return $layout ?: null;
    }

    /** Lists events. Only reads: groups are drawn and events go live by the scheduler (competitions:sync). */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['all', 'ongoing', 'upcoming', 'completed', 'cancelled', 'mine'])],
            'approved' => ['nullable', Rule::in(['all', 'approved', 'pending'])],
        ]);
        $status = $filters['status'] ?? 'all';
        $isAdmin = Auth::user()->isAdmin();

        $visible = fn () => $isAdmin
            ? Competition::query()
            : Competition::where('is_approved', true)->where('is_public', true);

        $query = $visible()->with(['user', 'course'])->withCount('registrations');
        $myRegistrationIds = CompetitionRegistration::where('user_id', Auth::id())->pluck('competition_id');

        match ($status) {
            'mine' => $query->whereIn('id', $myRegistrationIds),
            'all' => null,
            default => $query->where('status', $status),
        };
        if ($isAdmin && ($filters['approved'] ?? 'all') !== 'all') {
            $query->where('is_approved', $filters['approved'] === 'approved');
        }

        // Live first, then upcoming soonest-first, then finished events newest-first
        $competitions = $query
            ->orderByRaw("CASE status WHEN 'ongoing' THEN 0 WHEN 'upcoming' THEN 1 WHEN 'completed' THEN 2 ELSE 3 END")
            ->orderByRaw("CASE WHEN status IN ('ongoing', 'upcoming') THEN event_date END ASC")
            ->orderByDesc('event_date')
            ->paginate(12)
            ->withQueryString();

        $counts = [
            'live' => $visible()->where('status', 'ongoing')->count(),
            'upcoming' => $visible()->where('status', 'upcoming')->count(),
            'completed' => $visible()->where('status', 'completed')->count(),
            'mine' => $visible()->whereIn('id', $myRegistrationIds)->whereIn('status', ['upcoming', 'ongoing'])->count(),
            'pending' => $isAdmin ? Competition::where('is_approved', false)->count() : 0,
        ];

        return view('competitions.index', [
            'competitions' => $competitions,
            'status' => $status,
            'approved' => $filters['approved'] ?? 'all',
            'counts' => $counts,
            'myRegistrationIds' => $myRegistrationIds->all(),
        ]);
    }

    public function create()
    {
        if (!Auth::user()->canPublish()) {
            return redirect()->route('competitions.index')
                ->with('error', 'Only verified users can create competitions. Complete ' . User::VERIFY_AFTER_COMPETITIONS . ' competitions to get verified.');
        }

        return view('competitions.create');
    }

    public function store(Request $request)
    {
        if (!Auth::user()->canPublish()) {
            abort(403);
        }

        $validated = $request->validate($this->rules($request, false));
        $pickedOnMap = isset($validated['course_lat'], $validated['course_lon']);

        Competition::create($this->attributes($validated, $request) + [
            'user_id' => Auth::id(),
            'start_time' => $validated['start_time'],
            'course_lat' => $pickedOnMap ? $validated['course_lat'] : null,
            'course_lon' => $pickedOnMap ? $validated['course_lon'] : null,
            'course_layout' => $this->parseLayout($validated['holes_data'] ?? null, (int) $validated['holes']),
            'is_approved' => Auth::user()->isAdmin(),
        ]);

        return redirect()->route('competitions.index')
            ->with('success', Auth::user()->isAdmin()
                ? 'Competition created successfully!'
                : 'Competition submitted for approval!');
    }

    public function view($id)
    {
        $competition = Competition::with(['user', 'registrations.user'])->findOrFail($id);
        abort_unless($competition->isVisibleTo(Auth::user()), 404);

        $competition->load(['groups.registrations.user', 'registrations.group']);
        $leaderboard = $competition->hasGroups() ? $competition->leaderboard() : collect();

        // The rated course this event is played on: linked once rated, otherwise the matching layout if it exists
        $ratingCourse = $competition->course;
        if (!$ratingCourse && $competition->courseHoles()->exists()) {
            $ratingCourse = Course::where('name_key', Course::keyFor($competition->course_name ?: $competition->name))
                ->where('holes', $competition->courseHoles()->count())
                ->where('par', (int) $competition->courseHoles()->sum('par'))
                ->first();
        }

        $registrationClosed = $competition->registrationClosedReason();

        return view('competitions.view', compact('competition', 'leaderboard', 'ratingCourse', 'registrationClosed'));
    }

    /**
     * Registers the player, or changes their division / phone. The competition row stays locked
     * for the whole check-and-insert, so two players racing for the last place are handled one
     * after the other and the event never goes over max_participants.
     */
    public function register(Request $request, $id)
    {
        $competition = Competition::findOrFail($id);
        // A hidden event answers like a missing one, also to a direct POST with a guessed id
        abort_unless($competition->isVisibleTo($request->user()), 404);

        $validated = $request->validate([
            'division' => ['required', Rule::in($competition->divisionsArray)],
            'phone' => ['required', 'string', 'min:7', 'max:40', 'regex:/^[0-9+() .-]+$/'],
        ]);

        // Gender, age and rating limits (no playing down into a lower-rated division)
        $blocker = $request->user()->divisionBlocker($validated['division'], ($competition->division_rules ?? [])[$validated['division']] ?? null);
        if ($blocker) {
            return back()->withErrors(['division' => "You can't enter {$validated['division']}: {$blocker}."])->withInput();
        }

        $error = DB::transaction(function () use ($competition, $request, $validated) {
            $locked = Competition::whereKey($competition->id)->lockForUpdate()->first();

            if ($reason = $locked->registrationClosedReason()) {
                return $reason;
            }

            $alreadyRegistered = $locked->registrations()->where('user_id', $request->user()->id)->exists();
            if (!$alreadyRegistered && $locked->isFull()) {
                return 'This competition is full.';
            }

            CompetitionRegistration::updateOrCreate(
                ['competition_id' => $locked->id, 'user_id' => $request->user()->id],
                // Ratings come from rated tournament rounds, never from what a player types in
                ['division' => $validated['division'], 'phone' => $validated['phone'], 'rating' => $request->user()->rating]
            );

            return null;
        });

        if ($error) {
            return back()->withErrors(['division' => $error])->withInput();
        }

        return back()->with('success', 'You are registered for the competition.');
    }

    /** Withdraws the current player, possible while registration is open (before the draw). */
    public function unregister(Request $request, $id)
    {
        $competition = Competition::findOrFail($id);
        abort_unless($competition->isVisibleTo($request->user()), 404);

        $error = DB::transaction(function () use ($competition, $request) {
            $locked = Competition::whereKey($competition->id)->lockForUpdate()->first();

            if ($locked->hasGroups() || $locked->status !== 'upcoming' || now()->gte($locked->groupsDrawAt())) {
                return 'You can no longer withdraw — the groups have been drawn.';
            }

            return $locked->registrations()->where('user_id', $request->user()->id)->delete()
                ? null
                : 'You are not registered for this competition.';
        });

        return $error
            ? back()->with('error', $error)
            : back()->with('success', 'You have withdrawn from the competition.');
    }

    public function edit($id)
    {
        $competition = Competition::findOrFail($id);

        if (!Auth::user()->isAdmin()) {
            abort(403);
        }

        return view('competitions.edit', compact('competition'));
    }

    public function update(Request $request, $id)
    {
        $competition = Competition::findOrFail($id);

        if (!Auth::user()->isAdmin()) {
            abort(403);
        }

        $validated = $request->validate($this->rules($request, true));
        $attributes = $this->attributes($validated, $request) + [
            'start_time' => $validated['start_time'] ?? $competition->start_time,
            'status' => $validated['status'],
            'is_approved' => $request->boolean('is_approved'),
        ];

        // A different course name means the stored position and layout no longer describe the event
        if ($attributes['course_name'] !== $competition->course_name) {
            $attributes += ['course_lat' => null, 'course_lon' => null, 'course_layout' => null];
        }

        $this->guardRoundDefinition($competition, $attributes);
        $this->guardStatusChange($competition, $attributes['status']);

        $competition->update($attributes);

        // Completing (or re-opening) a tournament changes its ratings and every rating after it
        if ($competition->status === 'completed' || $competition->rated_at !== null) {
            app(RatingEngine::class)->recalculate();
        }

        if ($competition->status === 'completed') {
            $competition->registrations()->with('user')->get()
                ->each(fn ($registration) => $registration->user?->syncVerification());
        }

        return redirect()->route('competitions.view', $competition->id)
            ->with('success', 'Competition updated successfully!');
    }

    /**
     * Once the groups are drawn, the holes, cards and starting holes have been built from the
     * date, course, hole count, format and divisions, so those can no longer change: the
     * scorecards would otherwise belong to a different round than the event describes.
     */
    private function guardRoundDefinition(Competition $competition, array $attributes): void
    {
        if (!$competition->hasGroups()) {
            return;
        }

        $current = [
            'event_date' => $competition->event_date?->toDateString(),
            'start_time' => $competition->start_time ? substr($competition->start_time, 0, 5) : null,
            'course_name' => $competition->course_name,
            'holes' => (int) $competition->holes,
            'competition_type' => $competition->competition_type,
            'format' => $competition->format,
            'divisions' => collect($competition->divisionsArray)->sort()->values()->all(),
            'division_rules' => collect($competition->division_rules ?? [])->sortKeys()->all(),
        ];
        $wanted = [
            'event_date' => Carbon::parse($attributes['event_date'])->toDateString(),
            'start_time' => $attributes['start_time'] ? substr($attributes['start_time'], 0, 5) : null,
            'course_name' => $attributes['course_name'],
            'holes' => $attributes['holes'],
            'competition_type' => $attributes['competition_type'],
            'format' => $attributes['format'],
            'divisions' => collect(json_decode($attributes['divisions'], true))->sort()->values()->all(),
            'division_rules' => collect($attributes['division_rules'])->sortKeys()->all(),
        ];

        $changed = collect($current)->keys()->filter(fn (string $key) => $current[$key] != $wanted[$key]);
        if ($changed->isNotEmpty()) {
            throw ValidationException::withMessages([
                $changed->first() => 'The groups have been drawn, so this can no longer change: '
                    . $changed->map(fn (string $key) => str_replace('_', ' ', $key))->join(', ') . '.',
            ]);
        }
    }

    /** The status has to match what actually happened on the course. */
    private function guardStatusChange(Competition $competition, string $status): void
    {
        if ($status === $competition->status) {
            return;
        }

        $error = match ($status) {
            'upcoming' => $competition->hasGroups() ? 'The groups have been drawn, so the competition can no longer go back to upcoming.' : null,
            'ongoing' => !$competition->hasGroups() ? 'A competition can only be live once its groups have been drawn.' : null,
            'completed' => !$competition->hasFinishedRound() ? 'A competition can only be completed once at least one player has finished every hole.' : null,
            default => null,
        };

        if ($error) {
            throw ValidationException::withMessages(['status' => $error]);
        }
    }

    public function destroy($id)
    {
        $competition = Competition::findOrFail($id);

        if (!Auth::user()->isAdmin()) {
            abort(403);
        }

        $wasRated = $competition->rated_at !== null;
        $competition->delete();

        if ($wasRated) {
            app(RatingEngine::class)->recalculate();
        }

        return redirect()->route('competitions.index')
            ->with('success', 'Competition deleted successfully!');
    }

    public function approve($id)
    {
        $competition = Competition::findOrFail($id);

        if (!Auth::user()->isAdmin()) {
            abort(403);
        }

        $competition->update(['is_approved' => true]);

        return redirect()->back()
            ->with('success', 'Competition approved successfully!');
    }

    public function unapprove($id)
    {
        $competition = Competition::findOrFail($id);

        if (!Auth::user()->isAdmin()) {
            abort(403);
        }

        $competition->update(['is_approved' => false]);

        return redirect()->back()
            ->with('success', 'Competition unapproved!');
    }
}
