<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Core\Models\Team;
use Symfony\Component\Uid\UuidV7;

class OrganizationContext extends Model
{
    use SoftDeletes;

    protected $table = 'organization_contexts';

    protected $fillable = [
        'uuid',
        'contextable_type',
        'contextable_id',
        'organization_entity_id',
        'team_id',
        'include_children_relations',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'include_children_relations' => 'array',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $context): void {
            if (empty($context->uuid)) {
                do {
                    $uuid = UuidV7::generate();
                } while (self::where('uuid', $uuid)->exists());
                
                $context->uuid = $uuid;
            }
        });
    }

    /**
     * Polymorphe Beziehung zu Module Entity (z.B. Planner Project, CRM Contact)
     */
    public function contextable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Beziehung zu Organization Entity
     */
    public function organizationEntity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'organization_entity_id');
    }

    /**
     * Beziehung zu Team
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Scope für aktive Kontexte
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope für Kontexte mit Cascade-Relations
     */
    public function scopeWithChildren($query)
    {
        return $query->whereNotNull('include_children_relations');
    }

    /**
     * Scope für Kontexte eines bestimmten Module Entity Types
     */
    public function scopeForContextableType($query, string $type)
    {
        return $query->where('contextable_type', $type);
    }
}

