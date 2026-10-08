<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates the first administrator, so a fresh install has someone who can approve
 * competitions and promote players. Safe to run again: once an admin exists, nothing
 * is changed (in particular no password is reset).
 *
 * The account comes from ADMIN_NAME / ADMIN_EMAIL / ADMIN_PASSWORD in .env. Without
 * ADMIN_PASSWORD a random password is generated and printed once, so no install ever
 * starts with a password that is written in the repository.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (User::where('role', 'admin')->exists()) {
            $this->command?->info('An admin account already exists — left unchanged.');

            return;
        }

        $email = env('ADMIN_EMAIL') ?: 'admin@discstats.com';

        $existing = User::where('email', $email)->first();
        if ($existing) {
            $existing->update(['role' => 'admin']);
            $this->command?->warn("{$email} already had an account; it is now an admin (its password is unchanged).");

            return;
        }

        $password = env('ADMIN_PASSWORD') ?: Str::password(16, symbols: false);

        User::forceCreate([
            'name' => env('ADMIN_NAME') ?: 'Admin',
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'admin',
        ]);

        $this->command?->info("Admin account created: {$email}");
        if (!env('ADMIN_PASSWORD')) {
            $this->command?->warn("Generated password (shown only this once, sign in and change it): {$password}");
        }
    }
}
