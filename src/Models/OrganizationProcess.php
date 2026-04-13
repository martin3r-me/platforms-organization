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

class OrganizationProcess extends Model
{
    use SoftDeletes;

    protected $table = 'organization_processes';

    protected $fillable = [
        'uuid',
        'team_id',
        'user_id',
        'name',
        'code',
        'description',
        'owner_entity_id',
        'vsm_system_id',
        'status',
        'version',
        'is_active',
        'metadata',
        'target_description',
        'value_proposition',
        'cost_analysis',
        'risk_assessment',
        'improvement_levers',
        'action_plan',
        'standardization_notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'version'   => 'integer',
        'metadata'  => 'array',
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

    public function ownerEntity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'owner_entity_id');
    }

    public function vsmSystem(): BelongsTo
    {
        return $this->belongsTo(OrganizationVsmSystem::class, 'vsm_system_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(OrganizationProcessStep::class, 'process_id');
    }

    public function flows(): HasMany
    {
        return $this->hasMany(OrganizationProcessFlow::class, 'process_id');
    }

    public function triggers(): HasMany
    {
        return $this->hasMany(OrganizationProcessTrigger::class, 'process_id');
    }

    public function outputs(): HasMany
    {
        return $this->hasMany(OrganizationProcessOutput::class, 'process_id');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(OrganizationProcessSnapshot::class, 'process_id');
    }

    public function improvements(): HasMany
    {
        return $this->hasMany(OrganizationProcessImprovement::class, 'process_id');
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
