<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'public_token',
        'public_token_expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
        'public_token_expires_at' => 'datetime',
    ];

    // ── Public Sharing (Strategie-Onepager) ────────────────────

    /** Erzeugt einen öffentlichen Token für die Strategie-Ansicht (1 Jahr gültig). */
    public function generatePublicToken(): void
    {
        $this->public_token = bin2hex(random_bytes(24));
        $this->public_token_expires_at = now()->addYear();
        $this->save();
    }

    /** Widerruft den öffentlichen Link. */
    public function revokePublicToken(): void
    {
        $this->public_token = null;
        $this->public_token_expires_at = null;
        $this->save();
    }

    /** True, wenn ein gültiger (nicht abgelaufener) öffentlicher Token existiert. */
    public function isPublicAccessible(): bool
    {
        if (! $this->public_token) {
            return false;
        }

        if ($this->public_token_expires_at && $this->public_token_expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /** Teilbare URL der öffentlichen Strategie-Ansicht, oder null. */
    public function getPublicUrl(): ?string
    {
        if (! $this->public_token) {
            return null;
        }

        return route('organization.public.strategy', $this->public_token);
    }

    /**
     * Fremd-IDs dieser Entity (Kostenstelle, DATEV, Buchungskonto, Kreditor, …).
     * Jede Entity IST faktisch ihre eigene Kostenstelle — die KST ist nur der
     * erste `system`-Wert dieser Familie.
     */
    public function externalIds(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrganizationEntityExternalId::class, 'entity_id');
    }

    /** Fremd-ID-Wert für ein System (z.B. 'datev'), oder null. */
    public function externalId(string $system): ?string
    {
        return $this->externalIds->firstWhere('system', $system)?->value;
    }

    /**
     * Setzt/aktualisiert (oder löscht bei null) die Fremd-ID für ein System.
     */
    public function setExternalId(string $system, ?string $value, ?string $label = null): void
    {
        $value = $value !== null ? trim($value) : null;

        if ($value === null || $value === '') {
            $this->externalIds()->where('system', $system)->delete();
            $this->unsetRelation('externalIds');
            return;
        }

        $this->externalIds()->updateOrCreate(
            ['system' => $system, 'team_id' => $this->team_id],
            ['value' => $value, 'label' => $label],
        );
        $this->unsetRelation('externalIds');
    }

    /** Kostenstellen-Kürzel dieser Entity (Alias auf die 'kostenstelle'-Fremd-ID). */
    public function getCostCenterAttribute(): ?string
    {
        return $this->externalId(OrganizationEntityExternalId::SYSTEM_COST_CENTER);
    }

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
     * Scope: Agent-Entities (EntityType mit code 'agent') — die KI-Worker.
     */
    public function scopeAgents($query)
    {
        return $query->whereHas('type', fn ($q) => $q->where('code', 'agent'));
    }

    /**
     * Runtime-Profil einer agent-Entity (Domäne, Stufen, Governor, gemeldeter Status). 1:1.
     *
     * @return HasOne<OrganizationAgentProfile, $this>
     */
    public function agentProfile(): HasOne
    {
        return $this->hasOne(OrganizationAgentProfile::class, 'organization_entity_id');
    }

    /**
     * Die VSM-Domäne dieser Entity aus ihren Rollen-Assignments (z. B. "development",
     * "backoffice"), oder null ohne (agent-ausführbare) Rolle.
     */
    public function roleDomain(): ?string
    {
        return OrganizationRoleAssignment::query()
            ->where('person_entity_id', $this->id)
            ->with('role')
            ->get()
            ->pluck('role.domain')
            ->filter()
            ->first();
    }

    /**
     * memory_type-Präfix des Wissens-Pools dieser Domäne (Learn-Loop-Partitionierung, siehe
     * AgentKnowledgeSearchService). Naming-Wart: Domäne "development" legt unter memory_type
     * "dev.*" ab; andere Domänen sind 1:1 (z. B. "backoffice" → "backoffice.*"). Ohne Domäne
     * null, damit ein Abruf nie in einen fremden Pool leakt.
     */
    public function memoryTypePrefix(): ?string
    {
        return match ($domain = $this->roleDomain()) {
            'development' => 'dev',
            null => null,
            default => $domain,
        };
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
     * @deprecated Use EntityDimensionBridge::linksForEntity($this->id) instead.
     * Kept for backward compatibility during transition.
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
     * Validate that setting a parent_entity_id does not create a circular hierarchy.
     * Walks up the ancestor chain from the proposed parent; if it encounters $this->id, throws.
     */
    public function validateNoCircularHierarchy(int $newParentId): void
    {
        $visited = [$this->id];
        $currentId = $newParentId;

        while ($currentId !== null) {
            if (in_array($currentId, $visited)) {
                throw new \InvalidArgumentException(
                    "Circular hierarchy detected: entity {$this->id} cannot be a child of entity {$newParentId}."
                );
            }
            $visited[] = $currentId;
            $currentId = static::where('id', $currentId)->value('parent_entity_id');
        }
    }

    /**
     * Name/Code change history
     */
    public function nameHistory()
    {
        return $this->hasMany(OrganizationEntityNameHistory::class, 'entity_id');
    }

    /**
     * Signals (algedonic/inference signals for this entity)
     */
    public function signals(): HasMany
    {
        return $this->hasMany(OrganizationSignal::class, 'entity_id');
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
        // Invariante: linked_user_id darf NUR auf Person-Entities sitzen.
        // Ein User gehört an genau EINE Person-Entity — niemals an einen
        // Abteilungs-/Struktur-Knoten. Sonst kapert der Fehl-Link die Authz-
        // Auflösung (User → Person-Entity) und macht Grants unsichtbar.
        // Clearing auf null bleibt erlaubt (zum Bereinigen von Alt-Fehlern).
        static::saving(function (self $model) {
            if ($model->linked_user_id === null || ! $model->isDirty('linked_user_id')) {
                return;
            }

            $typeCode = OrganizationEntityType::query()
                ->whereKey($model->entity_type_id)
                ->value('code');

            // Mitglied-artige Typen mit eigenem User: person UND agent (der KI-Worker ist ein
            // echtes Org-Mitglied mit eigenem Bot-User). Struktur-/Abteilungs-Knoten weiterhin nicht.
            if (! in_array($typeCode, ['person', 'agent'], true)) {
                throw new \InvalidArgumentException(
                    'linked_user_id darf nur auf Person- oder Agent-Entities gesetzt werden ('
                    .'Entity-Type "'.($typeCode ?? '?').'" ist keins von beidem).'
                );
            }
        });

        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                do {
                    $uuid = UuidV7::generate();
                } while (self::where('uuid', $uuid)->exists());

                $model->uuid = $uuid;
            }
        });

        static::updating(function (self $model) {
            $nameOrCodeChanged = $model->isDirty('name') || $model->isDirty('code');
            $parentChanged = $model->isDirty('parent_entity_id');

            if ($nameOrCodeChanged || $parentChanged) {
                // Determine change_type
                $changeType = 'rename';
                if ($nameOrCodeChanged && $parentChanged) {
                    $changeType = 'rename_and_move';
                } elseif ($parentChanged) {
                    $changeType = 'move';
                }

                // Validate hierarchy if parent changed
                if ($parentChanged && $model->parent_entity_id !== null) {
                    $model->validateNoCircularHierarchy($model->parent_entity_id);
                }

                OrganizationEntityNameHistory::create([
                    'team_id' => $model->team_id,
                    'entity_id' => $model->id,
                    'old_name' => $model->getOriginal('name'),
                    'new_name' => $model->isDirty('name') ? $model->name : null,
                    'old_code' => $model->getOriginal('code'),
                    'new_code' => $model->isDirty('code') ? $model->code : null,
                    'old_parent_entity_id' => $parentChanged ? $model->getOriginal('parent_entity_id') : null,
                    'new_parent_entity_id' => $parentChanged ? $model->parent_entity_id : null,
                    'change_type' => $changeType,
                    'changed_by_user_id' => auth()->id(),
                    'changed_at' => now(),
                ]);
            }
        });
    }
}
