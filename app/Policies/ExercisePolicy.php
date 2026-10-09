<?php

namespace App\Policies;

use App\Models\Exercise;
use App\Models\User;

/**
 * Who may do what with a drill. A private drill exists only for its author (and admins):
 * every endpoint that reaches a drill by id checks `view` first, so guessing ids can't
 * open, save or comment on someone else's private drill.
 */
class ExercisePolicy
{
    public function view(User $user, Exercise $exercise): bool
    {
        return $exercise->is_public || $exercise->user_id === $user->id || $user->isAdmin();
    }

    public function update(User $user, Exercise $exercise): bool
    {
        return $exercise->user_id === $user->id;
    }

    public function delete(User $user, Exercise $exercise): bool
    {
        return $exercise->user_id === $user->id;
    }

    public function save(User $user, Exercise $exercise): bool
    {
        return $this->view($user, $exercise);
    }

    public function comment(User $user, Exercise $exercise): bool
    {
        return $this->view($user, $exercise);
    }
}
