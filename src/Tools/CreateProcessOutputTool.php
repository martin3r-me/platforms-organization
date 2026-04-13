<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationProcessOutput;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class CreateProcessOutputTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_outputs.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/process-outputs - Erstellt einen Prozess-Output. output_type: entity_created | entity_modified | triggers_process | interlink | deliverable.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'           => ['type' => 'integer'],
                'process_id'        => ['type' => 'integer', 'description' => 'ERFORDERLICH: Zugehöriger Prozess.'],
                'label'             => ['type' => 'string', 'description' => 'ERFORDERLICH: Bezeichnung.'],
                'description'       => ['type' => 'string'],
                'output_type'       => ['type' => 'string', 'description' => 'ERFORDERLICH: entity_created | entity_modified | triggers_process | interlink | deliverable.'],
                'entity_type_id'    => ['type' => 'integer', 'description' => 'Optional: Für entity_created/entity_modified.'],
                'entity_id'         => ['type' => 'integer', 'description' => 'Optional: Konkrete Entity.'],
                'target_process_id' => ['type' => 'integer', 'description' => 'Optional: Für triggers_process.'],
                'interlink_id'      => ['type' => 'integer', 'description' => 'Optional: Für output_type=interlink.'],
                'metadata'          => ['type' => 'object'],
            ],
            'required' => ['process_id', 'label', 'output_type'],
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

            $processId  = (int) ($arguments['process_id'] ?? 0);
            $label      = trim((string) ($arguments['label'] ?? ''));
            $outputType = trim((string) ($arguments['output_type'] ?? ''));

            if ($processId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'process_id ist erforderlich.');
            }
            if ($label === '') {
                return ToolResult::error('VALIDATION_ERROR', 'label ist erforderlich.');
            }
            if ($outputType === '') {
                return ToolResult::error('VALIDATION_ERROR', 'output_type ist erforderlich.');
            }

            $output = OrganizationProcessOutput::create([
                'team_id'           => $rootTeamId,
                'user_id'           => $context->user?->id,
                'process_id'        => $processId,
                'label'             => $label,
                'description'       => ($arguments['description'] ?? null) ?: null,
                'output_type'       => $outputType,
                'entity_type_id'    => ! empty($arguments['entity_type_id']) ? (int) $arguments['entity_type_id'] : null,
                'entity_id'         => ! empty($arguments['entity_id']) ? (int) $arguments['entity_id'] : null,
                'target_process_id' => ! empty($arguments['target_process_id']) ? (int) $arguments['target_process_id'] : null,
                'interlink_id'      => ! empty($arguments['interlink_id']) ? (int) $arguments['interlink_id'] : null,
                'metadata'          => $arguments['metadata'] ?? null,
            ]);

            return ToolResult::success([
                'id'          => $output->id,
                'uuid'        => $output->uuid,
                'process_id'  => $output->process_id,
                'label'       => $output->label,
                'output_type' => $output->output_type,
                'team_id'     => $output->team_id,
                'message'     => 'Prozess-Output erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Prozess-Outputs: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'process_outputs', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => false,
        ];
    }
}
