<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationInferencePromptStat extends Model
{
    protected $table = 'organization_inference_prompt_stats';

    protected $fillable = [
        'inference_prompt_id',
        'period',
        'signals_created',
        'signals_acknowledged',
        'signals_dismissed',
        'signals_resolved',
        'precision',
        'entity_type_breakdown',
    ];

    protected $casts = [
        'period' => 'date',
        'precision' => 'float',
        'entity_type_breakdown' => 'array',
        'signals_created' => 'integer',
        'signals_acknowledged' => 'integer',
        'signals_dismissed' => 'integer',
        'signals_resolved' => 'integer',
    ];

    public function inferencePrompt(): BelongsTo
    {
        return $this->belongsTo(OrganizationSignalInferencePrompt::class, 'inference_prompt_id');
    }

    /**
     * Recalculate precision for a prompt's current period.
     */
    public static function recalculate(int $promptId): void
    {
        $period = now()->startOfMonth()->toDateString();

        $stat = static::firstOrCreate(
            ['inference_prompt_id' => $promptId, 'period' => $period],
            ['signals_created' => 0, 'signals_acknowledged' => 0, 'signals_dismissed' => 0, 'signals_resolved' => 0, 'precision' => 0.0]
        );

        $total = $stat->signals_acknowledged + $stat->signals_dismissed;
        $stat->precision = $total > 0 ? round($stat->signals_acknowledged / $total, 3) : 0.0;
        $stat->save();
    }
}
