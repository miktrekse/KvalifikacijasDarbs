<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Putting', 'description' => 'Short-range putting practice.', 'color' => '#16A34A'],
            ['name' => 'Driving', 'description' => 'Tee shots and power control.', 'color' => '#2563EB'],
            ['name' => 'Approches', 'description' => 'Approach shots and upshots.', 'color' => '#D97706'],
            ['name' => 'Scramble', 'description' => 'Recovery shots and difficult lies.', 'color' => '#9333EA'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(['name' => $category['name']], $category);
        }
    }
}