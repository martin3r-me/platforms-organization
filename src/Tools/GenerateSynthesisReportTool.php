<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Jobs\InferenceWorkerJob;
use Platform\Organization\Models\OrganizationInferenceTrigger;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class GenerateSynthesisReportTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.synthesis.generate';
    }

    public function getDescription(): string
    {
        return 'POST /organization/synthesis/generate - Löst die Generierung eines Synthese-Reports aus (weekly/monthly/quarterly).';
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
                'report_type' => [
                    'type' => 'string',
                    'description' => 'Optional: Report-Typ. Default: weekly.',
                    'enum' => ['weekly', 'monthly', 'quarterly'],
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

            $reportType = $arguments['report_type'] ?? 'weekly';

            $trigger = OrganizationInferenceTrigger::create([
                'team_id' => $rootTeamId,
                'trigger_type' => 'scheduled',
                'prompt_filter' => ['synthesis' => true, 'report_type' => $reportType],
                'priority' => 30,
                'status' => 'processing',
                'debounce_key' => null,
                'created_at' => now(),
            ]);

            InferenceWorkerJob::dispatch($trigger);

            return ToolResult::success([
                'trigger_id' => $trigger->id,
                'report_type' => $reportType,
                'message' => "Synthese-Report ({$reportType}) wird generiert. Job in Queue.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Auslösen des Reports: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'synthesis', 'reports', 'generate'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
