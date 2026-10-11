<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\Category;
use App\Models\User;
use App\Models\Comment;
use App\Rules\NotReservedEmail;
use App\Support\AccountRemoval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $user->syncVerification();

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
            'totalUsers' => User::withoutGuests()->count(),
            'totalExercises' => Exercise::count(),
            'publicExercises' => Exercise::where('is_public', true)->count(),
            'totalComments' => Comment::count(),
            'recentExercises' => Exercise::with('user')->latest()->take(10)->get(),
            'recentUsers' => User::withoutGuests()->latest()->take(5)->get(),
        ]);
    }

    public function manageUsers()
    {
        $users = User::withoutGuests()->orderBy('created_at', 'desc')->paginate(20);
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
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users', new NotReservedEmail()],
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:user,verified,admin',
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
        ]);

        return redirect()->route('admin.users')->with('success', 'User created successfully!');
    }

    /**
     * The shared guest login is never managed here: one role change would otherwise turn it
     * into a shared account with real rights for every visitor.
     */
    public function editUser($id)
    {
        $user = User::withoutGuests()->findOrFail($id);
        
        if ($user->id === Auth::id()) {
            return redirect()->route('admin.users')->with('error', 'You cannot edit your own account here.');
        }
        
        return view('admin.users.edit', compact('user'));
    }

    /**
     * Same rules as the edit page, enforced here too because a PUT can be sent without it:
     * admins never change their own account from this screen, and the last admin keeps the role.
     */
    public function updateUser(Request $request, $id)
    {
        $user = User::withoutGuests()->findOrFail($id);

        if ($user->id === Auth::id()) {
            return redirect()->route('admin.users')->with('error', 'You cannot edit your own account here.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id, new NotReservedEmail()],
            'role' => 'required|in:user,verified,admin',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $error = DB::transaction(function () use ($user, $validated) {
            // Locked, so two admins demoting each other at once can't leave nobody in charge
            $admins = User::where('role', 'admin')->lockForUpdate()->pluck('id');
            if ($user->isAdmin() && $validated['role'] !== 'admin' && $admins->count() <= 1) {
                return 'DiscStats needs at least one admin.';
            }

            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role' => $validated['role'],
            ] + (!empty($validated['password']) ? ['password' => Hash::make($validated['password'])] : []));

            return null;
        });

        return $error
            ? back()->withInput()->with('error', $error)
            : redirect()->route('admin.users')->with('success', 'User updated successfully!');
    }

    /**
     * "Delete" anonymises the account (see AccountRemoval): the person and their login are
     * gone, but tournaments, scorecards, shared training rounds and ratings are untouched.
     * Admins are demoted first, so one admin can't remove another with a single click.
     */
    public function destroyUser($id)
    {
        $user = User::withoutGuests()->findOrFail($id);

        if ($user->id === Auth::id()) {
            return redirect()->route('admin.users')->with('error', 'You cannot delete your own account.');
        }
        if ($user->isAdmin()) {
            return redirect()->route('admin.users')->with('error', 'Remove the admin role before deleting this account.');
        }

        AccountRemoval::anonymize($user);

        return redirect()->route('admin.users')->with('success', 'User deleted. Their results stay in past tournaments as "' . AccountRemoval::ANONYMOUS_NAME . '".');
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
            'description' => 'nullable|string|max:5000',
            'instructions' => 'nullable|string|max:10000',
            'category_id' => 'nullable|exists:categories,id',
            'difficulty' => 'required|in:beginner,intermediate,advanced,expert',
            'duration_minutes' => 'nullable|integer|min:1|max:480',
            'equipment' => 'nullable|string|max:255',
            'tags_input' => 'nullable|string|max:500',
            'is_public' => 'boolean',
        ]);

        $tags = array_slice(ExerciseController::parseTags($validated['tags_input'] ?? null), 0, ExerciseController::MAX_TAGS);

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
            'description' => 'nullable|string|max:1000',
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
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
