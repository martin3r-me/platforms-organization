<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Platform\ActivityLog\Traits\LogsActivity;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Symfony\Component\Uid\UuidV7;

class OrganizationSignal extends Model
{
    use SoftDeletes, LogsActivity;

    protected $table = 'organization_signals';

    protected $fillable = [
        'uuid',
        'team_id',
        'source',
        'source_type',
        'signal_definition_id',
        'inference_prompt_id',
        'entity_id',
        'perspective_entity_id',
        'created_by_agent_entity_id',
        'current_owner_entity_id',
        'vsm_level',
        'status',
        'severity',
        'message',
        'trigger_metrics',
        'suggested_actions',
        'resolved_at',
        'resolved_by',
        'dismissed_reason',
        'resolution_summary',
        'snooze_until',
        'escalated_at',
        'deadline_at',
        'acknowledged_at',
        'affected_entity_ids',
        'assignee_entity_id',
    ];

    protected $casts = [
        'trigger_metrics' => 'array',
        'suggested_actions' => 'array',
        'resolved_at' => 'datetime',
        'snooze_until' => 'datetime',
        'escalated_at' => 'datetime',
        'deadline_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'affected_entity_ids' => 'array',
    ];

    public const SOURCE_TYPE_INFERENCE = 'inference';
    public const SOURCE_TYPE_INFERENCE_S3STAR = 'inference_s3star';
    public const SOURCE_TYPE_RULE_CRON = 'rule_cron';
    public const SOURCE_TYPE_HUMAN_ALGEDONIC = 'human_algedonic';
    public const SOURCE_TYPE_S4_ENVIRONMENTAL = 's4_environmental';
    public const SOURCE_TYPE_CROSS_ENTITY = 'cross_entity';
    public const SOURCE_TYPE_SYSTEM_HEALTH = 'system_health';
    public const SOURCE_TYPE_AGGREGATION = 'aggregation';

    public const VSM_LEVELS = ['s1', 's2', 's3', 's3_star', 's4', 's5'];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                do {
                    $uuid = UuidV7::generate();
                } while (self::where('uuid', $uuid)->exists());

                $model->uuid = $uuid;
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(OrganizationSignalDefinition::class, 'signal_definition_id');
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'entity_id');
    }

    public function perspectiveEntity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'perspective_entity_id');
    }

    public function createdByAgent(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'created_by_agent_entity_id');
    }

    public function currentOwner(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'current_owner_entity_id');
    }

    /**
     * Filtert Signale auf die aktive Perspektive. NULL-perspektivisch (Legacy
     * oder unklar zugeordnet) wird wahlweise mit-einbezogen.
     */
    public function scopeForPerspective($query, ?int $perspectiveEntityId, bool $includeNull = true)
    {
        if ($perspectiveEntityId === null) {
            return $query;
        }
        return $query->where(function ($q) use ($perspectiveEntityId, $includeNull) {
            $q->where('perspective_entity_id', $perspectiveEntityId);
            if ($includeNull) {
                $q->orWhereNull('perspective_entity_id');
            }
        });
    }

    public function inferencePrompt(): BelongsTo
    {
        return $this->belongsTo(OrganizationSignalInferencePrompt::class, 'inference_prompt_id');
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(OrganizationSignalComment::class, 'signal_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(OrganizationSignalAction::class, 'signal_id')->orderBy('position');
    }

    public function focuses(): HasMany
    {
        return $this->hasMany(OrganizationSignalFocus::class, 'signal_id');
    }

    public function focusedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_signal_focuses', 'signal_id', 'user_id')
            ->withTimestamps()
            ->withPivot('focused_at');
    }

    public function isFocusedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->relationLoaded('focuses')) {
            return $this->focuses->contains('user_id', $user->id);
        }

        return $this->focuses()->where('user_id', $user->id)->exists();
    }

    public function scopeFocusedBy($query, int $userId)
    {
        return $query->whereHas('focuses', fn ($q) => $q->where('user_id', $userId));
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'assignee_entity_id');
    }

    /**
     * Leitet den Signal-Status aus den per-Action-Entscheidungen ab.
     * Gibt null zurück, wenn das Signal keine Actions hat oder noch pending-Actions offen sind.
     */
    public function deriveStatusFromActions(): ?string
    {
        $actions = $this->actions;

        if ($actions->isEmpty() || $actions->contains(fn ($a) => $a->status === 'pending')) {
            return null;
        }

        $applied = $actions->where('status', 'applied')->count();
        $dismissed = $actions->where('status', 'dismissed')->count();

        if ($applied > 0 && $dismissed === 0) {
            return 'resolved';
        }

        if ($dismissed > 0 && $applied === 0) {
            return 'dismissed';
        }

        return 'acknowledged';
    }

    public function scopeAlgedonic($query)
    {
        return $query->where('severity', 'algedonic');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    public function scopeActionable($query)
    {
        return $query->whereIn('status', ['open', 'acknowledged'])
            ->where(function ($q) {
                $q->whereNull('snooze_until')
                    ->orWhere('snooze_until', '<=', now());
            });
    }

    public function scopeSnoozed($query)
    {
        return $query->whereNotNull('snooze_until')
            ->where('snooze_until', '>', now());
    }

    /**
     * Signale, deren Deadline ueberschritten ist und die nicht acknowledged sind.
     */
    public function scopeDueForEscalation($query)
    {
        return $query->whereIn('status', ['open', 'acknowledged'])
            ->whereNull('acknowledged_at')
            ->whereNotNull('deadline_at')
            ->where('deadline_at', '<', now());
    }

    public function acknowledge(): void
    {
        if ($this->acknowledged_at) {
            return;
        }
        $this->update([
            'acknowledged_at' => now(),
            'status' => 'acknowledged',
        ]);
    }
}
