<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Ready-made accounts for trying the app (e.g. for reviewers) without signing up:
 * one verified player, who can publish exercises and create competitions, and one regular player.
 * Safe to run again: existing accounts are reset to these details.
 */
class DemoUserSeeder extends Seeder
{
    public const USERS = [
        ['name' => 'Verified Tester', 'email' => 'verified@discstats.com', 'password' => 'verified123', 'role' => 'verified'],
        ['name' => 'Player Tester', 'email' => 'player@discstats.com', 'password' => 'player123', 'role' => 'user'],
    ];

    public function run(): void
    {
        foreach (self::USERS as $account) {
            User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => Hash::make($account['password']),
                    'role' => $account['role'],
                    // Filled in so division age and gender rules work when registering for competitions
                    'gender' => 'male',
                    'date_of_birth' => '1990-05-15',
                ]
            );

            $this->command?->info("{$account['role']}: {$account['email']} / {$account['password']}");
        }
    }
}
