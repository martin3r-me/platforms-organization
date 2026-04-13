<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Symfony\Component\Uid\UuidV7;

class OrganizationProcessFlow extends Model
{
    use SoftDeletes;

    protected $table = 'organization_process_flows';

    protected $fillable = [
        'uuid',
        'team_id',
        'user_id',
        'process_id',
        'from_step_id',
        'to_step_id',
        'condition_label',
        'condition_expression',
        'is_default',
        'metadata',
    ];

    protected $casts = [
        'condition_expression' => 'array',
        'is_default'           => 'boolean',
        'metadata'             => 'array',
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

    public function fromStep(): BelongsTo
    {
        return $this->belongsTo(OrganizationProcessStep::class, 'from_step_id');
    }

    public function toStep(): BelongsTo
    {
        return $this->belongsTo(OrganizationProcessStep::class, 'to_step_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }
}
