<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Der jeweils neueste Koordinations-Kennzahlen-Stand eines Agenten (eine Zeile je Handle, upsert).
 * Von Parlan gepusht, aus der gefalteten Fabric gerechnet. Reine Datenablage — der Snapshot-Command
 * merged die metrics in die Entität, der DimensionRadar ordnet sie über ihre Definitionen ein.
 */
class OrganizationCoordinationMetric extends Model
{
    protected $table = 'organization_coordination_metrics';

    protected $fillable = [
        'team_id',
        'user_id',
        'handle',
        'metrics',
        'reported_at',
    ];

    protected $casts = [
        'metrics' => 'array',
        'reported_at' => 'datetime',
    ];
}
