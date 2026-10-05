<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetitionGroup extends Model
{
    protected $fillable = [
        'competition_id',
        'number',
        'starting_hole',
    ];

    protected $casts = [
        'number' => 'integer',
        'starting_hole' => 'integer',
    ];

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(CompetitionRegistration::class);
    }
}
