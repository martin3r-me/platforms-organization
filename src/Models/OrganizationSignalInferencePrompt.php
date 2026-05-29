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

class OrganizationSignalInferencePrompt extends Model
{
    use SoftDeletes;

    protected $table = 'organization_signal_inference_prompts';

    protected $fillable = [
        'uuid',
        'team_id',
        'user_id',
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
        'last_evaluated_at',
    ];

    protected $casts = [
        'data_sources' => 'array',
        'scope_value' => 'array',
        'is_active' => 'boolean',
        'last_evaluated_at' => 'datetime',
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

    public function signals(): HasMany
    {
        return $this->hasMany(OrganizationSignal::class, 'inference_prompt_id');
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
        return $query->where(function ($q) {
            $q->whereNull('last_evaluated_at')
                ->orWhere('last_evaluated_at', '<=', now()->subHours(24));
        });
    }
}
