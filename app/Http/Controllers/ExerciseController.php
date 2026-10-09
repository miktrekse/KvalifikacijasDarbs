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

    public const DIFFICULTY_COLORS = ['beginner' => '#22a268', 'intermediate' => '#0ea5e9', 'advanced' => '#f26b3a', 'expert' => '#b91c1c'];

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

    /** Upper bounds keep requests inside what the TEXT/VARCHAR columns can hold. */
    public const MAX_TAGS = 15;

    public const MAX_TAG_LENGTH = 30;

    /** Shared by create and edit. */
    private function exerciseRules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'instructions' => 'nullable|string|max:10000',
            'category_id' => 'nullable|exists:categories,id',
            'difficulty' => ['required', Rule::in(self::DIFFICULTIES)],
            'duration_minutes' => 'nullable|integer|min:1|max:480',
            'equipment' => 'nullable|string|max:255',
            'equipment_options' => 'nullable|array',
            'equipment_options.*' => Rule::in(array_keys(self::EQUIPMENT)),
            'throwing_styles' => 'nullable|array',
            'throwing_styles.*' => 'in:backhand,forehand',
            'tags_input' => ['nullable', 'string', 'max:500', function (string $attribute, mixed $value, \Closure $fail) {
                $tags = self::parseTags($value);
                if (count($tags) > self::MAX_TAGS) {
                    $fail('Use at most ' . self::MAX_TAGS . ' tags.');
                }
                if (collect($tags)->contains(fn (string $tag) => mb_strlen($tag) > self::MAX_TAG_LENGTH)) {
                    $fail('Each tag can be at most ' . self::MAX_TAG_LENGTH . ' characters long.');
                }
            }],
            'is_public' => 'boolean',
        ];
    }

    /** "putting, circle 1, ,Putting" → ["putting", "circle 1"] */
    public static function parseTags(?string $input): array
    {
        return collect(explode(',', (string) $input))
            ->map(fn (string $tag) => trim($tag))
            ->filter()
            ->unique(fn (string $tag) => mb_strtolower($tag))
            ->values()
            ->all();
    }

    private function filterRules(array $sorts): array
    {
        return [
            'q' => 'nullable|string|max:100',
            'category' => 'nullable|integer|exists:categories,id',
            'difficulty' => 'nullable|array',
            'difficulty.*' => Rule::in(self::DIFFICULTIES),
            'style' => ['nullable', Rule::in(['backhand', 'forehand'])],
            'equipment' => ['nullable', Rule::in(array_keys(self::EQUIPMENT))],
            'duration' => ['nullable', Rule::in(array_keys(self::DURATIONS))],
            'saved' => 'nullable|boolean',
            'sort' => ['nullable', Rule::in($sorts)],
        ];
    }

    /**
     * Applies the library filters and sort to an exercise query. Columns are
     * table-qualified because the saved list joins the exercise_user pivot.
     */
    private function applyFilters($query, array $filters, string $defaultSort): void
    {
        $query->with(['category', 'user'])->withCount('users');

        if ($search = trim($filters['q'] ?? '')) {
            $query->where(fn ($q) => $q->where('exercises.title', 'like', "%{$search}%")
                ->orWhere('exercises.description', 'like', "%{$search}%")
                ->orWhere('exercises.tags', 'like', "%{$search}%"));
        }
        if (!empty($filters['category'])) {
            $query->where('exercises.category_id', $filters['category']);
        }
        if (!empty($filters['difficulty'])) {
            $query->whereIn('exercises.difficulty', $filters['difficulty']);
        }
        if (!empty($filters['style'])) {
            $query->whereJsonContains('exercises.throwing_styles', $filters['style']);
        }
        if (!empty($filters['equipment'])) {
            $query->where('exercises.equipment', 'like', '%' . $filters['equipment'] . '%');
        }
        if (!empty($filters['duration'])) {
            [$min, $max] = self::DURATIONS[$filters['duration']]['range'];
            $query->where('exercises.duration_minutes', '>=', $min);
            if ($max !== null) {
                $query->where('exercises.duration_minutes', '<=', $max);
            }
        }
        if (!empty($filters['saved'])) {
            $query->whereHas('users', fn ($q) => $q->where('user_id', Auth::id()));
        }

        $difficultyRank = "CASE exercises.difficulty WHEN 'beginner' THEN 1 WHEN 'intermediate' THEN 2 WHEN 'advanced' THEN 3 ELSE 4 END";
        match ($filters['sort'] ?? $defaultSort) {
            'easiest' => $query->orderByRaw("$difficultyRank asc"),
            'hardest' => $query->orderByRaw("$difficultyRank desc"),
            'shortest' => $query->orderByRaw('exercises.duration_minutes is null, exercises.duration_minutes asc'),
            'popular' => $query->orderByDesc('users_count'),
            'recent' => $query->orderByDesc('exercise_user.created_at'),
            default => $query->orderByDesc('exercises.created_at'),
        };
        $query->orderByDesc('exercises.id');
    }

    public function index(Request $request)
    {
        $filters = $request->validate($this->filterRules(['newest', 'easiest', 'hardest', 'shortest', 'popular']));

        $query = Exercise::where('exercises.is_public', true);
        $this->applyFilters($query, $filters, 'newest');

        return view('exercises.index', [
            'exercises' => $query->paginate(12)->withQueryString(),
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
        $validated = $request->validate($this->exerciseRules());
        $tags = self::parseTags($validated['tags_input'] ?? null);

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
        // A private drill answers like a missing one (404), so ids can't be probed
        abort_unless(Auth::user()->can('view', $exercise), 404);

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
        
        if (Auth::user()->cannot('update', $exercise)) {
            return redirect()->route('exercises.index')
                ->with('error', 'You can only edit your own exercises.');
        }
        
        $categories = Category::orderBy('name')->get();
        return view('exercises.edit', compact('exercise', 'categories'));
    }

    public function update(Request $request, $id)
    {
        $exercise = Exercise::findOrFail($id);
        
        if (Auth::user()->cannot('update', $exercise)) {
            return redirect()->route('exercises.index')
                ->with('error', 'You can only update your own exercises.');
        }
        
        $validated = $request->validate($this->exerciseRules());
        $tags = self::parseTags($validated['tags_input'] ?? null);

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
        
        if (Auth::user()->cannot('delete', $exercise)) {
            return redirect()->route('exercises.index')
                ->with('error', 'You can only delete your own exercises.');
        }
        
        $exercise->delete();
        
        return redirect()->route('exercises.index')
            ->with('success', 'Exercise deleted successfully!');
    }

    public function saved(Request $request)
    {
        $filters = $request->validate($this->filterRules(['recent', 'newest', 'easiest', 'hardest', 'shortest', 'popular']));
        $user = Auth::user();

        // A saved drill its author has since made private drops out of the list
        $query = $user->addedExercises()->visibleTo($user);
        $this->applyFilters($query, $filters, 'recent');

        // Header numbers describe the whole saved list, not just the filtered page
        $all = $user->addedExercises()->visibleTo($user)->get(['exercises.id', 'exercises.user_id', 'exercises.category_id', 'exercises.duration_minutes']);

        return view('exercises.saved', [
            'exercises' => $query->paginate(12)->withQueryString(),
            'filters' => $filters,
            'categories' => Category::withCount(['exercises' => fn ($q) => $q->whereIn('exercises.id', $all->pluck('id'))])->orderBy('name')->get(),
            'savedIds' => $all->pluck('id')->all(),
            'summary' => [
                'saved' => $all->count(),
                'minutes' => (int) $all->sum('duration_minutes'),
                'categories' => $all->pluck('category_id')->filter()->unique()->count(),
                'own' => $all->where('user_id', $user->id)->count(),
            ],
        ]);
    }

    public function toggleSave(Request $request)
    {
        $request->validate([
            'exercise_id' => 'required|exists:exercises,id',
            'saved' => 'sometimes|boolean',
        ]);

        $exercise = Exercise::findOrFail($request->exercise_id);
        $user = Auth::user();
        abort_unless($user->can('save', $exercise), 404);

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

    public function addComment(Request $request, $id)
    {
        $request->validate([
            'content' => 'required|string|max:1000'
        ]);

        $exercise = Exercise::findOrFail($id);
        abort_unless(Auth::user()->can('comment', $exercise), 404);

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

        // The comment has to belong to the drill in the URL
        $comment = Comment::where('exercise_id', $id)->findOrFail($request->comment_id);
        
        if (Auth::id() !== $comment->user_id && Auth::id() !== $comment->exercise->user_id) {
            return redirect()->back()
                ->with('error', 'You can only delete your own comments.');
        }

        $comment->delete();

        return redirect()->route('exercises.view', $id)
            ->with('success', 'Comment deleted successfully!');
    }
}
