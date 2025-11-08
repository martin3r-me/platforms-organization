<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrganizationEntityTypeModelMapping extends Model
{
    use HasFactory;

    protected $table = 'organization_entity_type_model_mappings';

    protected $fillable = [
        'entity_type_id',
        'module_key',
        'model_class',
        'is_bidirectional',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_bidirectional' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Beziehung zu Entity Type
     */
    public function entityType()
    {
        return $this->belongsTo(OrganizationEntityType::class, 'entity_type_id');
    }

    /**
     * Scope für aktive Mappings
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope für bidirektionale Mappings
     */
    public function scopeBidirectional($query)
    {
        return $query->where('is_bidirectional', true);
    }

    /**
     * Scope für ein bestimmtes Modul
     */
    public function scopeForModule($query, string $moduleKey)
    {
        return $query->where('module_key', $moduleKey);
    }

    /**
     * Scope für ein bestimmtes Model
     */
    public function scopeForModel($query, string $modelClass)
    {
        return $query->where('model_class', $modelClass);
    }

    /**
     * Scope für sortierte Mappings
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('module_key')->orderBy('model_class');
    }

    /**
     * Prüft ob ein Model für einen Entity Type erlaubt ist
     */
    public static function isAllowed(string $entityTypeCode, string $modelClass): bool
    {
        $entityType = OrganizationEntityType::findByCode($entityTypeCode);
        if (!$entityType) {
            return false;
        }

        return static::where('entity_type_id', $entityType->id)
            ->where('model_class', $modelClass)
            ->active()
            ->exists();
    }

    /**
     * Prüft ob ein Model bidirektional für einen Entity Type erlaubt ist
     */
    public static function isBidirectionalAllowed(string $entityTypeCode, string $modelClass): bool
    {
        $entityType = OrganizationEntityType::findByCode($entityTypeCode);
        if (!$entityType) {
            return false;
        }

        return static::where('entity_type_id', $entityType->id)
            ->where('model_class', $modelClass)
            ->where('is_bidirectional', true)
            ->active()
            ->exists();
    }

    /**
     * Holt alle erlaubten Modelle für einen Entity Type
     */
    public static function getAllowedModelsForEntityType(int $entityTypeId): \Illuminate\Support\Collection
    {
        return static::where('entity_type_id', $entityTypeId)
            ->active()
            ->ordered()
            ->get()
            ->groupBy('module_key');
    }
}

