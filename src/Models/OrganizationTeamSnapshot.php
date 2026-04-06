<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationTeamSnapshot extends Model
{
    public $timestamps = false;

    protected $table = 'organization_team_snapshots';

    protected $fillable = [
        'team_id',
        'snapshot_date',
        'snapshot_period',
        'structure',
        'created_at',
    ];

    protected $casts = [
        'structure' => 'array',
        'snapshot_date' => 'date',
        'created_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeForDateRange($query, $from, $to)
    {
        return $query->whereBetween('snapshot_date', [$from, $to]);
    }
}
