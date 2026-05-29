<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'trigger_metrics' => 'array',
        'resolved_at' => 'datetime',
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
}
