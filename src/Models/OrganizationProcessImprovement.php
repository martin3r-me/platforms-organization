<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Symfony\Component\Uid\UuidV7;

class OrganizationProcessImprovement extends Model
{
    use SoftDeletes;

    protected $table = 'organization_process_improvements';

    protected $fillable = [
        'uuid',
        'team_id',
        'user_id',
        'process_id',
        'title',
        'description',
        'category',
        'priority',
        'status',
        'expected_outcome',
        'actual_outcome',
        'before_snapshot_id',
        'after_snapshot_id',
        'completed_at',
        'metadata',
        'target_step_id',
        'projected_duration_target_minutes',
        'projected_automation_level',
        'projected_complexity',
    ];

    protected $casts = [
        'completed_at'                     => 'datetime',
        'metadata'                         => 'array',
        'projected_duration_target_minutes' => 'integer',
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
            if (! $model->user_id) {
                $model->user_id = Auth::id();
            }
            if (! $model->team_id) {
                $model->team_id = Auth::user()?->currentTeamRelation?->id;
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

    public function process(): BelongsTo
    {
        return $this->belongsTo(OrganizationProcess::class, 'process_id');
    }

    public function beforeSnapshot(): BelongsTo
    {
        return $this->belongsTo(OrganizationProcessSnapshot::class, 'before_snapshot_id');
    }

    public function afterSnapshot(): BelongsTo
    {
        return $this->belongsTo(OrganizationProcessSnapshot::class, 'after_snapshot_id');
    }

    public function targetStep(): BelongsTo
    {
        return $this->belongsTo(OrganizationProcessStep::class, 'target_step_id');
    }

    public function scopeForProcess($query, int $processId)
    {
        return $query->where('process_id', $processId);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
