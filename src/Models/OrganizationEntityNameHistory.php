<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Uid\UuidV7;

class OrganizationEntityNameHistory extends Model
{
    protected $table = 'organization_entity_name_history';

    protected $fillable = [
        'uuid',
        'team_id',
        'entity_id',
        'old_name',
        'new_name',
        'old_code',
        'new_code',
        'changed_by_user_id',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
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

    public function entity()
    {
        return $this->belongsTo(OrganizationEntity::class, 'entity_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'changed_by_user_id');
    }

    public function team()
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function scopeForEntity($query, int $entityId)
    {
        return $query->where('entity_id', $entityId);
    }

    public function scopeLatest($query)
    {
        return $query->orderByDesc('changed_at');
    }
}
