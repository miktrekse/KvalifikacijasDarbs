<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The shared read-only account behind "Continue as guest", created at install time with
 * a random password nobody knows (guest sign-in never asks for one). Safe to run again.
 */
class GuestUserSeeder extends Seeder
{
    public function run(): void
    {
        $guest = User::where('email', User::GUEST_EMAIL)->first();

        if (!$guest) {
            User::forceCreate([
                'name' => 'Guest',
                'email' => User::GUEST_EMAIL,
                'password' => Hash::make(Str::random(64)),
                'role' => 'guest',
            ]);
            $this->command?->info('Guest account created.');

            return;
        }

        if (!$guest->isGuest()) {
            // Someone registered the guest address as a normal account: take it back and lock it down
            $guest->forceFill([
                'role' => 'guest',
                'password' => Hash::make(Str::random(64)),
                'remember_token' => null,
            ])->save();
            $this->command?->warn('The guest address belonged to a normal account; it is the read-only guest again.');
        }
    }
}
