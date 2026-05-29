<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Core\Models\Team;
use Symfony\Component\Uid\UuidV7;

class OrganizationInquiry extends Model
{
    use SoftDeletes;

    protected $table = 'organization_inquiries';

    protected $fillable = [
        'uuid',
        'team_id',
        'inference_run_id',
        'inference_prompt_id',
        'entity_id',
        'inquiry_type',
        'recipient_mode',
        'fields',
        'context_summary',
        'status',
        'due_date',
        'completed_at',
        'follow_up_signal_id',
        'aggregated_result',
    ];

    protected $casts = [
        'fields' => 'array',
        'aggregated_result' => 'array',
        'due_date' => 'date',
        'completed_at' => 'datetime',
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

    public function inferenceRun(): BelongsTo
    {
        return $this->belongsTo(OrganizationInferenceRun::class, 'inference_run_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(OrganizationInquiryRecipient::class, 'inquiry_id');
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['pending', 'partial']);
    }

    public function scopeOverdue($query)
    {
        return $query->whereIn('status', ['pending', 'partial'])
            ->where('due_date', '<', now()->toDateString());
    }

    /**
     * Check if the inquiry is completed based on recipient_mode.
     */
    public function checkCompletion(): bool
    {
        $recipients = $this->recipients;
        $answered = $recipients->where('status', 'answered');

        $completed = match ($this->recipient_mode) {
            'any' => $answered->count() >= 1,
            'all', 'consensus' => $answered->count() >= $recipients->count(),
            default => $answered->count() >= $recipients->count(),
        };

        if ($completed) {
            $this->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        } elseif ($answered->count() > 0 && $this->status === 'pending') {
            $this->update(['status' => 'partial']);
        }

        return $completed;
    }
}
