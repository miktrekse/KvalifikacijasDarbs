<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'gender',
        'date_of_birth',
        'rating',
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
            'rating' => 'integer',
            'password' => 'hashed',
        ];
    }

    public function age(): ?int
    {
        return $this->date_of_birth?->age;
    }

    /**
     * Highest rating allowed in each amateur division (PDGA style). Players may always
     * play up into a higher division, but not down into one rated below them.
     * Pro, age and junior divisions have no cap.
     */
    public const DIVISION_MAX_RATINGS = [
        'MA1' => 929, 'MA2' => 879, 'MA3' => 819, 'MA4' => 749,
        'FA2' => 879, 'FA3' => 819, 'FA4' => 749,
    ];

    private const FEMALE_DIVISIONS = ['FPO', 'FA2', 'FA3', 'FA4', 'FP40', 'FJ18'];

    /** [minimum age, maximum age] for the standard age-limited divisions. */
    private const DIVISION_AGES = [
        'MP40' => [40, null], 'MP50' => [50, null], 'MP60' => [60, null], 'FP40' => [40, null],
        'MJ18' => [null, 17], 'FJ18' => [null, 17], 'MJ15' => [null, 14],
    ];

    /**
     * Why this player can't enter a division, or null when they can. Covers the standard
     * gender, age and rating limits plus an organizer's custom rule for the division.
     * Unrated players can enter any division until they have an official rating.
     */
    public function divisionBlocker(string $division, ?array $customRule = null): ?string
    {
        $age = $this->age();

        if (in_array($division, self::FEMALE_DIVISIONS, true) && $this->gender !== 'female') {
            return 'Women only';
        }
        if ([$minAge, $maxAge] = self::DIVISION_AGES[$division] ?? null) {
            if ($age === null) {
                return 'Needs your date of birth';
            }
            if ($minAge !== null && $age < $minAge) {
                return "Age {$minAge}+";
            }
            if ($maxAge !== null && $age > $maxAge) {
                return 'Under ' . ($maxAge + 1) . ' only';
            }
        }
        $maxRating = self::DIVISION_MAX_RATINGS[$division] ?? null;
        if ($maxRating !== null && $this->rating !== null && $this->rating > $maxRating) {
            return "Rated {$maxRating} or below (you're {$this->rating})";
        }

        if ($customRule) {
            if ($customRule['gender'] !== 'any' && $this->gender !== $customRule['gender']) {
                return ucfirst($customRule['gender']) . ' only';
            }
            if (($customRule['min_age'] !== null || $customRule['max_age'] !== null) && $age === null) {
                return 'Needs your date of birth';
            }
            if ($customRule['min_age'] !== null && $age < $customRule['min_age']) {
                return "Age {$customRule['min_age']}+";
            }
            if ($customRule['max_age'] !== null && $age > $customRule['max_age']) {
                return "Age {$customRule['max_age']} or under";
            }
            if ($customRule['min_rating'] !== null && ($this->rating === null || $this->rating < $customRule['min_rating'])) {
                return "Rated {$customRule['min_rating']}+";
            }
        }

        return null;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    /** Shared read-only account used by "Continue as guest". */
    public const GUEST_EMAIL = 'guest@discstats.local';

    /** The app's own account domain: nobody can register or be given an address on it. */
    private const RESERVED_EMAIL_DOMAIN = 'discstats.local';

    public static function isReservedEmail(string $email): bool
    {
        return Str::lower(Str::afterLast(trim($email), '@')) === self::RESERVED_EMAIL_DOMAIN;
    }

    public function isGuest(): bool
    {
        return $this->role === 'guest';
    }

    /** Real accounts only: the shared guest login isn't a user and stays out of lists, counts and searches. */
    public function scopeWithoutGuests($query)
    {
        return $query->where('role', '!=', 'guest');
    }

    public const VERIFY_AFTER_COMPETITIONS = 3;

    public function isVerified(): bool
    {
        return $this->role === 'verified';
    }

    /** Verified users and admins may create competitions and publish exercises. */
    public function canPublish(): bool
    {
        return $this->isVerified() || $this->isAdmin();
    }

    public function completedCompetitionsCount(): int
    {
        return $this->competitionRegistrations()
            ->whereHas('competition', fn ($query) => $query->where('status', 'completed'))
            ->count();
    }

    /** Promotes a regular user to verified after enough completed competitions. */
    public function syncVerification(): void
    {
        if ($this->isUser() && $this->completedCompetitionsCount() >= self::VERIFY_AFTER_COMPETITIONS) {
            $this->update(['role' => 'verified']);
        }
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

    /** Rated tournament rounds, newest first: the player's tournament log. */
    public function roundRatings(): HasMany
    {
        return $this->hasMany(RoundRating::class)->orderByDesc('played_at')->orderByDesc('id');
    }

    public function trainingRounds(): HasMany
    {
        return $this->hasMany(TrainingRound::class);
    }

    public function trainingRoundsJoined(): BelongsToMany
    {
        return $this->belongsToMany(TrainingRound::class, 'training_round_players')->withTimestamps();
    }
}
