<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

class OrganizationCostCenter extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'organization_cost_centers';

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

    public function team()
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function user()
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    /**
     * Scope für aktive Kostenstellen
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get all cost centers for a team (global + entity-specific)
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
            // Only global cost centers
            $query->whereNull('root_entity_id');
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Get cost centers for an entity with hierarchy fallback
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

        // Search in hierarchy order - only get the FIRST match for each code
        $costCenters = collect();
        $foundCodes = [];

        // First: Check entity-specific cost centers
        foreach ($hierarchy as $entityIdInPath) {
            $entitySpecific = self::where('team_id', $teamId)
                ->where('is_active', true)
                ->where('root_entity_id', $entityIdInPath)
                ->get();

            foreach ($entitySpecific as $costCenter) {
                if (!in_array($costCenter->code, $foundCodes)) {
                    $costCenters->push($costCenter);
                    $foundCodes[] = $costCenter->code;
                }
            }
        }

        // Second: Add global cost centers for codes not found in hierarchy
        $globalCostCenters = self::where('team_id', $teamId)
            ->where('is_active', true)
            ->whereNull('root_entity_id')
            ->get();

        foreach ($globalCostCenters as $costCenter) {
            if (!in_array($costCenter->code, $foundCodes)) {
                $costCenters->push($costCenter);
                $foundCodes[] = $costCenter->code;
            }
        }

        // Convert to Eloquent Collection
        return new \Illuminate\Database\Eloquent\Collection($costCenters->sortBy('name')->values());
    }

    /**
     * Check if this is a global cost center
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

    /**
     * Organisationseinheiten, die dieser Kostenstelle zugeordnet sind
     */
    public function entities()
    {
        return $this->hasMany(OrganizationEntity::class, 'cost_center_id');
    }
}


