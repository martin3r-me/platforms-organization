<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationPersonSoftSkill extends Model
{
    protected $table = 'organization_person_soft_skills';

    protected $fillable = [
        'person_entity_id',
        'soft_skill_id',
        'level',
        'notes',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'person_entity_id');
    }

    public function softSkill(): BelongsTo
    {
        return $this->belongsTo(OrganizationSoftSkill::class, 'soft_skill_id');
    }
}
