<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Removes a person from DiscStats without rewriting sport history.
 *
 * Deleting the user row would cascade into tournaments they organised, other players'
 * scorecards and shared training rounds, and change everyone's ratings after the fact.
 * Instead the account is anonymised: personal data, login and avatar are gone, while
 * results stay exactly as they were played (shown as "Deleted player").
 */
class AccountRemoval
{
    public const ANONYMOUS_NAME = 'Deleted player';

    public static function anonymize(User $user): void
    {
        $avatar = $user->avatar;
        $email = $user->email;

        DB::transaction(function () use ($user, $email) {
            // Their own exercise library is personal content, not shared history
            $user->exercises()->delete();
            $user->addedExercises()->detach();

            // Entries for events that haven't been drawn yet are withdrawn; played ones stay
            $user->competitionRegistrations()
                ->whereNull('competition_group_id')
                ->whereHas('competition', fn ($query) => $query->where('status', 'upcoming')->whereNull('groups_assigned_at'))
                ->delete();

            $user->forceFill([
                'name' => self::ANONYMOUS_NAME,
                // On the reserved domain, so nobody can register or reset this address
                'email' => 'deleted-' . $user->id . '@discstats.local',
                'password' => Str::random(64),
                'remember_token' => null,
                'email_verified_at' => null,
                'avatar' => null,
                'gender' => null,
                'date_of_birth' => null,
                'role' => 'user',
                'anonymized_at' => now(),
            ])->save();

            // Signed-in browsers are logged out (sessions live in the database)
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }
            DB::table('password_reset_tokens')->where('email', $email)->delete();
        });

        // The file goes only once the account no longer points at it
        if ($avatar) {
            Storage::disk('public')->delete($avatar);
        }
    }
}
