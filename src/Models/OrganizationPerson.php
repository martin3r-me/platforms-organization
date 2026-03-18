<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

class OrganizationPerson extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'organization_persons';

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'team_id',
        'user_id',
        'root_entity_id',
        'description',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
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

    public function team()
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function user()
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function getForTeam(int $teamId, ?int $rootEntityId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = self::where('team_id', $teamId)
            ->where('is_active', true);

        if ($rootEntityId) {
            $query->where(function ($q) use ($rootEntityId) {
                $q->whereNull('root_entity_id')
                  ->orWhere('root_entity_id', $rootEntityId);
            });
        } else {
            $query->whereNull('root_entity_id');
        }

        return $query->orderBy('name')->get();
    }

    public function isGlobal(): bool
    {
        return is_null($this->root_entity_id);
    }

    public function isEntitySpecific(): bool
    {
        return !is_null($this->root_entity_id);
    }

    public function links()
    {
        return $this->hasMany(OrganizationPersonLink::class, 'person_id');
    }
}
