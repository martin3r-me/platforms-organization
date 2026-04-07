<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
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
}
