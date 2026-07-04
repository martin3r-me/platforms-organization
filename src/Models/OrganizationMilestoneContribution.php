<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Symfony\Component\Uid\UuidV7;

/**
 * Polymorphic pivot: verbindet einen Milestone (Waypoint auf der
 * Transformations-Map) mit einem beliebigen beitragenden Model aus
 * einem anderen Modul (z.B. okr_objective, okr_key_result).
 *
 * Der beitragende Typ muss ueber die MilestoneContributorRegistry
 * registriert sein — sonst kann er nicht auf der Map gerendert werden.
 */
class OrganizationMilestoneContribution extends Model
{
    protected $table = 'organization_milestone_contributions';

    protected $fillable = [
        'uuid',
        'milestone_id',
        'linkable_type',
        'linkable_id',
        'weight',
        'team_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'weight' => 'integer',
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

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(OrganizationMilestone::class, 'milestone_id');
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
