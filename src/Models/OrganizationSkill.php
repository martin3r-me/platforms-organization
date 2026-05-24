<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

class OrganizationSkill extends Model
{
    use SoftDeletes;

    protected $table = 'organization_skills';

    protected $fillable = [
        'uuid',
        'team_id',
        'name',
        'category',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

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
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function jobProfiles(): BelongsToMany
    {
        return $this->belongsToMany(OrganizationJobProfile::class, 'organization_job_profile_skills', 'skill_id', 'job_profile_id')
            ->withPivot('level', 'is_required', 'sort_order');
    }

    public function persons(): BelongsToMany
    {
        return $this->belongsToMany(OrganizationEntity::class, 'organization_person_skills', 'skill_id', 'person_entity_id')
            ->withPivot('level', 'certified_at', 'notes')
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }
}
