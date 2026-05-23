<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationDimensionDefinition extends Model
{
    protected $table = 'organization_dimension_definitions';

    protected $fillable = [
        'key',
        'name',
        'description',
        'value_source',
        'mode',
        'team_scoped',
        'is_active',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'team_scoped' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    public function values()
    {
        return $this->hasMany(OrganizationDimensionValue::class, 'dimension_definition_id');
    }

    public function links()
    {
        return $this->hasMany(OrganizationDimensionLink::class, 'dimension_definition_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public static function findByKey(string $key): ?self
    {
        return static::where('key', $key)->first();
    }
}
