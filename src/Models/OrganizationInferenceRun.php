<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Platform\Core\Models\Team;
use Platform\Core\Services\LlmCostCalculator;
use Symfony\Component\Uid\UuidV7;

class OrganizationInferenceRun extends Model
{
    protected $table = 'organization_inference_runs';

    protected $fillable = [
        'uuid',
        'team_id',
        'trigger_id',
        'trigger_type',
        'status',
        'prompts_evaluated',
        'entities_analyzed',
        'signals_created',
        'inquiries_created',
        'memory_updates',
        'do_nothing_count',
        'duration_ms',
        'llm_model',
        'token_usage',
        'summary',
        'error_message',
    ];

    protected $casts = [
        'token_usage' => 'array',
        'prompts_evaluated' => 'integer',
        'entities_analyzed' => 'integer',
        'signals_created' => 'integer',
        'inquiries_created' => 'integer',
        'memory_updates' => 'integer',
        'do_nothing_count' => 'integer',
        'duration_ms' => 'integer',
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

    public function trigger(): BelongsTo
    {
        return $this->belongsTo(OrganizationInferenceTrigger::class, 'trigger_id');
    }

    public function synthesisReports(): HasMany
    {
        return $this->hasMany(OrganizationSynthesisReport::class, 'inference_run_id');
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(OrganizationInquiry::class, 'inference_run_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(OrganizationInferenceRunStep::class, 'inference_run_id')
            ->orderBy('step_index');
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function getTotalTokens(): int
    {
        $usage = $this->token_usage ?? [];
        return (int) ($usage['input_tokens'] ?? 0) + (int) ($usage['output_tokens'] ?? 0);
    }

    /**
     * Kosten dieses Runs in USD (berechnet aus token_usage × Modellpreis).
     */
    public function getCostUsdAttribute(): float
    {
        return app(LlmCostCalculator::class)
            ->costUsd($this->llm_model ?: 'default', $this->token_usage ?? []);
    }

    /**
     * Kosten in EUR, sofern ein USD→EUR-Kurs konfiguriert ist — sonst null.
     */
    public function getCostEurAttribute(): ?float
    {
        return app(LlmCostCalculator::class)
            ->costEur($this->llm_model ?: 'default', $this->token_usage ?? []);
    }

    /**
     * Kosten pro erzeugtem Signal in USD — null, wenn keine Signale entstanden sind.
     */
    public function getCostPerSignalUsdAttribute(): ?float
    {
        $signals = (int) ($this->signals_created ?? 0);

        return $signals > 0 ? $this->cost_usd / $signals : null;
    }

    /**
     * Kompakte Kostenübersicht (usd/eur/model/tokens) für Logs und UI.
     */
    public function costBreakdown(): array
    {
        return app(LlmCostCalculator::class)
            ->breakdown($this->llm_model ?: 'default', $this->token_usage ?? []);
    }

    public function markCompleted(array $stats = []): void
    {
        $this->update(array_merge($stats, ['status' => 'completed']));
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
        ]);
    }
}
