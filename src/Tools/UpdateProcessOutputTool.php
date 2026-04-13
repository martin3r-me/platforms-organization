<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationProcessOutput;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class UpdateProcessOutputTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_outputs.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/process-outputs/{id} - Aktualisiert einen Prozess-Output.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'            => ['type' => 'integer'],
                'process_output_id'  => ['type' => 'integer', 'description' => 'ERFORDERLICH.'],
                'label'              => ['type' => 'string'],
                'description'        => ['type' => 'string', 'description' => '"" zum Leeren.'],
                'output_type'        => ['type' => 'string'],
                'entity_type_id'     => ['type' => 'integer', 'description' => '0 oder null zum Leeren.'],
                'entity_id'          => ['type' => 'integer', 'description' => '0 oder null zum Leeren.'],
                'target_process_id'  => ['type' => 'integer', 'description' => '0 oder null zum Leeren.'],
                'interlink_id'       => ['type' => 'integer', 'description' => '0 oder null zum Leeren.'],
                'metadata'           => ['type' => 'object'],
            ],
            'required' => ['process_output_id'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $found = $this->validateAndFindModel(
                $arguments,
                $context,
                'process_output_id',
                OrganizationProcessOutput::class,
                'NOT_FOUND',
                'Prozess-Output nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationProcessOutput $output */
            $output = $found['model'];
            if ((int) $output->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Prozess-Output gehört nicht zum Root/Elterteam.');
            }

            $update = [];
            if (array_key_exists('label', $arguments)) {
                $val = trim((string) ($arguments['label'] ?? ''));
                if ($val === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'label darf nicht leer sein.');
                }
                $update['label'] = $val;
            }
            if (array_key_exists('description', $arguments)) {
                $val = (string) ($arguments['description'] ?? '');
                $update['description'] = $val === '' ? null : $val;
            }
            if (array_key_exists('output_type', $arguments)) {
                $update['output_type'] = (string) $arguments['output_type'];
            }
            foreach (['entity_type_id', 'entity_id', 'target_process_id', 'interlink_id'] as $field) {
                if (array_key_exists($field, $arguments)) {
                    $val = $arguments[$field];
                    $update[$field] = (! empty($val) && (int) $val > 0) ? (int) $val : null;
                }
            }
            if (array_key_exists('metadata', $arguments)) {
                $update['metadata'] = $arguments['metadata'];
            }

            if (! empty($update)) {
                $output->update($update);
            }
            $output->refresh();

            return ToolResult::success([
                'id'          => $output->id,
                'uuid'        => $output->uuid,
                'process_id'  => $output->process_id,
                'label'       => $output->label,
                'output_type' => $output->output_type,
                'team_id'     => $output->team_id,
                'message'     => 'Prozess-Output erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Prozess-Outputs: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'process_outputs', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
