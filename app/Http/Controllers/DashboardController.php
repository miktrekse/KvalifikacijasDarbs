<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\Category;
use App\Models\User;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return view('dashboard.user', [
            'myExercises' => $user->exercises()->latest()->take(10)->get(),
            'addedExercises' => $user->addedExercises()->take(10)->get(),
            'categories' => Category::all(),
        ]);
    }

    public function adminDashboard()
    {
        return view('dashboard.admin', [
            'totalUsers' => User::count(),
            'totalExercises' => Exercise::count(),
            'publicExercises' => Exercise::where('is_public', true)->count(),
            'totalComments' => Comment::count(),
            'recentExercises' => Exercise::with('user')->latest()->take(10)->get(),
            'recentUsers' => User::latest()->take(5)->get(),
        ]);
    }

    public function manageUsers()
    {
        $users = User::orderBy('created_at', 'desc')->paginate(20);
        return view('admin.users.index', compact('users'));
    }

    public function createUser()
    {
        return view('admin.users.create');
    }

    public function storeUser(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:user,admin',
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
        ]);

        return redirect()->route('admin.users')->with('success', 'User created successfully!');
    }

    public function editUser($id)
    {
        $user = User::findOrFail($id);
        
        if ($user->id === Auth::id()) {
            return redirect()->route('admin.users')->with('error', 'You cannot edit your own account here.');
        }
        
        return view('admin.users.edit', compact('user'));
    }

    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $id,
            'role' => 'required|in:user,admin',
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
        ]);

        if ($request->filled('password')) {
            $request->validate(['password' => 'string|min:8|confirmed']);
            $user->update(['password' => Hash::make($request->password)]);
        }

        return redirect()->route('admin.users')->with('success', 'User updated successfully!');
    }

    public function destroyUser($id)
    {
        $user = User::findOrFail($id);

        if ($user->id === Auth::id()) {
            return redirect()->route('admin.users')->with('error', 'You cannot delete your own account.');
        }

        $user->exercises()->delete();
        $user->addedExercises()->detach();
        
        $user->delete();

        return redirect()->route('admin.users')->with('success', 'User deleted successfully!');
    }

    public function manageExercises()
    {
        $exercises = Exercise::with(['user', 'category'])->orderBy('created_at', 'desc')->paginate(20);
        return view('admin.exercises.index', compact('exercises'));
    }

    public function editAnyExercise($id)
    {
        $exercise = Exercise::findOrFail($id);
        $categories = Category::orderBy('name')->get();
        return view('admin.exercises.edit', compact('exercise', 'categories'));
    }

    public function updateAnyExercise(Request $request, $id)
    {
        $exercise = Exercise::findOrFail($id);

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

        return redirect()->route('admin.exercises')->with('success', 'Exercise updated successfully!');
    }

    public function destroyAnyExercise($id)
    {
        $exercise = Exercise::findOrFail($id);
        
        $exercise->comments()->delete();
        $exercise->delete();

        return redirect()->route('admin.exercises')->with('success', 'Exercise deleted successfully!');
    }

    public function manageCategories()
    {
        $categories = Category::orderBy('name')->get();
        return view('admin.categories.index', compact('categories'));
    }

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7',
        ]);

        Category::create($validated);

        return redirect()->route('admin.categories')->with('success', 'Category created successfully!');
    }

    public function destroyCategory($id)
    {
        $category = Category::findOrFail($id);
        
        $category->exercises()->update(['category_id' => null]);
        
        $category->delete();

        return redirect()->route('admin.categories')->with('success', 'Category deleted successfully!');
    }

    public function manageComments()
    {
        $comments = Comment::with(['user', 'exercise'])->orderBy('created_at', 'desc')->paginate(20);
        return view('admin.comments.index', compact('comments'));
    }

    public function destroyAnyComment($id)
    {
        $comment = Comment::findOrFail($id);
        $comment->delete();

        return redirect()->back()->with('success', 'Comment deleted successfully!');
    }
}
