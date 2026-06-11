<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Zuweisung Person ↔ JobProfile mit Auslastung in Prozent.
 *
 * Constraint: person_entity_id muss auf eine Entity vom Typ "person" zeigen.
 */
class OrganizationPersonJobProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'organization_person_job_profiles';

    protected $fillable = [
        'team_id',
        'person_entity_id',
        'job_profile_id',
        'context_entity_id',
        'percentage',
        'is_primary',
        'valid_from',
        'valid_to',
        'note',
    ];

    protected $casts = [
        'percentage' => 'integer',
        'is_primary' => 'boolean',
        'valid_from' => 'date',
        'valid_to'   => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            $model->assertPersonEntityType();
        });
    }

    /**
     * Stellt sicher, dass person_entity_id auf eine Entity vom Typ "person" zeigt.
     */
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
                'person_entity_id' => 'JobProfiles können nur an Entities vom Typ "person" zugewiesen werden (gefunden: '.($typeCode ?? 'unbekannt').').',
            ]);
        }
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'person_entity_id');
    }

    public function jobProfile(): BelongsTo
    {
        return $this->belongsTo(OrganizationJobProfile::class, 'job_profile_id');
    }

    public function contextEntity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'context_entity_id');
    }

    /**
     * Override-Rollen: individuelle Anteile pro Person-Profile-Zuweisung.
     * Wenn leer, gelten die Default-Anteile aus dem JobProfile.
     */
    public function roleOverrides(): BelongsToMany
    {
        return $this->belongsToMany(OrganizationRole::class, 'organization_person_job_profile_roles', 'person_job_profile_id', 'role_id')
            ->withPivot('percentage_share', 'sort_order')
            ->withTimestamps()
            ->orderBy('organization_person_job_profile_roles.sort_order');
    }

    /**
     * Effektive Rollen-Verteilung dieser Zuweisung.
     *
     * Logik:
     *  - Wenn Override-Eintraege existieren: nutze sie
     *  - Sonst: nutze die JobProfile-Defaults
     *  - Anteile werden bereits hier zurueckgegeben (NICHT mit der overall percentage
     *    multipliziert) — der Caller muss bei Bedarf overall percentage anwenden.
     *
     * Rueckgabe: Collection von ['role_id', 'role', 'percentage_share', 'source' => 'override'|'default'].
     */
    public function effectiveRoleShares(): Collection
    {
        $this->loadMissing(['roleOverrides', 'jobProfile.roles']);

        if ($this->roleOverrides->isNotEmpty()) {
            return $this->roleOverrides->map(fn ($r) => [
                'role_id' => $r->id,
                'role' => $r,
                'percentage_share' => (int) $r->pivot->percentage_share,
                'source' => 'override',
            ]);
        }

        $jp = $this->jobProfile;
        if (! $jp) {
            return collect();
        }

        return $jp->roles->map(fn ($r) => [
            'role_id' => $r->id,
            'role' => $r,
            'percentage_share' => (int) $r->pivot->percentage_share,
            'source' => 'default',
        ]);
    }
}
