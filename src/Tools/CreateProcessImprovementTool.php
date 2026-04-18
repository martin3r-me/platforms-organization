<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Enums\ImprovementStatus;
use Platform\Organization\Models\OrganizationProcess;
use Platform\Organization\Models\OrganizationProcessImprovement;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class CreateProcessImprovementTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_improvements.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/process_improvements - Erstellt eine Verbesserung für einen Prozess. Kategorien: cost, quality, speed, risk, standardization.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'            => ['type' => 'integer'],
                'process_id'         => ['type' => 'integer', 'description' => 'ERFORDERLICH.'],
                'title'              => ['type' => 'string', 'description' => 'ERFORDERLICH.'],
                'description'        => ['type' => 'string'],
                'category'           => ['type' => 'string', 'description' => 'ERFORDERLICH: cost | quality | speed | risk | standardization.'],
                'priority'           => ['type' => 'string', 'description' => 'Optional: low | medium | high | critical. Default: medium.'],
                'status'             => ['type' => 'string', 'description' => 'Optional: identified | planned | in_progress | on_hold | completed | under_observation | validated | failed | rejected. Default: identified.'],
                'expected_outcome'   => ['type' => 'string'],
                'before_snapshot_id' => ['type' => 'integer', 'description' => 'Optional: Snapshot-ID für Vorher-Zustand.'],
                'target_step_id'                    => ['type' => 'integer', 'description' => 'Optional: Ziel-Step-ID für Projektion.'],
                'projected_duration_target_minutes' => ['type' => 'integer', 'description' => 'Optional: Projizierte neue Dauer in Minuten.'],
                'projected_automation_level'        => ['type' => 'string', 'description' => 'Optional: human | llm_assisted | llm_autonomous | hybrid.'],
                'projected_complexity'              => ['type' => 'string', 'description' => 'Optional: xs | s | m | l | xl | xxl.'],
                'projected_hourly_rate'             => ['type' => 'number', 'description' => 'Optional: Projizierter Stundensatz in EUR.'],
                'metadata'                          => ['type' => 'object'],
            ],
            'required' => ['process_id', 'title', 'category'],
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

            $process = OrganizationProcess::find($arguments['process_id'] ?? 0);
            if (! $process) {
                return ToolResult::error('NOT_FOUND', 'Prozess nicht gefunden.');
            }
            if ((int) $process->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Prozess gehört nicht zum Team.');
            }

            $title = trim((string) ($arguments['title'] ?? ''));
            if ($title === '') {
                return ToolResult::error('VALIDATION_ERROR', 'title ist erforderlich.');
            }

            $category = $arguments['category'] ?? '';
            if (! in_array($category, ['cost', 'quality', 'speed', 'risk', 'standardization'])) {
                return ToolResult::error('VALIDATION_ERROR', 'category muss cost, quality, speed, risk oder standardization sein.');
            }

            $priority = $arguments['priority'] ?? 'medium';
            if (! in_array($priority, ['low', 'medium', 'high', 'critical'])) {
                $priority = 'medium';
            }

            $status = $arguments['status'] ?? 'identified';
            if (! ImprovementStatus::tryFrom($status)) {
                $status = 'identified';
            }

            $improvement = OrganizationProcessImprovement::create([
                'team_id'            => $rootTeamId,
                'user_id'            => $context->user?->id,
                'process_id'         => $process->id,
                'title'              => $title,
                'description'        => ($arguments['description'] ?? null) ?: null,
                'category'           => $category,
                'priority'           => $priority,
                'status'             => $status,
                'expected_outcome'   => ($arguments['expected_outcome'] ?? null) ?: null,
                'before_snapshot_id'                => ! empty($arguments['before_snapshot_id']) ? (int) $arguments['before_snapshot_id'] : null,
                'target_step_id'                    => ! empty($arguments['target_step_id']) ? (int) $arguments['target_step_id'] : null,
                'projected_duration_target_minutes' => isset($arguments['projected_duration_target_minutes']) ? (int) $arguments['projected_duration_target_minutes'] : null,
                'projected_automation_level'        => ! empty($arguments['projected_automation_level']) ? $arguments['projected_automation_level'] : null,
                'projected_complexity'              => ! empty($arguments['projected_complexity']) ? $arguments['projected_complexity'] : null,
                'projected_hourly_rate'             => isset($arguments['projected_hourly_rate']) ? (float) $arguments['projected_hourly_rate'] : null,
                'metadata'                          => $arguments['metadata'] ?? null,
            ]);

            return ToolResult::success([
                'id'         => $improvement->id,
                'uuid'       => $improvement->uuid,
                'title'      => $improvement->title,
                'category'   => $improvement->category,
                'priority'   => $improvement->priority,
                'status'     => $improvement->status?->value,
                'process_id' => $improvement->process_id,
                'message'    => 'Verbesserung erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen der Verbesserung: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'processes', 'improvements', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => false,
        ];
    }
}
