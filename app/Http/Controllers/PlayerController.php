<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlayerController extends Controller
{
    public const SORTS = ['rating' => 'Top rated', 'active' => 'Most rounds', 'name' => 'Name', 'newest' => 'Newest'];

    /** Player directory: search by name, sorted by rating by default. */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(array_keys(self::SORTS))],
        ]);
        $query = trim($filters['q'] ?? '');
        $sort = $filters['sort'] ?? 'rating';

        $players = User::withoutGuests()
            ->when($query !== '', fn ($users) => $users->where('name', 'like', '%' . addcslashes($query, '%_\\') . '%'))
            ->withCount([
                'roundRatings',
                'competitionRegistrations as events_count' => fn ($registrations) => $registrations->whereHas('competition', fn ($c) => $c->where('status', 'completed')),
            ])
            ->when($sort === 'rating', fn ($users) => $users->orderByRaw('rating IS NULL')->orderByDesc('rating')->orderByDesc('round_ratings_count'))
            ->when($sort === 'active', fn ($users) => $users->orderByDesc('round_ratings_count')->orderByDesc('events_count'))
            ->when($sort === 'newest', fn ($users) => $users->orderByDesc('created_at'))
            ->orderBy('name')
            ->paginate(24)
            ->withQueryString();

        $data = compact('players', 'query', 'sort') + ['sorts' => self::SORTS];

        // The search box swaps in just the results while you type
        if ($request->header('X-Partial') === 'results') {
            return view('players.partials.results', $data);
        }

        return view('players.index', $data + [
            'stats' => [
                'players' => User::withoutGuests()->count(),
                'rated' => User::withoutGuests()->whereNotNull('rating')->count(),
                'top' => User::withoutGuests()->max('rating'),
            ],
        ]);
    }
}
