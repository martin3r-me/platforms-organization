<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrganizationEntityRelationType extends Model
{
    use HasFactory;

    protected $table = 'organization_entity_relation_types';

    protected $fillable = [
        'code',
        'name',
        'description',
        'icon',
        'sort_order',
        'is_active',
        'is_directional',
        'is_hierarchical',
        'is_reciprocal',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_directional' => 'boolean',
        'is_hierarchical' => 'boolean',
        'is_reciprocal' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Scope für aktive Relation Types
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope für sortierte Relation Types
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Scope für hierarchische Relation Types
     */
    public function scopeHierarchical($query)
    {
        return $query->where('is_hierarchical', true);
    }

    /**
     * Scope für direktionale Relation Types
     */
    public function scopeDirectional($query)
    {
        return $query->where('is_directional', true);
    }

    /**
     * Scope für reziproke Relation Types
     */
    public function scopeReciprocal($query)
    {
        return $query->where('is_reciprocal', true);
    }

    /**
     * Finde Relation Type nach Code
     */
    public static function findByCode(string $code): ?self
    {
        return static::where('code', $code)->first();
    }

    /**
     * Alle aktiven Relation Types geordnet
     */
    public static function getActiveOrdered()
    {
        return static::active()->ordered()->get();
    }

    /**
     * Hierarchische Relation Types
     */
    public static function getHierarchical()
    {
        return static::hierarchical()->active()->ordered()->get();
    }

    /**
     * Direktionale Relation Types
     */
    public static function getDirectional()
    {
        return static::directional()->active()->ordered()->get();
    }

    /**
     * Reziproke Relation Types
     */
    public static function getReciprocal()
    {
        return static::reciprocal()->active()->ordered()->get();
    }
}
