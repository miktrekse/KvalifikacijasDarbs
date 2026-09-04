<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@discstats.com',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
            'avatar' => null,
        ]);

        $this->command->info('Admin user created successfully!');
        $this->command->info('Email: admin@discstats.com');
        $this->command->info('Password: admin123');
    }
}
