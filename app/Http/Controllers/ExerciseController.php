<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Exercise;
use App\Models\Category;
use App\Models\Comment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ExerciseController extends Controller
{
    public const DIFFICULTIES = ['beginner', 'intermediate', 'advanced', 'expert'];

    public const EQUIPMENT = [
        'putters' => 'Putters',
        'midranges' => 'Midranges',
        'fairway-drivers' => 'Fairway drivers',
        'distance-drivers' => 'Distance drivers',
    ];

    /** Session length buckets: [min, max] minutes, max null = open-ended. */
    public const DURATIONS = [
        'quick' => ['label' => 'Up to 15 min', 'range' => [0, 15]],
        'medium' => ['label' => '16–25 min', 'range' => [16, 25]],
        'long' => ['label' => '26+ min', 'range' => [26, null]],
    ];

    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:100',
            'category' => 'nullable|integer|exists:categories,id',
            'difficulty' => 'nullable|array',
            'difficulty.*' => Rule::in(self::DIFFICULTIES),
            'style' => ['nullable', Rule::in(['backhand', 'forehand'])],
            'equipment' => ['nullable', Rule::in(array_keys(self::EQUIPMENT))],
            'duration' => ['nullable', Rule::in(array_keys(self::DURATIONS))],
            'saved' => 'nullable|boolean',
            'sort' => ['nullable', Rule::in(['newest', 'easiest', 'hardest', 'shortest', 'popular'])],
        ]);

        $query = Exercise::where('is_public', true)
            ->with(['category', 'user'])
            ->withCount('users');

        if ($search = trim($filters['q'] ?? '')) {
            $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhere('tags', 'like', "%{$search}%"));
        }
        if (!empty($filters['category'])) {
            $query->where('category_id', $filters['category']);
        }
        if (!empty($filters['difficulty'])) {
            $query->whereIn('difficulty', $filters['difficulty']);
        }
        if (!empty($filters['style'])) {
            $query->whereJsonContains('throwing_styles', $filters['style']);
        }
        if (!empty($filters['equipment'])) {
            $query->where('equipment', 'like', '%' . $filters['equipment'] . '%');
        }
        if (!empty($filters['duration'])) {
            [$min, $max] = self::DURATIONS[$filters['duration']]['range'];
            $query->where('duration_minutes', '>=', $min);
            if ($max !== null) {
                $query->where('duration_minutes', '<=', $max);
            }
        }
        if (!empty($filters['saved'])) {
            $query->whereHas('users', fn ($q) => $q->where('user_id', Auth::id()));
        }

        $difficultyRank = "CASE difficulty WHEN 'beginner' THEN 1 WHEN 'intermediate' THEN 2 WHEN 'advanced' THEN 3 ELSE 4 END";
        match ($filters['sort'] ?? 'newest') {
            'easiest' => $query->orderByRaw("$difficultyRank asc"),
            'hardest' => $query->orderByRaw("$difficultyRank desc"),
            'shortest' => $query->orderByRaw('duration_minutes is null, duration_minutes asc'),
            'popular' => $query->orderByDesc('users_count'),
            default => $query->latest(),
        };

        $exercises = $query->orderBy('id', 'desc')->paginate(12)->withQueryString();

        return view('exercises.index', [
            'exercises' => $exercises,
            'filters' => $filters,
            'categories' => Category::withCount(['exercises' => fn ($q) => $q->where('is_public', true)])->orderBy('name')->get(),
            'savedIds' => Auth::user()->addedExercises()->pluck('exercises.id')->all(),
            'totalPublic' => Exercise::where('is_public', true)->count(),
        ]);
    }

    public function create()
    {
        $categories = Category::orderBy('name')->get();
        return view('exercises.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'instructions' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'difficulty' => 'required|in:beginner,intermediate,advanced,expert',
            'duration_minutes' => 'nullable|integer|min:1|max:480',
            'equipment' => 'nullable|string|max:255',
            'equipment_options' => 'nullable|array',
            'equipment_options.*' => 'in:putters,midranges,fairway-drivers,distance-drivers',
            'throwing_styles' => 'nullable|array',
            'throwing_styles.*' => 'in:backhand,forehand',
            'tags_input' => 'nullable|string',
            'is_public' => 'boolean',
        ]);

        $tags = [];
        if (!empty($validated['tags_input'])) {
            $tags = array_map('trim', explode(',', $validated['tags_input']));
            $tags = array_filter($tags);
        }

        $category = !empty($validated['category_id']) ? Category::find($validated['category_id']) : null;
        $throwingStyles = $validated['throwing_styles'] ?? [];
        if ($category && strtolower($category->name) === 'putting') {
            $throwingStyles = [];
        }
        $equipment = !empty($validated['equipment_options'])
            ? implode(', ', $validated['equipment_options'])
            : ($validated['equipment'] ?? null);

        $exercise = Exercise::create([
            'user_id' => Auth::id(),
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'instructions' => $validated['instructions'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'difficulty' => $validated['difficulty'],
            'duration_minutes' => $validated['duration_minutes'] ?? null,
            'equipment' => $equipment,
            'throwing_styles' => $throwingStyles,
            'tags' => $tags,
            'is_public' => Auth::user()->canPublish() && $request->boolean('is_public', true),
        ]);

        Auth::user()->addedExercises()->attach($exercise->id);

        return redirect()->route('exercises.view', $exercise->id)
            ->with('success', 'Exercise created successfully!');
    }

    public function view($id)
    {
        $exercise = Exercise::with(['category', 'user', 'comments.user'])
            ->findOrFail($id);
        
        $isSaved = false;
        if (Auth::check()) {
            $isSaved = Auth::user()->addedExercises()
                ->where('exercise_id', $id)
                ->exists();
        }
        
        $isOwner = Auth::check() && Auth::id() === $exercise->user_id;
        
        return view('exercises.view', compact('exercise', 'isSaved', 'isOwner'));
    }

    public function edit($id)
    {
        $exercise = Exercise::findOrFail($id);
        
        if (Auth::id() !== $exercise->user_id) {
            return redirect()->route('exercises.index')
                ->with('error', 'You can only edit your own exercises.');
        }
        
        $categories = Category::orderBy('name')->get();
        return view('exercises.edit', compact('exercise', 'categories'));
    }

    public function update(Request $request, $id)
    {
        $exercise = Exercise::findOrFail($id);
        
        if (Auth::id() !== $exercise->user_id) {
            return redirect()->route('exercises.index')
                ->with('error', 'You can only update your own exercises.');
        }
        
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'instructions' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'difficulty' => 'required|in:beginner,intermediate,advanced,expert',
            'duration_minutes' => 'nullable|integer|min:1|max:480',
            'equipment' => 'nullable|string|max:255',
            'equipment_options' => 'nullable|array',
            'equipment_options.*' => 'in:putters,midranges,fairway-drivers,distance-drivers',
            'throwing_styles' => 'nullable|array',
            'throwing_styles.*' => 'in:backhand,forehand',
            'tags_input' => 'nullable|string',
            'is_public' => 'boolean',
        ]);

        $tags = [];
        if (!empty($validated['tags_input'])) {
            $tags = array_map('trim', explode(',', $validated['tags_input']));
            $tags = array_filter($tags);
        }

        $category = !empty($validated['category_id']) ? Category::find($validated['category_id']) : null;
        $throwingStyles = $validated['throwing_styles'] ?? [];
        if ($category && strtolower($category->name) === 'putting') {
            $throwingStyles = [];
        }
        $equipment = array_key_exists('equipment_options', $validated)
            ? implode(', ', $validated['equipment_options'] ?? [])
            : ($validated['equipment'] ?? null);

        $exercise->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'instructions' => $validated['instructions'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'difficulty' => $validated['difficulty'],
            'duration_minutes' => $validated['duration_minutes'] ?? null,
            'equipment' => $equipment,
            'throwing_styles' => $throwingStyles,
            'tags' => $tags,
            'is_public' => Auth::user()->canPublish() && $request->boolean('is_public', true),
        ]);

        return redirect()->route('exercises.view', $exercise->id)
            ->with('success', 'Exercise updated successfully!');
    }

    public function destroy($id)
    {
        $exercise = Exercise::findOrFail($id);
        
        if (Auth::id() !== $exercise->user_id) {
            return redirect()->route('exercises.index')
                ->with('error', 'You can only delete your own exercises.');
        }
        
        $exercise->delete();
        
        return redirect()->route('exercises.index')
            ->with('success', 'Exercise deleted successfully!');
    }

    public function saved()
    {
        $exercises = Auth::user()->addedExercises()
            ->with(['category', 'user'])
            ->orderBy('exercise_user.created_at', 'desc')
            ->paginate(12);
        
        return view('exercises.saved', compact('exercises'));
    }

    public function toggleSave(Request $request)
    {
        $request->validate([
            'exercise_id' => 'required|exists:exercises,id',
            'saved' => 'sometimes|boolean',
        ]);

        $exercise = Exercise::findOrFail($request->exercise_id);
        $user = Auth::user();

        // Callers that send the wanted state get an idempotent request, so a double
        // click or a retry can't flip it back; forms without it simply toggle.
        $shouldSave = $request->has('saved')
            ? $request->boolean('saved')
            : !$user->addedExercises()->where('exercise_id', $exercise->id)->exists();

        if ($shouldSave) {
            $user->addedExercises()->syncWithoutDetaching([$exercise->id]);
            $message = 'Saved to your drills.';
        } else {
            $user->addedExercises()->detach($exercise->id);
            $message = 'Removed from your saved drills.';
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'isSaved' => $shouldSave,
                'savesCount' => $exercise->users()->count(),
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    public function myExercises()
    {
        $exercises = Exercise::where('user_id', Auth::id())
            ->with('category')
            ->orderBy('created_at', 'desc')
            ->paginate(12);
        
        return view('exercises.my-exercises', compact('exercises'));
    }

    public function addComment(Request $request, $id)
    {
        $request->validate([
            'content' => 'required|string|max:1000'
        ]);

        $exercise = Exercise::findOrFail($id);

        Comment::create([
            'exercise_id' => $exercise->id,
            'user_id' => Auth::id(),
            'content' => $request->content
        ]);

        return redirect()->route('exercises.view', $exercise->id)
            ->with('success', 'Comment added successfully!');
    }

    public function deleteComment(Request $request, $id)
    {
        $request->validate([
            'comment_id' => 'required|exists:comments,id'
        ]);

        $comment = Comment::findOrFail($request->comment_id);
        
        if (Auth::id() !== $comment->user_id && Auth::id() !== $comment->exercise->user_id) {
            return redirect()->back()
                ->with('error', 'You can only delete your own comments.');
        }

        $comment->delete();

        return redirect()->route('exercises.view', $id)
            ->with('success', 'Comment deleted successfully!');
    }
}
