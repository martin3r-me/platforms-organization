<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Symfony\Component\Uid\UuidV7;

class OrganizationTimePeriod extends Model
{
    protected $table = 'organization_time_periods';

    protected $fillable = [
        'uuid',
        'team_id',
        'user_id',
        'context_type',
        'context_id',
        'planned_start',
        'planned_end',
        'note',
        'is_active',
    ];

    protected $casts = [
        'planned_start' => 'date',
        'planned_end' => 'date',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $period): void {
            do {
                $uuid = UuidV7::generate();
            } while (self::where('uuid', $uuid)->exists());

            $period->uuid = $uuid;

            if (! $period->team_id && Auth::user()?->currentTeamRelation) {
                $period->team_id = Auth::user()->currentTeamRelation->id;
            }

            if (! $period->user_id && Auth::user()) {
                $period->user_id = Auth::user()->id;
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
}
