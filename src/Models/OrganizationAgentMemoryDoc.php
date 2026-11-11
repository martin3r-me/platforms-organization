<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine steuernde Datei aus dem Vault eines Engrams (Arbeitsweise, Index, offene Fragen, Playbook),
 * vom Daemon gespiegelt. Nur Lesesicht — geändert wird über einen Kuratier-Auftrag.
 */
class OrganizationAgentMemoryDoc extends Model
{
    protected $table = 'organization_agent_memory_docs';

    protected $fillable = [
        'organization_entity_id',
        'path',
        'kind',
        'title',
        'sha',
        'content',
        'file_modified_at',
    ];

    protected $casts = [
        'file_modified_at' => 'datetime',
    ];

    public const KINDS = ['routine', 'index', 'questions', 'playbook'];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'organization_entity_id');
    }
}
