<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine Zeile im Live-Aktivitäts-Feed eines Agenten (vom Daemon gemeldet). Reine Anzeige-Daten;
 * geordnet über die auto-increment id. created_at wird beim Insert gesetzt (kein updated_at).
 */
class OrganizationAgentRunEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'organization_agent_run_events';

    protected $fillable = [
        'organization_entity_id',
        'run_id',
        'kind',
        'text',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'organization_entity_id');
    }
}
