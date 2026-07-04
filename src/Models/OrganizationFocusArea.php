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
 * FocusArea: strategisches Handlungsfeld eines Forecasts.
 * Sammelt VisionImages, Obstacles und Milestones fuer die Carrier-Entity.
 */
class OrganizationFocusArea extends Model implements HasDisplayName
{
    use SoftDeletes, LogsActivity;

    protected $table = 'organization_focus_areas';

    protected $fillable = [
        'uuid',
        'entity_id',
        'forecast_id',
        'title',
        'description',
        'content',
        'central_question_vision_images',
        'central_question_obstacles',
        'central_question_milestones',
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

            $entity = OrganizationEntity::with('type')->find($model->entity_id);
            if (!$entity || $entity->type?->vsm_class !== OrganizationEntityType::VSM_CLASS_CARRIER) {
                throw new \InvalidArgumentException(
                    "entity_id muss auf eine Carrier-Entity zeigen (Entity #{$model->entity_id} "
                    . "ist '" . ($entity->type?->code ?? 'null') . "')."
                );
            }
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

    public function forecast(): BelongsTo
    {
        return $this->belongsTo(OrganizationForecast::class, 'forecast_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function visionImages(): HasMany
    {
        return $this->hasMany(OrganizationVisionImage::class, 'focus_area_id')->orderBy('order');
    }

    public function obstacles(): HasMany
    {
        return $this->hasMany(OrganizationObstacle::class, 'focus_area_id')->orderBy('order');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(OrganizationMilestone::class, 'focus_area_id')->orderBy('order');
    }
}
