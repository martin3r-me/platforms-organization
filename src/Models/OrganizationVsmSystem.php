<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrganizationVsmSystem extends Model
{
    use HasFactory;

    protected $table = 'organization_vsm_systems';

    protected $fillable = [
        'code',
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
     * Scope für aktive VSM-Systeme
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope für sortierte VSM-Systeme
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Finde VSM-System nach Code
     */
    public static function findByCode(string $code): ?self
    {
        return static::where('code', $code)->first();
    }

    /**
     * Alle aktiven VSM-Systeme geordnet
     */
    public static function getActiveOrdered()
    {
        return static::active()->ordered()->get();
    }

    /**
     * Organisationseinheiten, die diesem VSM-System zugeordnet sind
     */
    public function entities()
    {
        return $this->hasMany(OrganizationEntity::class, 'vsm_system_id');
    }
}
