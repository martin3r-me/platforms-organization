<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Uid\UuidV7;

class OrganizationDimensionLink extends Model
{
    protected $table = 'organization_dimension_links';

    protected $fillable = [
        'uuid',
        'dimension_definition_id',
        'dimension_value_id',
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
        'is_primary' => 'boolean',
        'percentage' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
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

    public function value()
    {
        return $this->belongsTo(OrganizationDimensionValue::class, 'dimension_value_id');
    }

    public function linkable()
    {
        return $this->morphTo();
    }

    public function team()
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function scopeForDimension($query, string $dimensionKey)
    {
        return $query->whereHas('definition', fn ($q) => $q->where('key', $dimensionKey));
    }

    public function scopeForLinkable($query, string $type, int $id)
    {
        return $query->where('linkable_type', $type)->where('linkable_id', $id);
    }
}
