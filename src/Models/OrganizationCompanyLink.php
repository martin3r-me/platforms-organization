<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Symfony\Component\Uid\UuidV7;

class OrganizationCompanyLink extends Model
{
    protected $table = 'organization_company_links';

    protected $fillable = [
        'uuid',
        'organization_entity_id',
        'linkable_type',
        'linkable_id',
        'team_id',
        'created_by_user_id',
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

            if (empty($model->team_id) && auth()->check() && auth()->user()->currentTeam) {
                $model->team_id = auth()->user()->currentTeam->id;
            }

            if (empty($model->created_by_user_id) && auth()->check()) {
                $model->created_by_user_id = auth()->id();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'organization_entity_id');
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }
}



