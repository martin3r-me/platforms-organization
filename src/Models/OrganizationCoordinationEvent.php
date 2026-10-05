<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ein rohes Koordinations-Event (TRAAN/Parlan), von Parlan gepusht. Reine Datenablage — der
 * Snapshot-Command aggregiert daraus pro Entität die 7½-Dimensionen-Kennzahlen. Idempotent über
 * event_id. created_at beim Insert gesetzt (kein updated_at).
 */
class OrganizationCoordinationEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'organization_coordination_events';

    protected $fillable = [
        'event_id',
        'team_id',
        'actor_user_id',
        'actor_handle',
        'counterpart_handle',
        'kind',
        'thread',
        'latency_seconds',
        'occurred_at',
        'created_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
        'latency_seconds' => 'integer',
    ];
}
