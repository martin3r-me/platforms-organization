<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationPersonSkill extends Model
{
    protected $table = 'organization_person_skills';

    protected $fillable = [
        'person_entity_id',
        'skill_id',
        'level',
        'certified_at',
        'notes',
    ];

    protected $casts = [
        'certified_at' => 'date',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'person_entity_id');
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(OrganizationSkill::class, 'skill_id');
    }
}
