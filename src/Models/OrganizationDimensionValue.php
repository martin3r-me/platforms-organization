<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

class OrganizationDimensionValue extends Model
{
    use SoftDeletes;

    protected $table = 'organization_dimension_values';

    protected $fillable = [
        'uuid',
        'dimension_definition_id',
        'code',
        'name',
        'description',
        'team_id',
        'is_active',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
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

    public function definition()
    {
        return $this->belongsTo(OrganizationDimensionDefinition::class, 'dimension_definition_id');
    }

    public function links()
    {
        return $this->hasMany(OrganizationDimensionLink::class, 'dimension_value_id');
    }

    public function team()
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where(function ($q) use ($teamId) {
            $q->where('team_id', $teamId)->orWhereNull('team_id');
        });
    }

    public function isGlobal(): bool
    {
        return is_null($this->team_id);
    }
}
