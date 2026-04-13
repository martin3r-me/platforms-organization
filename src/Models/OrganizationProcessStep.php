<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Symfony\Component\Uid\UuidV7;

class OrganizationProcessStep extends Model
{
    use SoftDeletes;

    protected $table = 'organization_process_steps';

    protected $fillable = [
        'uuid',
        'team_id',
        'user_id',
        'process_id',
        'name',
        'description',
        'position',
        'step_type',
        'duration_target_minutes',
        'wait_target_minutes',
        'corefit_classification',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'position'                => 'integer',
        'duration_target_minutes' => 'integer',
        'wait_target_minutes'     => 'integer',
        'is_active'               => 'boolean',
        'metadata'                => 'array',
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

    public function process(): BelongsTo
    {
        return $this->belongsTo(OrganizationProcess::class, 'process_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function outgoingFlows(): HasMany
    {
        return $this->hasMany(OrganizationProcessFlow::class, 'from_step_id');
    }

    public function incomingFlows(): HasMany
    {
        return $this->hasMany(OrganizationProcessFlow::class, 'to_step_id');
    }

    public function stepEntities(): HasMany
    {
        return $this->hasMany(OrganizationProcessStepEntity::class, 'process_step_id');
    }

    public function stepInterlinks(): HasMany
    {
        return $this->hasMany(OrganizationProcessStepInterlink::class, 'process_step_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }
}
