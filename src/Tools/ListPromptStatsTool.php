<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationInferencePromptStat;
use Platform\Organization\Models\OrganizationSignalInferencePrompt;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ListPromptStatsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.prompt_stats.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/prompt_stats - Precision-Statistiken pro Inference-Prompt. Zeigt acknowledged/dismissed Ratio, Trends.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID.',
                ],
                'inference_prompt_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Filter nach Prompt-ID.',
                ],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $query = OrganizationSignalInferencePrompt::forTeam($rootTeamId);

            if (! empty($arguments['inference_prompt_id'])) {
                $query->where('id', (int) $arguments['inference_prompt_id']);
            }

            $prompts = $query->with('stats')->get();

            $data = $prompts->map(function ($prompt) {
                $latestStats = $prompt->stats->sortByDesc('period')->first();
                $allTimeStats = $prompt->stats;

                $totalCreated = $allTimeStats->sum('signals_created');
                $totalAck = $allTimeStats->sum('signals_acknowledged');
                $totalDismissed = $allTimeStats->sum('signals_dismissed');
                $totalResolved = $allTimeStats->sum('signals_resolved');
                $totalFeedback = $totalAck + $totalDismissed;
                $overallPrecision = $totalFeedback > 0 ? round($totalAck / $totalFeedback, 3) : null;

                return [
                    'prompt_id' => $prompt->id,
                    'prompt_name' => $prompt->name,
                    'vsm_system' => $prompt->vsm_system,
                    'is_active' => $prompt->is_active,
                    'all_time' => [
                        'signals_created' => $totalCreated,
                        'signals_acknowledged' => $totalAck,
                        'signals_dismissed' => $totalDismissed,
                        'signals_resolved' => $totalResolved,
                        'precision' => $overallPrecision,
                    ],
                    'current_period' => $latestStats ? [
                        'period' => $latestStats->period?->format('Y-m'),
                        'signals_created' => $latestStats->signals_created,
                        'precision' => $latestStats->precision,
                    ] : null,
                    'auto_disable_risk' => $overallPrecision !== null && $overallPrecision < 0.2 && $totalFeedback >= 10,
                ];
            })->values()->toArray();

            return ToolResult::success(['data' => $data]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Stats: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'inference', 'prompts', 'precision', 'stats'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
