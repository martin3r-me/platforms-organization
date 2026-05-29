<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Core\Models\Team;
use Symfony\Component\Uid\UuidV7;

class OrganizationMemoryEntry extends Model
{
    use SoftDeletes;

    protected $table = 'organization_memory_entries';

    protected $fillable = [
        'uuid',
        'team_id',
        'entity_id',
        'inference_prompt_id',
        'memory_type',
        'content',
        'structured_data',
        'confidence',
        'source_type',
        'source_id',
        'valid_until',
        'is_active',
        'reinforcement_count',
    ];

    protected $casts = [
        'structured_data' => 'array',
        'confidence' => 'float',
        'valid_until' => 'datetime',
        'is_active' => 'boolean',
        'reinforcement_count' => 'integer',
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

    public function entity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'entity_id');
    }

    public function inferencePrompt(): BelongsTo
    {
        return $this->belongsTo(OrganizationSignalInferencePrompt::class, 'inference_prompt_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeForEntity($query, int $entityId)
    {
        return $query->where('entity_id', $entityId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('memory_type', $type);
    }

    public function scopeValid($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('valid_until')
                ->orWhere('valid_until', '>', now());
        });
    }

    /**
     * Reinforce this memory entry (increase confidence and extend validity).
     */
    public function reinforce(float $confidenceBoost = 0.1, ?int $extendDays = null): void
    {
        $this->reinforcement_count++;
        $this->confidence = min(1.0, $this->confidence + $confidenceBoost);

        if ($extendDays && $this->valid_until) {
            $this->valid_until = $this->valid_until->addDays($extendDays);
        }

        $this->save();
    }
}
