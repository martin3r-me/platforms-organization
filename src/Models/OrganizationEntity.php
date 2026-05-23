<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\ActivityLog\Traits\LogsActivity;
use Symfony\Component\Uid\UuidV7;

class OrganizationEntity extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'organization_entities';

    protected $fillable = [
        'uuid',
        'name',
        'code',
        'team_id',
        'user_id',
        'linked_user_id',
        'description',
        'entity_type_id',
        'parent_entity_id',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Scope für aktive Entities
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope für Entities nach Team
     */
    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * Scope für Entities nach Type
     */
    public function scopeOfType($query, $entityTypeId)
    {
        return $query->where('entity_type_id', $entityTypeId);
    }

    /**
     * Finde Entity nach UUID
     */
    public static function findByUuid(string $uuid): ?self
    {
        return static::where('uuid', $uuid)->first();
    }

    /**
     * Alle aktiven Entities für ein Team
     */
    public static function getActiveForTeam($teamId)
    {
        return static::forTeam($teamId)->active()->with(['type', 'parent'])->get();
    }

    /**
     * Beziehung zu Entity Type
     */
    public function type()
    {
        return $this->belongsTo(OrganizationEntityType::class, 'entity_type_id');
    }

    /**
     * Beziehung zu Parent Entity (Hierarchie)
     */
    public function parent()
    {
        return $this->belongsTo(OrganizationEntity::class, 'parent_entity_id');
    }

    /**
     * Beziehung zu Child Entities (Hierarchie)
     */
    public function children()
    {
        return $this->hasMany(OrganizationEntity::class, 'parent_entity_id');
    }

    /**
     * Alle Child Entities rekursiv
     */
    public function allChildren()
    {
        return $this->children()->with('allChildren');
    }

    /**
     * Alle Parent Entities rekursiv
     */
    public function allParents()
    {
        return $this->parent()->with('allParents');
    }

    /**
     * Beziehung zu Team
     */
    public function team()
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    /**
     * Beziehung zu User (Ersteller)
     */
    public function user()
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    /**
     * Beziehung zu verknüpftem User (Person-Entity)
     */
    public function linkedUser()
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'linked_user_id');
    }

    /**
     * Scope: Entities mit einem bestimmten linked_user_id
     */
    public function scopeLinkedToUser($query, int $userId)
    {
        return $query->where('linked_user_id', $userId);
    }

    /**
     * Scope: Person-Entities (EntityType mit code 'person')
     */
    public function scopePersons($query)
    {
        return $query->whereHas('type', fn($q) => $q->where('code', 'person'));
    }

    /**
     * Beziehung zu Organization Contexts (Module Entities, die an diese Entity gehängt sind)
     */
    public function contexts()
    {
        return $this->hasMany(OrganizationContext::class, 'organization_entity_id');
    }

    /**
     * Aktive Contexts
     */
    public function activeContexts()
    {
        return $this->contexts()->where('is_active', true);
    }

    /**
     * Beziehung zu Entity Links (polymorphe Verknüpfungen zu anderen Modulen)
     */
    public function entityLinks()
    {
        return $this->hasMany(OrganizationEntityLink::class, 'entity_id');
    }

    /**
     * Relations, die von dieser Entity ausgehen
     */
    public function relationsFrom()
    {
        return $this->hasMany(OrganizationEntityRelationship::class, 'from_entity_id');
    }

    /**
     * Relations, die zu dieser Entity führen
     */
    public function relationsTo()
    {
        return $this->hasMany(OrganizationEntityRelationship::class, 'to_entity_id');
    }

    /**
     * Alle Relations (sowohl from als auch to)
     */
    public function allRelations()
    {
        return OrganizationEntityRelationship::where(function ($query) {
            $query->where('from_entity_id', $this->id)
                  ->orWhere('to_entity_id', $this->id);
        });
    }

    /**
     * Aktive Relations, die von dieser Entity ausgehen
     */
    public function activeRelationsFrom()
    {
        return $this->relationsFrom()->active();
    }

    /**
     * Aktive Relations, die zu dieser Entity führen
     */
    public function activeRelationsTo()
    {
        return $this->relationsTo()->active();
    }

    /**
     * Prüfe ob Entity ein Child hat
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Prüfe ob Entity ein Parent hat
     */
    public function hasParent(): bool
    {
        return !is_null($this->parent_entity_id);
    }

    /**
     * Prüfe ob Entity ein Root Entity ist (kein Parent)
     */
    public function isRoot(): bool
    {
        return is_null($this->parent_entity_id);
    }

    /**
     * Prüfe ob Entity ein Leaf Entity ist (keine Children)
     */
    public function isLeaf(): bool
    {
        return !$this->hasChildren();
    }

    /**
     * Name/Code change history
     */
    public function nameHistory()
    {
        return $this->hasMany(OrganizationEntityNameHistory::class, 'entity_id');
    }

    /**
     * Dimension links (generic dimensions framework)
     */
    public function dimensionLinks()
    {
        return $this->morphMany(OrganizationDimensionLink::class, 'linkable');
    }

    /**
     * Booted Event - UUID automatisch generieren + Name-History tracking
     */
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

        static::updating(function (self $model) {
            if ($model->isDirty('name') || $model->isDirty('code')) {
                OrganizationEntityNameHistory::create([
                    'team_id' => $model->team_id,
                    'entity_id' => $model->id,
                    'old_name' => $model->getOriginal('name'),
                    'new_name' => $model->isDirty('name') ? $model->name : null,
                    'old_code' => $model->getOriginal('code'),
                    'new_code' => $model->isDirty('code') ? $model->code : null,
                    'changed_by_user_id' => auth()->id(),
                    'changed_at' => now(),
                ]);
            }
        });
    }
}
