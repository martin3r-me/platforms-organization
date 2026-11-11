<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine Regel der Arbeitsweise eines Engrams (gespiegelt). Nur Lesesicht — geändert wird per Kuratier-Auftrag.
 */
class OrganizationAgentRule extends Model
{
    protected $table = 'organization_agent_rules';

    protected $fillable = [
        'organization_entity_id', 'rule_id', 'ord', 'kind', 'trigger', 'text', 'why', 'source', 'status',
        'supersedes', 'confirmed', 'refuted', 'sha', 'rule_created_at', 'rule_updated_at', 'last_used_at',
    ];

    protected $casts = [
        'rule_id' => 'integer',
        'ord' => 'integer',
        'supersedes' => 'integer',
        'confirmed' => 'integer',
        'refuted' => 'integer',
    ];

    public const LIVE = ['active', 'probation'];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'organization_entity_id');
    }

    public function isLive(): bool
    {
        return in_array($this->status, self::LIVE, true);
    }
}
