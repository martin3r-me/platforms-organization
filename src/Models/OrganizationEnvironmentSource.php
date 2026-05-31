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

class OrganizationEnvironmentSource extends Model
{
    use SoftDeletes;

    protected $table = 'organization_environment_sources';

    protected $fillable = [
        'uuid',
        'team_id',
        'user_id',
        'name',
        'source_type',
        'category',
        'config',
        'pull_interval_hours',
        'is_active',
        'last_pulled_at',
    ];

    protected $casts = [
        'config' => 'array',
        'is_active' => 'boolean',
        'pull_interval_hours' => 'integer',
        'last_pulled_at' => 'datetime',
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

    public function snapshots(): HasMany
    {
        return $this->hasMany(OrganizationEnvironmentSnapshot::class, 'source_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeDue($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('last_pulled_at')
                ->orWhereRaw(
                    'last_pulled_at <= NOW() - INTERVAL pull_interval_hours HOUR'
                );
        });
    }
}
