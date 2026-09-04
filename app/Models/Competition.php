<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Competition extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'event_date',
        'location',
        'course_name',
        'format',
        'divisions',
        'holes',
        'entry_fee',
        'currency',
        'max_participants',
        'registration_link',
        'registration_deadline',
        'status',
        'is_approved',
        'is_public',
        'results_link',
        'notes',
    ];

    protected $casts = [
        'event_date' => 'date',
        'registration_deadline' => 'datetime',
        'entry_fee' => 'decimal:2',
        'holes' => 'integer',
        'max_participants' => 'integer',
        'is_approved' => 'boolean',
        'is_public' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getDivisionsArrayAttribute(): array
    {
        if (empty($this->divisions)) {
            return [];
        }
        return is_array($this->divisions) ? $this->divisions : json_decode($this->divisions, true) ?? [];
    }

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('event_date', '>=', now()->toDateString())
                    ->where('status', 'upcoming');
    }

    public function isEditable(): bool
    {
        return true;
    }

    public function canApprove(User $user): bool
    {
        return $user->isAdmin();
    }
}
