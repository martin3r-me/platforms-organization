<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

/**
 * Zuweisung einer Rolle an eine Person im Kontext einer beliebigen Entity.
 *
 * Beispiel: Anna (Person) ist "Projektleiter" (Role)
 *           im Projekt Website-Relaunch (Context-Entity).
 *
 * Constraints:
 *  - person_entity_id muss EntityType "person" sein
 *  - person_entity_id !== context_entity_id
 */
class OrganizationRoleAssignment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'organization_role_assignments';

    protected $fillable = [
        'team_id',
        'user_id',
        'role_id',
        'person_entity_id',
        'context_entity_id',
        'percentage',
        'valid_from',
        'valid_to',
        'note',
    ];

    protected $casts = [
        'percentage' => 'integer',
        'valid_from' => 'date',
        'valid_to'   => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            $model->assertPersonEntityType();
            $model->assertPersonNotEqualsContext();
        });
    }

    protected function assertPersonEntityType(): void
    {
        $entity = OrganizationEntity::with('type')->find($this->person_entity_id);

        if (! $entity) {
            throw ValidationException::withMessages([
                'person_entity_id' => 'Die angegebene Person-Entity wurde nicht gefunden.',
            ]);
        }

        $typeCode = $entity->type?->code;

        if ($typeCode !== 'person') {
            throw ValidationException::withMessages([
                'person_entity_id' => 'Rollen können nur an Entities vom Typ "person" zugewiesen werden (gefunden: '.($typeCode ?? 'unbekannt').').',
            ]);
        }
    }

    protected function assertPersonNotEqualsContext(): void
    {
        if ((int) $this->person_entity_id === (int) $this->context_entity_id) {
            throw ValidationException::withMessages([
                'context_entity_id' => 'Die Person und der Kontext dürfen nicht dieselbe Entity sein.',
            ]);
        }
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(OrganizationRole::class, 'role_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'person_entity_id');
    }

    public function context(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'context_entity_id');
    }

    public function scopeForPerson($query, int $personEntityId)
    {
        return $query->where('person_entity_id', $personEntityId);
    }

    public function scopeForContext($query, int $contextEntityId)
    {
        return $query->where('context_entity_id', $contextEntityId);
    }

    public function scopeForRole($query, int $roleId)
    {
        return $query->where('role_id', $roleId);
    }
}
