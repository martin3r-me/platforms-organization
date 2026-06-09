<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Organization\Models\OrganizationEntity;
use Symfony\Component\Uid\UuidV7;

class OrganizationSignalInferencePrompt extends Model
{
    use SoftDeletes;

    protected $table = 'organization_signal_inference_prompts';

    protected $fillable = [
        'uuid',
        'team_id',
        'user_id',
        'agent_entity_id',
        'name',
        'description',
        'vsm_system',
        'prompt_template',
        'data_sources',
        'dimension',
        'default_severity',
        'scope_type',
        'scope_value',
        'is_active',
        'schedule_interval_hours',
        'last_evaluated_at',
        'last_error',
        'run_count',
    ];

    protected $casts = [
        'data_sources' => 'array',
        'scope_value' => 'array',
        'is_active' => 'boolean',
        'schedule_interval_hours' => 'integer',
        'last_evaluated_at' => 'datetime',
        'run_count' => 'integer',
    ];

    public const HEALTH_HEALTHY = 'healthy';
    public const HEALTH_STALE = 'stale';
    public const HEALTH_ERROR = 'error';
    public const HEALTH_NEVER_RUN = 'never_run';

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                do {
                    $uuid = UuidV7::generate();
                } while (self::where('uuid', $uuid)->exists());

                $model->uuid = $uuid;
            }

            if (! $model->user_id) {
                $model->user_id = Auth::id();
            }

            if (! $model->team_id) {
                $model->team_id = Auth::user()?->currentTeamRelation?->id;
            }
        });

        static::saving(function (self $model) {
            // Wenn ein Agent verknuepft ist, muss er ein system_agent Entity-Type sein.
            // Saubere Spezialisierung pro Ebene: alle Prompts eines Agents teilen vsm_system.
            if ($model->agent_entity_id) {
                $agent = OrganizationEntity::with('type')->find($model->agent_entity_id);
                if (!$agent) {
                    throw new \InvalidArgumentException("agent_entity_id {$model->agent_entity_id} nicht gefunden.");
                }
                if ($agent->type?->code !== 'system_agent') {
                    throw new \InvalidArgumentException(
                        "agent_entity_id muss ein system_agent Entity-Type sein "
                        . "(ist '{$agent->type?->code}')."
                    );
                }

                // vsm_system-Konsistenz: andere Prompts dieses Agents muessen dasselbe vsm_system haben.
                $otherVsm = self::where('agent_entity_id', $model->agent_entity_id)
                    ->where('id', '!=', $model->id ?? 0)
                    ->whereNotNull('vsm_system')
                    ->pluck('vsm_system')
                    ->unique();

                if ($otherVsm->isNotEmpty() && !$otherVsm->contains($model->vsm_system)) {
                    throw new \InvalidArgumentException(
                        "Alle Prompts eines Agents muessen dasselbe vsm_system haben. "
                        . "Bestehende: " . $otherVsm->implode(', ') . ", neuer: {$model->vsm_system}."
                    );
                }
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function signals(): HasMany
    {
        return $this->hasMany(OrganizationSignal::class, 'inference_prompt_id');
    }

    public function memoryEntries(): HasMany
    {
        return $this->hasMany(OrganizationMemoryEntry::class, 'inference_prompt_id');
    }

    public function stats(): HasMany
    {
        return $this->hasMany(OrganizationInferencePromptStat::class, 'inference_prompt_id');
    }

    public function agentEntity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'agent_entity_id');
    }

    /**
     * Berechneter Health-Status aus last_evaluated_at + schedule_interval_hours + last_error.
     * never_run > error > stale > healthy.
     */
    public function getHealthStatusAttribute(): string
    {
        if (!$this->last_evaluated_at) {
            return self::HEALTH_NEVER_RUN;
        }
        if (!empty($this->last_error)) {
            return self::HEALTH_ERROR;
        }

        $intervalHours = $this->schedule_interval_hours
            ?? config('organization.inference.default_interval_hours', 72);
        $staleThreshold = $this->last_evaluated_at->copy()->addHours((int) ($intervalHours * 1.5));

        return now()->greaterThan($staleThreshold) ? self::HEALTH_STALE : self::HEALTH_HEALTHY;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeForVsmSystem($query, string $system)
    {
        return $query->where('vsm_system', $system);
    }

    public function scopeDue($query)
    {
        $defaultHours = config('organization.inference.default_interval_hours', 72);

        return $query->where(function ($q) use ($defaultHours) {
            $q->whereNull('last_evaluated_at')
                ->orWhereRaw(
                    'last_evaluated_at <= NOW() - INTERVAL COALESCE(schedule_interval_hours, ?) HOUR',
                    [$defaultHours]
                );
        });
    }
}
