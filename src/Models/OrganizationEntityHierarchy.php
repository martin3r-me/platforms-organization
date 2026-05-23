<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationEntityHierarchy extends Model
{
    protected $table = 'organization_entity_hierarchy';

    protected $fillable = [
        'perspective_id',
        'entity_id',
        'parent_entity_id',
        'sort_order',
        'team_id',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function perspective()
    {
        return $this->belongsTo(OrganizationPerspective::class, 'perspective_id');
    }

    public function entity()
    {
        return $this->belongsTo(OrganizationEntity::class, 'entity_id');
    }

    public function parentEntity()
    {
        return $this->belongsTo(OrganizationEntity::class, 'parent_entity_id');
    }

    public function scopeForPerspective($query, int $perspectiveId)
    {
        return $query->where('perspective_id', $perspectiveId);
    }

    /**
     * Returns [entity_id => parent_entity_id|null] for all entries in a perspective.
     */
    public static function getParentMap(int $perspectiveId): array
    {
        return static::where('perspective_id', $perspectiveId)
            ->pluck('parent_entity_id', 'entity_id')
            ->toArray();
    }
}
