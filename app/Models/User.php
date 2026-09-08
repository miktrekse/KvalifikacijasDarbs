<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'gender',
        'date_of_birth',
        'password',
        'role',
        'avatar',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'password' => 'hashed',
        ];
    }

    public function age(): ?int
    {
        return $this->date_of_birth?->age;
    }

    public function meetsDivisionRequirements(string $division, ?int $rating = null): bool
    {
        $age = $this->age();
        $femaleDivisions = ['FPO', 'FA2', 'FA3', 'FA4', 'FP40', 'FJ18'];

        if (in_array($division, $femaleDivisions, true) && $this->gender !== 'female') {
            return false;
        }

        if ($division === 'MP40' && ($age === null || $age < 40)) return false;
        if ($division === 'MP50' && ($age === null || $age < 50)) return false;
        if ($division === 'MP60' && ($age === null || $age < 60)) return false;
        if ($division === 'FP40' && ($age === null || $age < 40)) return false;
        if (in_array($division, ['MJ18', 'FJ18'], true) && ($age === null || $age >= 18)) return false;
        if ($division === 'MJ15' && ($age === null || $age >= 15)) return false;

        return match ($division) {
            'MPO' => $rating === null || $rating >= 930,
            'MA1' => $rating === null || ($rating >= 880 && $rating <= 929),
            'MA2', 'FA2' => $rating === null || ($rating >= 820 && $rating <= 879),
            'MA3', 'FA3' => $rating === null || ($rating >= 750 && $rating <= 819),
            'MA4', 'FA4' => $rating === null || ($rating >= 0 && $rating <= 749),
            'FPO' => $rating === null || $rating >= 880,
            default => true,
        };
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class);
    }

    public function addedExercises(): BelongsToMany
    {
        return $this->belongsToMany(Exercise::class, 'exercise_user')
            ->withPivot(['is_favorite', 'last_used_at'])
            ->withTimestamps();
    }

    public function competitionRegistrations(): HasMany
    {
        return $this->hasMany(CompetitionRegistration::class);
    }
}
