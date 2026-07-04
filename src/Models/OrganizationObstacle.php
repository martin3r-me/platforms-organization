<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\ActivityLog\Traits\LogsActivity;
use Platform\Core\Contracts\HasDisplayName;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Symfony\Component\Uid\UuidV7;

/**
 * Obstacle: identifiziertes Hindernis in einer FocusArea.
 *
 * entity_id wird beim Save aus focus_area.entity_id abgeleitet.
 */
class OrganizationObstacle extends Model implements HasDisplayName
{
    use SoftDeletes, LogsActivity;

    protected $table = 'organization_obstacles';

    protected $fillable = [
        'uuid',
        'entity_id',
        'focus_area_id',
        'title',
        'description',
        'central_question',
        'order',
        'team_id',
        'user_id',
    ];

    protected $casts = [
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
        });
    }

    public function getDisplayName(): ?string
    {
        return $this->title;
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
}
