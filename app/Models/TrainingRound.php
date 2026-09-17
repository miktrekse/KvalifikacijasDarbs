<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingRound extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'course_name',
        'course_lat',
        'course_lon',
        'course_locality',
        'holes_count',
        'status',
        'completed_at',
    ];

    protected $casts = [
        'course_lat' => 'decimal:7',
        'course_lon' => 'decimal:7',
        'holes_count' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function players(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'training_round_players')->withTimestamps();
    }

    public function holes(): HasMany
    {
        return $this->hasMany(TrainingRoundHole::class)->orderBy('number');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
