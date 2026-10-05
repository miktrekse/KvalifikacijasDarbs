<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A player's rated tournament round, with their rating before and after it. */
class RoundRating extends Model
{
    protected $fillable = [
        'competition_id',
        'user_id',
        'course_id',
        'strokes',
        'par',
        'round_rating',
        'rating_before',
        'rating_after',
        'rating_change',
        'rounds_counted',
        'is_propagator',
        'played_at',
    ];

    protected $casts = [
        'strokes' => 'integer',
        'par' => 'integer',
        'round_rating' => 'integer',
        'rating_before' => 'integer',
        'rating_after' => 'integer',
        'rating_change' => 'integer',
        'rounds_counted' => 'integer',
        'is_propagator' => 'boolean',
        'played_at' => 'datetime',
    ];

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function relativeToPar(): int
    {
        return $this->strokes - $this->par;
    }
}
