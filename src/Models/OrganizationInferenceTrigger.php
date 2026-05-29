<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\Core\Models\Team;

class OrganizationInferenceTrigger extends Model
{
    public $timestamps = false;

    protected $table = 'organization_inference_triggers';

    protected $fillable = [
        'team_id',
        'trigger_type',
        'trigger_reference',
        'prompt_filter',
        'entity_filter',
        'priority',
        'status',
        'debounce_key',
        'created_at',
        'processed_at',
    ];

    protected $casts = [
        'prompt_filter' => 'array',
        'entity_filter' => 'array',
        'priority' => 'integer',
        'created_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * Create a trigger with debouncing (skip if same debounce_key pending within 1h).
     */
    public static function createDebounced(array $attributes): ?self
    {
        $key = $attributes['debounce_key'] ?? null;

        if ($key) {
            $existing = static::where('debounce_key', $key)
                ->where('status', 'pending')
                ->where('created_at', '>=', now()->subHour())
                ->exists();

            if ($existing) {
                return null;
            }
        }

        $attributes['created_at'] = $attributes['created_at'] ?? now();

        return static::create($attributes);
    }
}
