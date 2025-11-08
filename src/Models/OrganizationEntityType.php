<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrganizationEntityType extends Model
{
    use HasFactory;

    protected $table = 'organization_entity_types';

    protected $fillable = [
        'code',
        'name',
        'description',
        'icon',
        'sort_order',
        'is_active',
        'entity_type_group_id',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Scope für aktive Entity Types
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope für sortierte Entity Types
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Finde Entity Type nach Code
     */
    public static function findByCode(string $code): ?self
    {
        return static::where('code', $code)->first();
    }

    /**
     * Alle aktiven Entity Types geordnet
     */
    public static function getActiveOrdered()
    {
        return static::active()->ordered()->get();
    }

    /**
     * Beziehung zu Entity Type Group
     */
    public function group()
    {
        return $this->belongsTo(OrganizationEntityTypeGroup::class, 'entity_type_group_id');
    }

    /**
     * Entity Types nach Gruppe
     */
    public static function getByGroup($groupId)
    {
        return static::where('entity_type_group_id', $groupId)
                    ->active()
                    ->ordered()
                    ->get();
    }

    /**
     * Entity Types nach Gruppenname
     */
    public static function getByGroupName(string $groupName)
    {
        return static::whereHas('group', function($query) use ($groupName) {
            $query->where('name', $groupName);
        })
        ->active()
        ->ordered()
        ->get();
    }

    /**
     * Beziehung zu Model Mappings
     */
    public function modelMappings()
    {
        return $this->hasMany(OrganizationEntityTypeModelMapping::class, 'entity_type_id');
    }

    /**
     * Aktive Model Mappings
     */
    public function activeModelMappings()
    {
        return $this->hasMany(OrganizationEntityTypeModelMapping::class, 'entity_type_id')
            ->where('is_active', true);
    }
}
