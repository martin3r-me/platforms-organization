<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

class OrganizationVsmFunction extends Model
{
    use SoftDeletes;

    protected $table = 'organization_vsm_functions';

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'team_id',
        'user_id',
        'root_entity_id',
        'description',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
        'deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                do {
                    $uuid = UuidV7::generate();
                } while (self::where('uuid', $uuid)->exists());
                $model->uuid = $uuid;
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    /**
     * Get all VSM functions for a team (global + entity-specific)
     */
    public static function getForTeam(int $teamId, ?int $rootEntityId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = self::where('team_id', $teamId)
            ->where('is_active', true);

        if ($rootEntityId) {
            // Get global (root_entity_id = NULL) + entity-specific (root_entity_id = X)
            $query->where(function ($q) use ($rootEntityId) {
                $q->whereNull('root_entity_id')
                  ->orWhere('root_entity_id', $rootEntityId);
            });
        } else {
            // Only global functions
            $query->whereNull('root_entity_id');
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Get VSM functions for an entity with hierarchy fallback
     * Searches: entity → parent → grandparent → ... → global
     */
    public static function getForEntityWithHierarchy(int $teamId, int $entityId): \Illuminate\Database\Eloquent\Collection
    {
        $entity = OrganizationEntity::find($entityId);
        if (!$entity) {
            return self::getForTeam($teamId, null); // Fallback to global
        }

        // Build hierarchy path: [entity, parent, grandparent, ...]
        $hierarchy = [];
        $current = $entity;
        while ($current) {
            $hierarchy[] = $current->id;
            $current = $current->parent;
        }

        // Search in hierarchy order
        $vsmFunctions = collect();
        $foundCodes = [];

        foreach ($hierarchy as $entityIdInPath) {
            $entitySpecific = self::where('team_id', $teamId)
                ->where('is_active', true)
                ->where('root_entity_id', $entityIdInPath)
                ->get();

            foreach ($entitySpecific as $vsmFunction) {
                if (!in_array($vsmFunction->code, $foundCodes)) {
                    $vsmFunctions->push($vsmFunction);
                    $foundCodes[] = $vsmFunction->code;
                }
            }
        }

        // Add global VSM functions for codes not found in hierarchy
        $globalVsmFunctions = self::where('team_id', $teamId)
            ->where('is_active', true)
            ->whereNull('root_entity_id')
            ->get();

        foreach ($globalVsmFunctions as $vsmFunction) {
            if (!in_array($vsmFunction->code, $foundCodes)) {
                $vsmFunctions->push($vsmFunction);
                $foundCodes[] = $vsmFunction->code;
            }
        }

        // Convert to Eloquent Collection
        return new \Illuminate\Database\Eloquent\Collection($vsmFunctions->sortBy('name')->values());
    }

    /**
     * Check if this is a global function
     */
    public function isGlobal(): bool
    {
        return is_null($this->root_entity_id);
    }

    /**
     * Check if this is entity-specific
     */
    public function isEntitySpecific(): bool
    {
        return !is_null($this->root_entity_id);
    }
}
