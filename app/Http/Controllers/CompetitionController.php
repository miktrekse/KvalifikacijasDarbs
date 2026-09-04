<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Competition;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CompetitionController extends Controller
{
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
            'format' => 'required|in:stroke_play,match_play,stableford,best_disc,team',
            'divisions' => 'nullable|string',
            'holes' => 'required|integer|min:9|max:27',
            'entry_fee' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'max_participants' => 'nullable|integer|min:1',
            'registration_link' => 'nullable|url',
            'registration_deadline' => 'nullable|date',
            'is_public' => 'boolean',
        ]);

        $divisions = [];
        if (!empty($validated['divisions'])) {
            $divisions = array_map('trim', explode(',', $validated['divisions']));
            $divisions = array_filter($divisions);
        }

        Competition::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'event_date' => $validated['event_date'],
            'location' => $validated['location'] ?? null,
            'course_name' => $validated['course_name'] ?? null,
            'format' => $validated['format'],
            'divisions' => json_encode($divisions),
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
        $competition = Competition::with('user')->findOrFail($id);

        if (!$competition->is_approved || !$competition->is_public) {
            if (!Auth::check() || !Auth::user()->isAdmin()) {
                abort(404);
            }
        }

        return view('competitions.view', compact('competition'));
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
            'format' => 'required|in:stroke_play,match_play,stableford,best_disc,team',
            'divisions' => 'nullable|string',
            'holes' => 'required|integer|min:9|max:27',
            'entry_fee' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'max_participants' => 'nullable|integer|min:1',
            'registration_link' => 'nullable|url',
            'registration_deadline' => 'nullable|date',
            'status' => 'required|in:upcoming,ongoing,completed,cancelled',
            'is_approved' => 'boolean',
            'is_public' => 'boolean',
        ]);

        $divisions = [];
        if (!empty($validated['divisions'])) {
            $divisions = array_map('trim', explode(',', $validated['divisions']));
            $divisions = array_filter($divisions);
        }

        $competition->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'event_date' => $validated['event_date'],
            'location' => $validated['location'] ?? null,
            'course_name' => $validated['course_name'] ?? null,
            'format' => $validated['format'],
            'divisions' => json_encode($divisions),
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
