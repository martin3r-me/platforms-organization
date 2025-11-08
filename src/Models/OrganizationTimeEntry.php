<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Symfony\Component\Uid\UuidV7;

class OrganizationTimeEntry extends Model
{
    use SoftDeletes;

    protected $table = 'organization_time_entries';

    protected $fillable = [
        'uuid',
        'team_id',
        'user_id',
        'context_type',
        'context_id',
        'root_context_type',
        'root_context_id',
        'work_date',
        'minutes',
        'rate_cents',
        'amount_cents',
        'is_billed',
        'metadata',
        'note',
    ];

    protected $casts = [
        'work_date' => 'date',
        'minutes' => 'integer',
        'rate_cents' => 'integer',
        'amount_cents' => 'integer',
        'is_billed' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $entry): void {
            do {
                $uuid = UuidV7::generate();
            } while (self::where('uuid', $uuid)->exists());

            $entry->uuid = $uuid;

            if (! $entry->team_id && Auth::user()?->currentTeamRelation) {
                $entry->team_id = Auth::user()->currentTeamRelation->id; // Child-Team (nicht dynamisch)
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

    public function rootContext(): MorphTo
    {
        return $this->morphTo('rootContext', 'root_context_type', 'root_context_id');
    }

    public function additionalContexts(): HasMany
    {
        return $this->hasMany(OrganizationTimeEntryContext::class, 'time_entry_id');
    }

    public function scopeForContextKey($query, string $type, int $id)
    {
        return $query->where('context_type', $type)
            ->where('context_id', $id);
    }

    /**
     * Scope für Abfragen über Kontext-Kaskade.
     * Findet alle Time-Entries, die über ihre Contexts zu einem bestimmten Kontext gehören.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type Model-Klasse
     * @param int $id Model-ID
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForContext($query, string $type, int $id)
    {
        return $query->whereHas('additionalContexts', function ($q) use ($type, $id) {
            $q->where('context_type', $type)
              ->where('context_id', $id);
        });
    }

    public function getHoursAttribute(): float
    {
        return round(($this->minutes ?? 0) / 60, 2);
    }
}

