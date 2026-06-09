<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrganizationEntityType extends Model
{
    use HasFactory;

    public const VSM_CLASS_CARRIER = 'carrier';
    public const VSM_CLASS_ACTOR = 'actor';
    public const VSM_CLASS_OBSERVED = 'observed';

    public const VSM_CLASSES = [
        self::VSM_CLASS_CARRIER,
        self::VSM_CLASS_ACTOR,
        self::VSM_CLASS_OBSERVED,
    ];

    protected $table = 'organization_entity_types';

    protected $fillable = [
        'code',
        'name',
        'description',
        'icon',
        'sort_order',
        'is_active',
        'entity_type_group_id',
        'vsm_class',
        'can_be_perspective',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'can_be_perspective' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Invariant: can_be_perspective spiegelt vsm_class === 'carrier'.
     * Verhindert Drift zwischen UI-Eingabe und systemischer Bedeutung.
     */
    protected static function booted(): void
    {
        static::saving(function (self $model) {
            if ($model->isDirty('vsm_class')) {
                $model->can_be_perspective = $model->vsm_class === self::VSM_CLASS_CARRIER;
            }
        });
    }

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
     * Scope: Carrier-Types (lebensfaehige Systeme, duerfen Perspektive sein).
     */
    public function scopeCarriers($query)
    {
        return $query->where('vsm_class', self::VSM_CLASS_CARRIER);
    }

    /**
     * Scope: Actor-Types (fuellen VSM-Funktionen aus, empfangen Signale).
     */
    public function scopeActors($query)
    {
        return $query->where('vsm_class', self::VSM_CLASS_ACTOR);
    }

    /**
     * Scope: Observed-Types (Umwelt; werden von S4 beobachtet).
     */
    public function scopeObserved($query)
    {
        return $query->where('vsm_class', self::VSM_CLASS_OBSERVED);
    }

    /**
     * Scope: Types, deren Entities Perspektive sein duerfen.
     */
    public function scopePerspectiveCapable($query)
    {
        return $query->where('can_be_perspective', true);
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
