<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingRoundHole extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_round_id',
        'number',
        'par',
        'distance_m',
    ];

    protected $casts = [
        'number' => 'integer',
        'par' => 'integer',
        'distance_m' => 'integer',
    ];

    public function round(): BelongsTo
    {
        return $this->belongsTo(TrainingRound::class, 'training_round_id');
    }

    public function shots(): HasMany
    {
        return $this->hasMany(TrainingRoundShot::class)->orderBy('shot_number');
    }
}
