<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\Core\Models\Team;

class OrganizationOpsRoomSnapshot extends Model
{
    protected $table = 'organization_ops_room_snapshots';

    protected $fillable = [
        'team_id',
        'perspective_entity_id',
        'snapshot_date',
        'open_count',
        'escalated_count',
        'algedonic_count',
        'vacant_cells_count',
        'per_level',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'open_count' => 'integer',
        'escalated_count' => 'integer',
        'algedonic_count' => 'integer',
        'vacant_cells_count' => 'integer',
        'per_level' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function perspectiveEntity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'perspective_entity_id');
    }

    public function scopeForPerspective(Builder $query, int $perspectiveEntityId): Builder
    {
        return $query->where('perspective_entity_id', $perspectiveEntityId);
    }

    public function scopeLastDays(Builder $query, int $days): Builder
    {
        return $query->where('snapshot_date', '>=', now()->subDays($days)->toDateString())
            ->orderBy('snapshot_date');
    }
}
