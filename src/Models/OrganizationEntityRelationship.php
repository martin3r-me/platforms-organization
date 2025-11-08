<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Symfony\Component\Uid\UuidV7;

class OrganizationEntityRelationship extends Model
{
    use SoftDeletes;

    protected $table = 'organization_entity_relationships';

    protected $fillable = [
        'uuid',
        'from_entity_id',
        'to_entity_id',
        'relation_type_id',
        'valid_from',
        'valid_to',
        'team_id',
        'user_id',
        'metadata',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_to' => 'date',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $relationship) {
            if (empty($relationship->uuid)) {
                do {
                    $uuid = UuidV7::generate();
                } while (self::where('uuid', $uuid)->exists());
                
                $relationship->uuid = $uuid;
            }

            if (! $relationship->user_id) {
                $relationship->user_id = Auth::id();
            }

            if (! $relationship->team_id) {
                $relationship->team_id = Auth::user()?->currentTeamRelation?->id;
            }
        });
    }

    /**
     * Von-Wem geht die Beziehung aus
     */
    public function fromEntity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'from_entity_id');
    }

    /**
     * Zu-Wem geht die Beziehung
     */
    public function toEntity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'to_entity_id');
    }

    /**
     * Relation Type
     */
    public function relationType(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntityRelationType::class, 'relation_type_id');
    }

    /**
     * Team
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * User (Ersteller)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Hilfreicher Label-Accessor für UI
     */
    public function getSummaryAttribute(): string
    {
        $fromName = $this->fromEntity?->name ?? 'Unbekannt';
        $relationLabel = $this->relationType?->name ?? 'Unbekannt';
        $toName = $this->toEntity?->name ?? 'Unbekannt';
        
        return "{$fromName} {$relationLabel} {$toName}";
    }

    /**
     * Scope für aktive Relations (zeitlich gültig)
     * Hinweis: Dies prüft nur die zeitliche Gültigkeit, nicht ob die Relation gelöscht ist
     */
    public function scopeActive($query)
    {
        $now = now()->toDateString();
        return $query->where(function ($q) use ($now) {
            $q->whereNull('valid_from')
              ->orWhere('valid_from', '<=', $now);
        })->where(function ($q) use ($now) {
            $q->whereNull('valid_to')
              ->orWhere('valid_to', '>=', $now);
        });
    }

    /**
     * Scope für Relations von einer Entity
     */
    public function scopeFromEntity($query, $entityId)
    {
        return $query->where('from_entity_id', $entityId);
    }

    /**
     * Scope für Relations zu einer Entity
     */
    public function scopeToEntity($query, $entityId)
    {
        return $query->where('to_entity_id', $entityId);
    }
}

