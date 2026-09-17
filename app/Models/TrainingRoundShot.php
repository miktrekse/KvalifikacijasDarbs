<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingRoundShot extends Model
{
    use HasFactory;

    /**
     * Where the disc landed after the shot, and how many strokes it costs.
     * Out of bounds carries a one-stroke penalty on top of the throw itself.
     */
    public const RESULTS = [
        'fairway' => ['label' => 'Fairway', 'strokes' => 1],
        'off_fairway' => ['label' => 'Off Fairway', 'strokes' => 1],
        'circle_2' => ['label' => 'Circle 2', 'strokes' => 1],
        'circle_1' => ['label' => 'Circle 1', 'strokes' => 1],
        'out_of_bounds' => ['label' => 'Out of Bounds', 'strokes' => 2],
        'in_basket' => ['label' => 'In the Basket', 'strokes' => 1],
    ];

    protected $fillable = [
        'training_round_hole_id',
        'user_id',
        'shot_number',
        'result',
        'strokes',
    ];

    protected $casts = [
        'shot_number' => 'integer',
        'strokes' => 'integer',
    ];

    public function hole(): BelongsTo
    {
        return $this->belongsTo(TrainingRoundHole::class, 'training_round_hole_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getLabelAttribute(): string
    {
        return self::RESULTS[$this->result]['label'] ?? $this->result;
    }
}
