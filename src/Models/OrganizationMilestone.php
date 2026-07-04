<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\ActivityLog\Traits\LogsActivity;
use Platform\Core\Contracts\HasDisplayName;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Symfony\Component\Uid\UuidV7;

/**
 * Milestone: Waypoint auf der Transformations-Map einer Carrier-Entity.
 *
 * Gehoert immer zu einer FocusArea; entity_id wird beim Save aus
 * focus_area.entity_id abgeleitet fuer direkte Entity-Queries auf der Map.
 *
 * target_year + target_quarter sind die primaeren Achsen der Map,
 * target_date bleibt optional fuer feinere Datierung.
 */
class OrganizationMilestone extends Model implements HasDisplayName
{
    use SoftDeletes, LogsActivity;

    protected $table = 'organization_milestones';

    protected $fillable = [
        'uuid',
        'entity_id',
        'focus_area_id',
        'title',
        'description',
        'central_question',
        'target_date',
        'target_year',
        'target_quarter',
        'order',
        'team_id',
        'user_id',
    ];

    protected $casts = [
        'target_date' => 'date',
        'target_year' => 'integer',
        'target_quarter' => 'integer',
        'order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            if (empty($model->uuid)) {
                do {
                    $uuid = UuidV7::generate();
                } while (self::where('uuid', $uuid)->exists());
                $model->uuid = $uuid;
            }

            $focusArea = OrganizationFocusArea::find($model->focus_area_id);
            if (!$focusArea) {
                throw new \InvalidArgumentException("focus_area_id {$model->focus_area_id} nicht gefunden.");
            }
            $model->entity_id = $focusArea->entity_id;

            if ($model->target_quarter !== null && ($model->target_quarter < 1 || $model->target_quarter > 4)) {
                throw new \InvalidArgumentException(
                    "target_quarter muss zwischen 1 und 4 liegen (ist {$model->target_quarter})."
                );
            }
        });
    }

    public function getDisplayName(): ?string
    {
        return $this->title;
    }

    public function scopeForEntity($query, int $entityId)
    {
        return $query->where('entity_id', $entityId);
    }

    public function scopeInQuarter($query, int $year, int $quarter)
    {
        return $query->where('target_year', $year)->where('target_quarter', $quarter);
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'entity_id');
    }

    public function focusArea(): BelongsTo
    {
        return $this->belongsTo(OrganizationFocusArea::class, 'focus_area_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(OrganizationMilestoneContribution::class, 'milestone_id');
    }
}
