<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Runtime-Profil einer agent-Entity: was der Client-Daemon zieht (Governor, Modell, Claim-Cap,
 * active) + was er zurückmeldet (Status, Usage, Heartbeat). Keine Secrets — Claude-Login und
 * GitHub-Token liegen auf dem Client; hier nur github_username (Referenz) + Status.
 *
 * @property int         $id
 * @property int         $organization_entity_id
 * @property int         $five_hour_reserve_pct
 * @property int         $seven_day_burn_margin_pct
 * @property int|null    $max_story_points
 * @property string|null $claude_model
 * @property bool        $claim_unassigned
 * @property bool        $active
 * @property string|null $github_username
 * @property string|null $status
 * @property string|null $claude_subscription
 * @property string|null $five_hour_pct
 * @property string|null $seven_day_pct
 * @property \Illuminate\Support\Carbon|null $last_heartbeat_at
 * @property array|null  $settings domänen-spezifische Felder ohne eigene Spalte (AgentSettingsProvider, storage=bag)
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class OrganizationAgentProfile extends Model
{
    protected $fillable = [
        'organization_entity_id',
        'five_hour_reserve_pct',
        'seven_day_burn_margin_pct',
        'max_story_points',
        'claude_model',
        'claim_unassigned',
        'active',
        'github_username',
        'status',
        'claude_subscription',
        'five_hour_pct',
        'seven_day_pct',
        'last_heartbeat_at',
        'settings',
    ];

    protected $casts = [
        'five_hour_reserve_pct' => 'integer',
        'seven_day_burn_margin_pct' => 'integer',
        'max_story_points' => 'integer',
        'claim_unassigned' => 'boolean',
        'active' => 'boolean',
        'five_hour_pct' => 'decimal:2',
        'seven_day_pct' => 'decimal:2',
        'last_heartbeat_at' => 'datetime',
        'settings' => 'array',
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
