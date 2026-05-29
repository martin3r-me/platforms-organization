<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Core\Models\Team;
use Symfony\Component\Uid\UuidV7;

class OrganizationSynthesisReport extends Model
{
    use SoftDeletes;

    protected $table = 'organization_synthesis_reports';

    protected $fillable = [
        'uuid',
        'team_id',
        'inference_run_id',
        'report_type',
        'period_start',
        'period_end',
        'title',
        'content',
        'structured_summary',
        'signals_included',
        'inquiries_included',
        'algedonic_signals',
        'recipient_scope',
        'status',
        'published_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'structured_summary' => 'array',
        'signals_included' => 'array',
        'inquiries_included' => 'array',
        'algedonic_signals' => 'array',
        'recipient_scope' => 'array',
        'published_at' => 'datetime',
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

    public function inferenceRun(): BelongsTo
    {
        return $this->belongsTo(OrganizationInferenceRun::class, 'inference_run_id');
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }
}
