<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationEntitySnapshot extends Model
{
    public $timestamps = false;

    protected $table = 'organization_entity_snapshots';

    protected $fillable = [
        'entity_id',
        'snapshot_date',
        'snapshot_period',
        'metrics',
        'created_at',
    ];

    protected $casts = [
        'metrics' => 'array',
        'snapshot_date' => 'date',
        'created_at' => 'datetime',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'entity_id');
    }

    public function scopeForPeriod($query, string $period)
    {
        return $query->where('snapshot_period', $period);
    }

    public function scopeForDateRange($query, $from, $to)
    {
        return $query->whereBetween('snapshot_date', [$from, $to]);
    }
}
