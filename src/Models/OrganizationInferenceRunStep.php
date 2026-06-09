<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationInferenceRunStep extends Model
{
    protected $table = 'organization_inference_run_steps';

    public const TYPE_TOOL_CALL = 'tool_call';
    public const TYPE_ASSISTANT_MESSAGE = 'assistant_message';
    public const TYPE_ERROR = 'error';

    protected $fillable = [
        'inference_run_id',
        'inference_prompt_id',
        'step_index',
        'step_type',
        'tool_name',
        'arguments',
        'result',
        'result_ok',
        'error_message',
        'duration_ms',
        'occurred_at',
    ];

    protected $casts = [
        'arguments' => 'array',
        'result' => 'array',
        'result_ok' => 'boolean',
        'duration_ms' => 'integer',
        'step_index' => 'integer',
        'occurred_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(OrganizationInferenceRun::class, 'inference_run_id');
    }

    public function prompt(): BelongsTo
    {
        return $this->belongsTo(OrganizationSignalInferencePrompt::class, 'inference_prompt_id');
    }
}
