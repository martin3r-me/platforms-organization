<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein Kuratier-Auftrag an ein Engram: eine Gedächtnis-Datei ersetzen oder stilllegen. Der Daemon
 * holt offene Aufträge ab, wendet sie nur auf dem Stand base_sha an (sonst „stale") und quittiert.
 */
class OrganizationAgentCuration extends Model
{
    protected $table = 'organization_agent_curations';

    protected $fillable = [
        'organization_entity_id',
        'path',
        'op',
        'base_sha',
        'content',
        'note',
        'status',
        'result',
        'created_by_user_id',
        'applied_at',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
    ];

    public const OPS = ['replace', 'retire', 'revise', 'approve', 'approve_import', 'reject_import'];

    public const FINAL = ['applied', 'stale', 'rejected', 'cancelled'];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'organization_entity_id');
    }
}
