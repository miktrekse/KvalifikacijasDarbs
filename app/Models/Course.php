<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A rated course layout. par_rating is what a par round is worth;
 * every stroke under or over par adds or removes points_per_stroke.
 */
class Course extends Model
{
    public const BASE_PAR_RATING = 950;

    public const POINTS_PER_STROKE = 7;

    protected $fillable = [
        'name',
        'name_key',
        'holes',
        'par',
        'par_rating',
        'points_per_stroke',
        'rated_rounds',
        'rated_events',
    ];

    protected $casts = [
        'holes' => 'integer',
        'par' => 'integer',
        'par_rating' => 'float',
        'points_per_stroke' => 'float',
        'rated_rounds' => 'integer',
        'rated_events' => 'integer',
    ];

    public static function keyFor(string $name): string
    {
        return Str::slug(Str::ascii($name)) ?: 'course';
    }

    /** Round rating for a score on this layout, e.g. par = 950, −3 = 971, +2 = 936. */
    public function ratingFor(int $strokes, ?float $parRating = null): int
    {
        return (int) round(($parRating ?? $this->par_rating) - $this->points_per_stroke * ($strokes - $this->par));
    }

    public function competitions(): HasMany
    {
        return $this->hasMany(Competition::class);
    }

    public function roundRatings(): HasMany
    {
        return $this->hasMany(RoundRating::class);
    }
}
