<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
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
                'metadata'           => ['type' => 'object'],
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
            if (! in_array($status, ['identified', 'planned', 'in_progress', 'on_hold', 'completed', 'under_observation', 'validated', 'failed', 'rejected'])) {
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
                'before_snapshot_id' => ! empty($arguments['before_snapshot_id']) ? (int) $arguments['before_snapshot_id'] : null,
                'metadata'           => $arguments['metadata'] ?? null,
            ]);

            return ToolResult::success([
                'id'         => $improvement->id,
                'uuid'       => $improvement->uuid,
                'title'      => $improvement->title,
                'category'   => $improvement->category,
                'priority'   => $improvement->priority,
                'status'     => $improvement->status,
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
