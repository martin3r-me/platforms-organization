<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrganizationEntityTypeGroup extends Model
{
    use HasFactory;

    protected $table = 'organization_entity_type_groups';

    protected $fillable = [
        'name',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Scope für aktive Gruppen
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope für sortierte Gruppen
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Finde Gruppe nach Name
     */
    public static function findByName(string $name): ?self
    {
        return static::where('name', $name)->first();
    }

    /**
     * Alle aktiven Gruppen geordnet
     */
    public static function getActiveOrdered()
    {
        return static::active()->ordered()->get();
    }

    /**
     * Beziehung zu Entity Types
     */
    public function entityTypes()
    {
        return $this->hasMany(OrganizationEntityType::class, 'entity_type_group_id');
    }
}
