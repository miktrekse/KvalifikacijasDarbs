<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show(User $user)
    {
        $user->load([
            'competitionRegistrations' => fn ($query) => $query->with('competition')->latest(),
        ]);

        $registeredCompetitions = $user->competitionRegistrations;
        $playedCompetitions = $registeredCompetitions
            ->filter(fn ($registration) => $registration->competition?->status === 'completed');

        return view('profile.show', compact('user', 'registeredCompetitions', 'playedCompetitions'));
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:male,female'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'rating' => ['nullable', 'integer', 'min:0', 'max:1100'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $validated['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($validated);

        return redirect()->route('profile.show', $user)->with('success', 'Profile updated successfully.');
    }
}
