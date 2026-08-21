<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Runtime-Profil einer agent-Entity: was der Client-Daemon zieht (Domäne, Stufen, Governor,
 * active) + was er zurückmeldet (Status, Usage, Heartbeat). Keine Secrets — Claude-Login und
 * GitHub-Token liegen auf dem Client; hier nur github_username (Referenz) + Status.
 *
 * @property string|null $domain   development|backoffice|helpdesk|assistant (S1) | analysis (S2–S4)
 * @property array|null  $stages
 */
class OrganizationAgentProfile extends Model
{
    protected $fillable = [
        'organization_entity_id',
        'five_hour_reserve_pct',
        'seven_day_burn_margin_pct',
        'max_story_points',
        'claude_model',
        'active',
        'github_username',
        'status',
        'claude_subscription',
        'five_hour_pct',
        'seven_day_pct',
        'last_heartbeat_at',
    ];

    protected $casts = [
        'five_hour_reserve_pct' => 'integer',
        'seven_day_burn_margin_pct' => 'integer',
        'max_story_points' => 'integer',
        'active' => 'boolean',
        'five_hour_pct' => 'decimal:2',
        'seven_day_pct' => 'decimal:2',
        'last_heartbeat_at' => 'datetime',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'organization_entity_id');
    }

    /** Liveness: hat der Daemon in den letzten paar Minuten ein Heartbeat gesendet? */
    public function isOnline(int $withinSeconds = 180): bool
    {
        return $this->last_heartbeat_at !== null
            && $this->last_heartbeat_at->greaterThan(now()->subSeconds($withinSeconds));
    }
}
