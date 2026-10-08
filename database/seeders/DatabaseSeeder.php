<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Everything a fresh install needs: `php artisan db:seed` (also run by `composer setup`).
 * Every seeder is safe to run again.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            GuestUserSeeder::class,
            CategorySeeder::class,
            ExerciseSeeder::class,
        ]);

        // Test accounts with known passwords are for local and demo installs only, never production
        if (!app()->isProduction()) {
            $this->call(DemoUserSeeder::class);
        }
    }
}
