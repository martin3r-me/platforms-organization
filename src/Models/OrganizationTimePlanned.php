<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Symfony\Component\Uid\UuidV7;

class OrganizationTimePlanned extends Model
{
    protected $table = 'organization_time_planned';

    protected $fillable = [
        'uuid',
        'team_id',
        'user_id',
        'context_type',
        'context_id',
        'planned_minutes',
        'note',
        'is_active',
        'valid_from',
        'valid_to',
    ];

    protected $casts = [
        'planned_minutes' => 'integer',
        'is_active' => 'boolean',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $planned): void {
            do {
                $uuid = UuidV7::generate();
            } while (self::where('uuid', $uuid)->exists());

            $planned->uuid = $uuid;

            if (! $planned->team_id && Auth::user()?->currentTeamRelation) {
                $planned->team_id = Auth::user()->currentTeamRelation->id;
            }

            if (! $planned->user_id && Auth::user()) {
                $planned->user_id = Auth::user()->id;
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function context(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForContextKey($query, string $type, int $id)
    {
        return $query->where('context_type', $type)
            ->where('context_id', $id);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForDate($query, $date)
    {
        return $query->where(function ($q) use ($date) {
            $q->where(fn ($q2) => $q2->whereNull('valid_from')->orWhere('valid_from', '<=', $date))
              ->where(fn ($q2) => $q2->whereNull('valid_to')->orWhere('valid_to', '>=', $date));
        });
    }

    public function scopeForPeriod($query, $from, $to)
    {
        return $query->where(function ($q) use ($from, $to) {
            $q->where(fn ($q2) => $q2->whereNull('valid_to')->orWhere('valid_to', '>=', $from))
              ->where(fn ($q2) => $q2->whereNull('valid_from')->orWhere('valid_from', '<=', $to));
        });
    }

    public function getPeriodLabelAttribute(): string
    {
        if ($this->valid_from === null && $this->valid_to === null) {
            return 'unbefristet';
        }

        $from = $this->valid_from?->format('d.m.Y') ?? '∞';
        $to = $this->valid_to?->format('d.m.Y') ?? '∞';

        return "{$from} – {$to}";
    }

    public function getHoursAttribute(): float
    {
        return round(($this->planned_minutes ?? 0) / 60, 2);
    }
}
