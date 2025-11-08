<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
    ];

    protected $casts = [
        'planned_minutes' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $planned): void {
            do {
                $uuid = UuidV7::generate();
            } while (self::where('uuid', $uuid)->exists());

            $planned->uuid = $uuid;

            if (! $planned->team_id && Auth::user()?->currentTeamRelation) {
                $planned->team_id = Auth::user()->currentTeamRelation->id; // Child-Team (nicht dynamisch)
            }

            if (! $planned->user_id && Auth::user()) {
                $planned->user_id = Auth::user()->id;
            }

            // Deaktiviere alle vorherigen aktiven Einträge für diesen Kontext
            self::where('context_type', $planned->context_type)
                ->where('context_id', $planned->context_id)
                ->where('is_active', true)
                ->update(['is_active' => false]);
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

    public function additionalContexts(): HasMany
    {
        return $this->hasMany(OrganizationTimePlannedContext::class, 'planned_id');
    }

    public function scopeForContext($query, string $type, int $id)
    {
        return $query->whereHas('additionalContexts', function ($q) use ($type, $id) {
            $q->where('context_type', $type)
              ->where('context_id', $id);
        });
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

    public function getHoursAttribute(): float
    {
        return round(($this->planned_minutes ?? 0) / 60, 2);
    }
}

