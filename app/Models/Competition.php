<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Support\CompetitionGrouping;
use App\Support\RatingEngine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class Competition extends Model
{
    use HasFactory;

    public const DEFAULT_START_TIME = '10:00';

    public const GROUPS_DRAWN_MINUTES_BEFORE = 30;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'event_date',
        'start_time',
        'location',
        'course_name',
        'format',
        'competition_type',
        'divisions',
        'division_rules',
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
        'division_rules' => 'array',
        'groups_assigned_at' => 'datetime',
        'rated_at' => 'datetime',
        'course_rating_before' => 'float',
        'course_rating_after' => 'float',
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

    public function registrations(): HasMany
    {
        return $this->hasMany(CompetitionRegistration::class);
    }

    /** True once every player on a card has holed out on every hole. */
    public function allRoundsFinished(): bool
    {
        $players = $this->registrations()->whereNotNull('competition_group_id')->count();
        $holes = $this->courseHoles()->count();
        if ($players === 0 || $holes === 0) {
            return false;
        }

        $holedOut = CompetitionShot::whereIn('competition_hole_id', $this->courseHoles()->select('id'))
            ->where('result', 'in_basket')
            ->whereIn('user_id', $this->registrations()->whereNotNull('competition_group_id')->select('user_id'))
            ->count();

        return $holedOut >= $players * $holes;
    }

    /**
     * Completes the tournament as soon as the last card holes out, then rates it:
     * round ratings, the course rating and every player's new rating.
     */
    public function finishIfComplete(): bool
    {
        if ($this->status !== 'ongoing' || !$this->allRoundsFinished()) {
            return false;
        }

        $this->update(['status' => 'completed']);
        app(RatingEngine::class)->recalculate();
        $this->registrations()->with('user')->get()->each(fn ($registration) => $registration->user?->syncVerification());

        return true;
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function roundRatings(): HasMany
    {
        return $this->hasMany(RoundRating::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(CompetitionGroup::class)->orderBy('number');
    }

    /** Named courseHoles because `holes` is the hole-count column. */
    public function courseHoles(): HasMany
    {
        return $this->hasMany(CompetitionHole::class)->orderBy('number');
    }

    /**
     * Start of the round. Times are entered as local course time, so they are
     * read in the competition timezone; competitions without a time start at 10:00.
     */
    public function startsAt(): Carbon
    {
        $time = $this->start_time ? substr($this->start_time, 0, 5) : self::DEFAULT_START_TIME;

        return Carbon::parse($this->event_date->toDateString() . ' ' . $time, config('app.competition_timezone'));
    }

    public function groupsDrawAt(): Carbon
    {
        return $this->startsAt()->subMinutes(self::GROUPS_DRAWN_MINUTES_BEFORE);
    }

    public function hasGroups(): bool
    {
        return $this->groups_assigned_at !== null;
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['completed', 'cancelled'], true);
    }

    public function scoringIsOpen(): bool
    {
        return !$this->isClosed() && $this->hasGroups() && now()->gte($this->startsAt());
    }

    /**
     * Moves the competition along its timeline: draws the groups 30 minutes
     * before the start and marks it ongoing once the round has started.
     * Called by the scheduler every minute and lazily whenever the page is opened.
     */
    public function syncLifecycle(): void
    {
        if ($this->isClosed()) {
            return;
        }

        if (!$this->hasGroups() && now()->gte($this->groupsDrawAt())) {
            CompetitionGrouping::assign($this);
        }

        if ($this->status === 'upcoming' && now()->gte($this->startsAt())) {
            $this->update(['status' => 'ongoing']);
        }
    }

    /**
     * Live standings: one row per registered player, ranked by score relative
     * to par over the holes they have finished (players yet to tee off last).
     */
    public function leaderboard(): Collection
    {
        $holes = $this->courseHoles()->with('shots')->get();
        $registrations = $this->registrations()->with(['user', 'group'])->get();
        $roundRatings = $this->roundRatings()->get()->keyBy('user_id');

        return $registrations->map(function (CompetitionRegistration $registration) use ($holes, $roundRatings) {
            $strokes = 0;
            $par = 0;
            $thru = 0;

            foreach ($holes as $hole) {
                $shots = $hole->shots->where('user_id', $registration->user_id);
                if ($shots->isEmpty() || $shots->last()->result !== 'in_basket') {
                    continue;
                }
                $strokes += $shots->sum('strokes');
                $par += $hole->par;
                $thru++;
            }

            return [
                'registration' => $registration,
                'user' => $registration->user,
                'division' => $registration->division,
                'group' => $registration->group?->number,
                'strokes' => $strokes,
                'relative' => $strokes - $par,
                'thru' => $thru,
                'round_rating' => $roundRatings->get($registration->user_id)?->round_rating,
            ];
        })->sort(fn (array $a, array $b) => [$a['thru'] === 0, $a['relative'], $a['strokes'], $a['user']->name]
            <=> [$b['thru'] === 0, $b['relative'], $b['strokes'], $b['user']->name])->values();
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
