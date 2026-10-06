<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitionShot extends Model
{
    /** Same landing zones as training rounds, so stats are computed the same way. */
    public const RESULTS = TrainingRoundShot::RESULTS;

    public const OB_LIES = TrainingRoundShot::OB_LIES;

    protected $fillable = [
        'competition_hole_id',
        'user_id',
        'recorded_by',
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
        return $this->belongsTo(CompetitionHole::class, 'competition_hole_id');
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
