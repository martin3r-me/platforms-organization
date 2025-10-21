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
