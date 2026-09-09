<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Competition;
use App\Models\CompetitionRegistration;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CompetitionController extends Controller
{
    private function normalizeDivisionRules(array $rules): array
    {
        return collect($rules)
            ->filter(fn (array $rule) => trim($rule['name'] ?? '') !== '')
            ->mapWithKeys(fn (array $rule) => [trim($rule['name']) => [
                'gender' => $rule['gender'] ?? 'any',
                'min_age' => $rule['min_age'] !== '' ? ($rule['min_age'] ?? null) : null,
                'max_age' => $rule['max_age'] !== '' ? ($rule['max_age'] ?? null) : null,
                'min_rating' => $rule['min_rating'] !== '' ? ($rule['min_rating'] ?? null) : null,
            ]])->all();
    }

    private function userMeetsCustomDivision(User $user, array $rule, ?int $rating = null): bool
    {
        $age = $user->age();

        return ($rule['gender'] === 'any' || $user->gender === $rule['gender'])
            && ($rule['min_age'] === null || ($age !== null && $age >= $rule['min_age']))
            && ($rule['max_age'] === null || ($age !== null && $age <= $rule['max_age']))
            && ($rule['min_rating'] === null || ($rating !== null && $rating >= $rule['min_rating']));
    }

    public function index(Request $request)
    {
        $query = Competition::with('user');

        if (Auth::check() && Auth::user()->isAdmin()) {
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }
            if ($request->has('approved') && $request->approved !== 'all') {
                $query->where('is_approved', $request->approved === 'approved');
            }
        } else {
            $query->where('is_approved', true)
                  ->where('is_public', true);
            
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }
        }

        $competitions = $query->orderBy('event_date', 'desc')
            ->paginate(12);

        return view('competitions.index', compact('competitions'));
    }

    public function create()
    {
        return view('competitions.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'event_date' => 'required|date|after_or_equal:today',
            'location' => 'nullable|string|max:255',
            'course_name' => 'nullable|string|max:255',
            'competition_type' => 'required|in:singles,doubles',
            'format' => ['required', Rule::in($request->input('competition_type') === 'doubles'
                ? ['doubles_match_play', 'doubles_best_disc', 'doubles_team']
                : ['stroke_play', 'match_play', 'stableford'])],
            'divisions' => 'nullable|string',
            'division_options' => 'nullable|array',
            'division_options.*' => 'in:MPO,MA1,MA2,MA3,MA4,FPO,FA2,FA3,FA4,MP60,MP50,MP40,FP40,MJ18,MJ15,FJ18',
            'division_rules' => 'nullable|array',
            'division_rules.*.name' => 'required|string|max:40',
            'division_rules.*.gender' => 'required|in:any,male,female',
            'division_rules.*.min_age' => 'nullable|integer|min:0|max:120',
            'division_rules.*.max_age' => 'nullable|integer|min:0|max:120',
            'division_rules.*.min_rating' => 'nullable|integer|min:0|max:1100',
            'holes' => 'required|integer|min:1|max:99',
            'entry_fee' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'max_participants' => 'nullable|integer|min:1',
            'registration_link' => 'nullable|url',
            'registration_deadline' => 'nullable|date',
            'is_public' => 'boolean',
        ]);

        $divisions = $validated['division_options'] ?? [];
        if (!$divisions && !empty($validated['divisions'])) {
            $divisions = array_map('trim', explode(',', $validated['divisions']));
            $divisions = array_filter($divisions);
        }
        $divisionRules = $this->normalizeDivisionRules($validated['division_rules'] ?? []);
        $divisions = array_values(array_unique(array_merge($divisions, array_keys($divisionRules))));

        Competition::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'event_date' => $validated['event_date'],
            'location' => $validated['location'] ?? null,
            'course_name' => $validated['course_name'] ?? null,
            'format' => $validated['format'],
            'competition_type' => $validated['competition_type'],
            'divisions' => json_encode($divisions),
            'division_rules' => $divisionRules,
            'holes' => $validated['holes'],
            'entry_fee' => $validated['entry_fee'] ?? 0,
            'currency' => $validated['currency'] ?? 'EUR',
            'max_participants' => $validated['max_participants'] ?? null,
            'registration_link' => $validated['registration_link'] ?? null,
            'registration_deadline' => $validated['registration_deadline'] ?? null,
            'is_public' => $request->boolean('is_public', true),
            'is_approved' => Auth::user()->isAdmin() ? true : false,
        ]);

        return redirect()->route('competitions.index')
            ->with('success', Auth::user()->isAdmin() 
                ? 'Competition created successfully!' 
                : 'Competition submitted for approval!');
    }

    public function view($id)
    {
        $competition = Competition::with(['user', 'registrations.user'])->findOrFail($id);

        if (!$competition->is_approved || !$competition->is_public) {
            if (!Auth::check() || !Auth::user()->isAdmin()) {
                abort(404);
            }
        }

        return view('competitions.view', compact('competition'));
    }

    public function register(Request $request, $id)
    {
        $competition = Competition::with('registrations')->findOrFail($id);
        $divisionOptions = $competition->divisionsArray;

        $validated = $request->validate([
            'division' => ['required', Rule::in($divisionOptions)],
            'phone' => ['required', 'string', 'min:7', 'max:40', 'regex:/^[0-9+() .-]+$/'],
            'rating' => ['nullable', 'integer', 'min:0', 'max:1100'],
        ]);

        if ($competition->max_participants && $competition->registrations->count() >= $competition->max_participants) {
            return back()->withErrors(['division' => 'This competition is full.'])->withInput();
        }

        if (!$request->user()->meetsDivisionRequirements($validated['division'])) {
            return back()->withErrors(['division' => 'Your profile does not meet this division\'s age or gender requirements.'])->withInput();
        }

        $rule = ($competition->division_rules ?? [])[$validated['division']] ?? null;
        if ($rule && !$this->userMeetsCustomDivision($request->user(), $rule, $validated['rating'] ?? null)) {
            return back()->withErrors(['division' => 'Your profile does not meet this custom division\'s requirements.'])->withInput();
        }

        CompetitionRegistration::updateOrCreate(
            ['competition_id' => $competition->id, 'user_id' => $request->user()->id],
            ['division' => $validated['division'], 'phone' => $validated['phone'], 'rating' => $validated['rating'] ?? null]
        );

        return back()->with('success', 'You are registered for the competition.');
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

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'event_date' => 'required|date',
            'location' => 'nullable|string|max:255',
            'course_name' => 'nullable|string|max:255',
            'competition_type' => 'required|in:singles,doubles',
            'format' => ['required', Rule::in($request->input('competition_type') === 'doubles'
                ? ['doubles_match_play', 'doubles_best_disc', 'doubles_team']
                : ['stroke_play', 'match_play', 'stableford'])],
            'divisions' => 'nullable|string',
            'division_options' => 'nullable|array',
            'division_options.*' => 'in:MPO,MA1,MA2,MA3,MA4,FPO,FA2,FA3,FA4,MP60,MP50,MP40,FP40,MJ18,MJ15,FJ18',
            'division_rules' => 'nullable|array',
            'division_rules.*.name' => 'required|string|max:40',
            'division_rules.*.gender' => 'required|in:any,male,female',
            'division_rules.*.min_age' => 'nullable|integer|min:0|max:120',
            'division_rules.*.max_age' => 'nullable|integer|min:0|max:120',
            'division_rules.*.min_rating' => 'nullable|integer|min:0|max:1100',
            'holes' => 'required|integer|min:1|max:99',
            'entry_fee' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'max_participants' => 'nullable|integer|min:1',
            'registration_link' => 'nullable|url',
            'registration_deadline' => 'nullable|date',
            'status' => 'required|in:upcoming,ongoing,completed,cancelled',
            'is_approved' => 'boolean',
            'is_public' => 'boolean',
        ]);

        $divisions = $validated['division_options'] ?? [];
        if (!$divisions && !empty($validated['divisions'])) {
            $divisions = array_map('trim', explode(',', $validated['divisions']));
            $divisions = array_filter($divisions);
        }
        $divisionRules = $this->normalizeDivisionRules($validated['division_rules'] ?? []);
        $divisions = array_values(array_unique(array_merge($divisions, array_keys($divisionRules))));

        $competition->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'event_date' => $validated['event_date'],
            'location' => $validated['location'] ?? null,
            'course_name' => $validated['course_name'] ?? null,
            'format' => $validated['format'],
            'competition_type' => $validated['competition_type'],
            'divisions' => json_encode($divisions),
            'division_rules' => $divisionRules,
            'holes' => $validated['holes'],
            'entry_fee' => $validated['entry_fee'] ?? 0,
            'currency' => $validated['currency'] ?? 'EUR',
            'max_participants' => $validated['max_participants'] ?? null,
            'registration_link' => $validated['registration_link'] ?? null,
            'registration_deadline' => $validated['registration_deadline'] ?? null,
            'status' => $validated['status'],
            'is_approved' => $request->boolean('is_approved'),
            'is_public' => $request->boolean('is_public', true),
        ]);

        return redirect()->route('competitions.view', $competition->id)
            ->with('success', 'Competition updated successfully!');
    }

    public function destroy($id)
    {
        $competition = Competition::findOrFail($id);

        if (!Auth::user()->isAdmin()) {
            abort(403);
        }

        $competition->delete();

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
