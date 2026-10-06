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

    /** Where play resumes after an OB throw (the penalty stroke is already in RESULTS). */
    public const OB_LIES = [
        'circle_1' => 'Circle 1',
        'circle_2' => 'Circle 2',
        'fairway' => 'Fairway',
        'off_fairway' => 'Off fairway',
        'tee' => 'Re-tee',
        'drop_zone' => 'Drop zone',
    ];

    /** Highest number a hole can be scored with when only the score is kept. */
    public const MAX_HOLE_SCORE = 20;

    /** Positions inside Circle 2: makes from here are putts, anything further is a throw-in. */
    public const PUTTING_LIES = ['circle_1', 'circle_2'];

    protected $fillable = [
        'training_round_hole_id',
        'user_id',
        'shot_number',
        'result',
        'ob_lie',
        'strokes',
        'distance_m',
        'score_only',
    ];

    protected $casts = [
        'shot_number' => 'integer',
        'strokes' => 'integer',
        'distance_m' => 'integer',
        'score_only' => 'boolean',
    ];

    public function hole(): BelongsTo
    {
        return $this->belongsTo(TrainingRoundHole::class, 'training_round_hole_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Distance only counts for a make from outside Circle 2 (a throw-in), so it is
     * dropped for putts and for any result other than in the basket.
     */
    public static function throwInDistance(string $result, ?int $distance, ?Model $previousShot): ?int
    {
        if ($result !== 'in_basket' || !$distance) {
            return null;
        }

        $lie = $previousShot === null
            ? 'tee'
            : ($previousShot->result === 'out_of_bounds' ? $previousShot->ob_lie : $previousShot->result);

        return in_array($lie, self::PUTTING_LIES, true) ? null : $distance;
    }

    public function getLabelAttribute(): string
    {
        return self::RESULTS[$this->result]['label'] ?? $this->result;
    }
}
