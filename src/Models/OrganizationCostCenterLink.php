<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Symfony\Component\Uid\UuidV7;

class OrganizationCostCenterLink extends Model
{
    protected $table = 'organization_cost_center_links';

    protected $fillable = [
        'uuid',
        'entity_id',
        'linkable_type',
        'linkable_id',
        'start_date',
        'end_date',
        'percentage',
        'is_primary',
        'team_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'percentage' => 'decimal:2',
        'is_primary' => 'boolean',
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

            if (empty($model->team_id) && auth()->check()) {
                $model->team_id = auth()->user()->currentTeam->id;
            }
            if (empty($model->created_by_user_id) && auth()->check()) {
                $model->created_by_user_id = auth()->id();
            }
        });
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'entity_id');
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }
}


