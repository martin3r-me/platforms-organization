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
        'signal_definition_id',
        'inference_prompt_id',
        'entity_id',
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
        'affected_entity_ids',
        'assignee_entity_id',
    ];

    protected $casts = [
        'trigger_metrics' => 'array',
        'suggested_actions' => 'array',
        'resolved_at' => 'datetime',
        'snooze_until' => 'datetime',
        'affected_entity_ids' => 'array',
    ];

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
}
