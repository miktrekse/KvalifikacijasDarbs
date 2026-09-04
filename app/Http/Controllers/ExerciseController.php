<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Exercise;
use App\Models\Category;
use App\Models\Comment;
use Illuminate\Support\Facades\Auth;

class ExerciseController extends Controller
{
    public function index()
    {
        $exercises = Exercise::where('is_public', true)
            ->with('category')
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(12);
        
        return view('exercises.index', compact('exercises'));
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
            'tags_input' => 'nullable|string',
            'is_public' => 'boolean',
        ]);

        $tags = [];
        if (!empty($validated['tags_input'])) {
            $tags = array_map('trim', explode(',', $validated['tags_input']));
            $tags = array_filter($tags);
        }

        $exercise = Exercise::create([
            'user_id' => Auth::id(),
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'instructions' => $validated['instructions'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'difficulty' => $validated['difficulty'],
            'duration_minutes' => $validated['duration_minutes'] ?? null,
            'equipment' => $validated['equipment'] ?? null,
            'tags' => $tags,
            'is_public' => $request->boolean('is_public', true),
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
            'tags_input' => 'nullable|string',
            'is_public' => 'boolean',
        ]);

        $tags = [];
        if (!empty($validated['tags_input'])) {
            $tags = array_map('trim', explode(',', $validated['tags_input']));
            $tags = array_filter($tags);
        }

        $exercise->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'instructions' => $validated['instructions'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'difficulty' => $validated['difficulty'],
            'duration_minutes' => $validated['duration_minutes'] ?? null,
            'equipment' => $validated['equipment'] ?? null,
            'tags' => $tags,
            'is_public' => $request->boolean('is_public', true),
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
            'exercise_id' => 'required|exists:exercises,id'
        ]);

        $exercise = Exercise::findOrFail($request->exercise_id);
        $user = Auth::user();

        $isSaved = $user->addedExercises()
            ->where('exercise_id', $exercise->id)
            ->exists();

        if ($isSaved) {
            $user->addedExercises()->detach($exercise->id);
            $message = 'Exercise removed from saved list.';
        } else {
            $user->addedExercises()->attach($exercise->id);
            $message = 'Exercise saved successfully!';
        }

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'isSaved' => !$isSaved
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
