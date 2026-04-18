<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\Organization\Enums\RunStepStatus;
use Symfony\Component\Uid\UuidV7;

class OrganizationProcessRunStep extends Model
{
    protected $table = 'organization_process_run_steps';

    protected $fillable = [
        'uuid',
        'run_id',
        'process_step_id',
        'status',
        'position',
        'active_duration_minutes',
        'wait_duration_minutes',
        'wait_override',
        'checked_at',
        'notes',
    ];

    protected $casts = [
        'status'                  => RunStepStatus::class,
        'position'                => 'integer',
        'active_duration_minutes' => 'integer',
        'wait_duration_minutes'   => 'integer',
        'wait_override'           => 'boolean',
        'checked_at'              => 'datetime',
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

    public function run(): BelongsTo
    {
        return $this->belongsTo(OrganizationProcessRun::class, 'run_id');
    }

    public function processStep(): BelongsTo
    {
        return $this->belongsTo(OrganizationProcessStep::class, 'process_step_id');
    }
}
