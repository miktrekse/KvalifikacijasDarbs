<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => 'user',
            // Filled in so division gender and age rules can be checked
            'gender' => 'male',
            'date_of_birth' => '1990-05-15',
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => ['role' => 'admin']);
    }

    /** A DiscStats "verified" player (enough completed tournaments), not a verified email. */
    public function verifiedPlayer(): static
    {
        return $this->state(fn (array $attributes) => ['role' => 'verified']);
    }

    public function guest(): static
    {
        return $this->state(fn (array $attributes) => ['role' => 'guest']);
    }
}
