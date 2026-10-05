<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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

    public function par(): int
    {
        return (int) $this->holes->sum('par');
    }

    /**
     * Score for each player over the holes they have holed out on, keyed by user id.
     * Expects players and holes.shots to be loaded.
     */
    public function playerTotals(): Collection
    {
        return $this->players->mapWithKeys(function (User $player) {
            $strokes = 0;
            $par = 0;
            $thru = 0;

            foreach ($this->holes as $hole) {
                $shots = $hole->shots->where('user_id', $player->id);
                if ($shots->isEmpty() || $shots->last()->result !== 'in_basket') {
                    continue;
                }
                $strokes += $shots->sum('strokes');
                $par += $hole->par;
                $thru++;
            }

            return [$player->id => [
                'player' => $player,
                'strokes' => $strokes,
                'relative' => $strokes - $par,
                'thru' => $thru,
            ]];
        });
    }

    /** Strokes on one hole for a player, or null until they hole out. */
    public function holeScore(TrainingRoundHole $hole, int $userId): ?int
    {
        $shots = $hole->shots->where('user_id', $userId);

        return $shots->isNotEmpty() && $shots->last()->result === 'in_basket' ? (int) $shots->sum('strokes') : null;
    }
}
