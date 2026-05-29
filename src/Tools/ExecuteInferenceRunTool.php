<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Jobs\InferenceWorkerJob;
use Platform\Organization\Models\OrganizationInferenceTrigger;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ExecuteInferenceRunTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.inference_runs.execute';
    }

    public function getDescription(): string
    {
        return 'POST /organization/inference_runs/execute - Löst einen Inference-Run manuell aus (on-demand). Erzeugt einen Trigger und dispatched den Job.';
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
                    'description' => 'Optional: Spezifischen Prompt evaluieren.',
                ],
                'vsm_system' => [
                    'type' => 'string',
                    'description' => 'Optional: Alle Prompts eines VSM-Systems evaluieren.',
                    'enum' => ['s1', 's2', 's3', 's3_star', 's4', 's5'],
                ],
                'sync' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Synchron ausführen statt Queue (für Debugging). Default: false.',
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

            $promptFilter = [];
            if (! empty($arguments['inference_prompt_id'])) {
                $promptFilter['prompt_ids'] = [(int) $arguments['inference_prompt_id']];
            } elseif (! empty($arguments['vsm_system'])) {
                $promptFilter['vsm_system'] = $arguments['vsm_system'];
            }

            $trigger = OrganizationInferenceTrigger::create([
                'team_id' => $rootTeamId,
                'trigger_type' => 'on_demand',
                'prompt_filter' => ! empty($promptFilter) ? $promptFilter : null,
                'priority' => 70,
                'status' => 'processing',
                'debounce_key' => null, // On-demand: no debouncing
                'created_at' => now(),
            ]);

            if (! empty($arguments['sync'])) {
                InferenceWorkerJob::dispatchSync($trigger);
            } else {
                InferenceWorkerJob::dispatch($trigger);
            }

            return ToolResult::success([
                'trigger_id' => $trigger->id,
                'message' => 'Inference-Run ausgelöst. Job wurde in die Queue gestellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Auslösen des Runs: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'inference', 'runs', 'execute'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
