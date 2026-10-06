<?php

namespace App\Models;

use App\Support\CardScores;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetitionHole extends Model
{
    protected $fillable = [
        'competition_id',
        'number',
        'par',
        'distance_m',
    ];

    protected $casts = [
        'number' => 'integer',
        'par' => 'integer',
        'distance_m' => 'integer',
    ];

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function shots(): HasMany
    {
        return $this->hasMany(CompetitionShot::class)->orderBy('shot_number');
    }

    /**
     * Swaps the loaded shots for the ones that count (one agreed copy per player,
     * conflicts and unfinished holes dropped), so totals and stats read official scores.
     */
    public function useOfficialShots(): static
    {
        return $this->setRelation('shots', CardScores::officialShots($this->shots));
    }
}
