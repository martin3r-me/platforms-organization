<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Organization\Enums\RunStatus;
use Symfony\Component\Uid\UuidV7;

class OrganizationProcessRun extends Model
{
    use SoftDeletes;

    protected $table = 'organization_process_runs';

    protected $fillable = [
        'uuid',
        'team_id',
        'user_id',
        'process_id',
        'status',
        'notes',
        'started_at',
        'completed_at',
        'cancelled_at',
        'metadata',
    ];

    protected $casts = [
        'status'       => RunStatus::class,
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata'     => 'array',
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

    public function runSteps(): HasMany
    {
        return $this->hasMany(OrganizationProcessRunStep::class, 'run_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', RunStatus::ACTIVE);
    }

    public function scopeForProcess($query, int $processId)
    {
        return $query->where('process_id', $processId);
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }
}
