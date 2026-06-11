<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Relation-Type-Katalog (global, kein Team-Scope).
 *
 * Trägt Beer-Channel-Properties, die der Aggregations-Service auswertet,
 * um Snapshot/Movement-Traversal zu steuern — KEIN Hardcoding auf code.
 *
 * Properties siehe Migration extend_relation_types_with_channel_properties.
 */
class OrganizationEntityRelationType extends Model
{
    use HasFactory;

    protected $table = 'organization_entity_relation_types';

    protected $fillable = [
        // --- Identitaet / Anzeige ---
        'code',
        'name',
        'description',
        'icon',
        'sort_order',
        'is_active',

        // --- Bestehende Richtungs-/Hierarchie-Flags ---
        'is_directional',
        'is_hierarchical',
        'is_reciprocal',

        // --- Beer-Channel-Properties (Snapshot/Movement-Steuerung) ---
        'affects_aggregation',
        'is_recursive',
        'cascade_to_children',
        'aggregation_weight',

        // --- Richtungs-Semantik ---
        'traversal_direction',
        'inverse_code',

        // --- Validierung & Typen-Konstraints ---
        'allowed_from_types',
        'allowed_to_types',
        'cardinality',

        // --- Beer-Theorie-Anker ---
        'channel_class',
        'variety_flow',

        // --- Extensibility ---
        'capabilities',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_directional' => 'boolean',
        'is_hierarchical' => 'boolean',
        'is_reciprocal' => 'boolean',
        'sort_order' => 'integer',

        'affects_aggregation' => 'boolean',
        'is_recursive' => 'boolean',
        'cascade_to_children' => 'boolean',
        'aggregation_weight' => 'decimal:4',

        'allowed_from_types' => 'array',
        'allowed_to_types' => 'array',
        'capabilities' => 'array',
        'metadata' => 'array',
    ];

    // --- Scopes -----------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopeHierarchical($query)
    {
        return $query->where('is_hierarchical', true);
    }

    public function scopeDirectional($query)
    {
        return $query->where('is_directional', true);
    }

    public function scopeReciprocal($query)
    {
        return $query->where('is_reciprocal', true);
    }

    /**
     * Scope: nur Channel-Types, die Snapshot/Movement-Traversal beeinflussen.
     */
    public function scopeAffectsAggregation($query)
    {
        return $query->where('affects_aggregation', true);
    }

    /**
     * Scope: nach Beer-Channel-Klasse filtern.
     */
    public function scopeOfChannelClass($query, string $class)
    {
        return $query->where('channel_class', $class);
    }

    // --- Static Lookups ---------------------------------------------------

    public static function findByCode(string $code): ?self
    {
        return static::where('code', $code)->first();
    }

    public static function getActiveOrdered()
    {
        return static::active()->ordered()->get();
    }

    public static function getHierarchical()
    {
        return static::hierarchical()->active()->ordered()->get();
    }

    public static function getDirectional()
    {
        return static::directional()->active()->ordered()->get();
    }

    public static function getReciprocal()
    {
        return static::reciprocal()->active()->ordered()->get();
    }

    /**
     * Alle Channel-Types, die Aggregation triggern (fuer Snapshot/Movement).
     */
    public static function getAggregationRelevant()
    {
        return static::active()->affectsAggregation()->ordered()->get();
    }

    // --- Capability-Inspektion -------------------------------------------

    /**
     * Prueft ob dieser Type ein bestimmtes Capability-Tag hat.
     * Capabilities sind frei taggbare Strings im JSON-Feld 'capabilities'.
     */
    public function hasCapability(string $capability): bool
    {
        $caps = $this->capabilities;
        return is_array($caps) && in_array($capability, $caps, true);
    }
}
