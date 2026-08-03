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
 * Strategy: das strategische Aggregat einer Carrier-Entity (1:1).
 *
 * Lifecycle-/Meta-Container fuer die Strategie eines Carriers — Status
 * (draft|active|archived), Version, Owner. Die eigentlichen Artefakte
 * (StrategicDocuments, Forecasts/Regnosen, FocusAreas mit VisionImages/
 * Obstacles/Milestones) haengen weiterhin ueber entity_id an der Entity;
 * Strategy verankert sie logisch und liefert den Lebenszyklus obendrauf.
 */
class OrganizationStrategy extends Model implements HasDisplayName
{
    use SoftDeletes, LogsActivity;

    public const STATUS_DRAFT    = 'draft';
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_ARCHIVED = 'archived';

    protected $table = 'organization_strategies';

    protected $fillable = [
        'uuid',
        'entity_id',
        'status',
        'version',
        'published_at',
        'owner_user_id',
        'team_id',
    ];

    protected $casts = [
        'version'      => 'integer',
        'published_at' => 'datetime',
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
        return 'Strategie ' . ($this->entity?->name ?? '#' . $this->entity_id);
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'entity_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    // Bequemlichkeits-Relations: Artefakte haengen ueber entity_id, nicht ueber strategy_id.
    public function focusAreas(): HasMany
    {
        return $this->hasMany(OrganizationFocusArea::class, 'entity_id', 'entity_id')->orderBy('order');
    }

    public function forecasts(): HasMany
    {
        return $this->hasMany(OrganizationForecast::class, 'entity_id', 'entity_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(OrganizationStrategicDocument::class, 'entity_id', 'entity_id');
    }
}
