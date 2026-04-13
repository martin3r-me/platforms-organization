<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationProcessStepInterlink;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class UpdateProcessStepInterlinkTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_step_interlinks.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/process-step-interlinks/{id} - Aktualisiert eine Step-Interlink-Zuordnung.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'                     => ['type' => 'integer'],
                'process_step_interlink_id'   => ['type' => 'integer', 'description' => 'ERFORDERLICH.'],
                'interlink_id'                => ['type' => 'integer'],
                'role'                        => ['type' => 'string'],
                'metadata'                    => ['type' => 'object'],
            ],
            'required' => ['process_step_interlink_id'],
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
                'process_step_interlink_id',
                OrganizationProcessStepInterlink::class,
                'NOT_FOUND',
                'Step-Interlink-Zuordnung nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationProcessStepInterlink $stepInterlink */
            $stepInterlink = $found['model'];
            if ((int) $stepInterlink->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Step-Interlink-Zuordnung gehört nicht zum Root/Elterteam.');
            }

            $update = [];
            if (array_key_exists('interlink_id', $arguments)) {
                $val = (int) $arguments['interlink_id'];
                if ($val <= 0) {
                    return ToolResult::error('VALIDATION_ERROR', 'interlink_id muss eine gültige ID sein.');
                }
                $update['interlink_id'] = $val;
            }
            if (array_key_exists('role', $arguments)) {
                $val = trim((string) ($arguments['role'] ?? ''));
                if ($val === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'role darf nicht leer sein.');
                }
                $update['role'] = $val;
            }
            if (array_key_exists('metadata', $arguments)) {
                $update['metadata'] = $arguments['metadata'];
            }

            if (! empty($update)) {
                $stepInterlink->update($update);
            }
            $stepInterlink->refresh();

            return ToolResult::success([
                'id'              => $stepInterlink->id,
                'uuid'            => $stepInterlink->uuid,
                'process_step_id' => $stepInterlink->process_step_id,
                'interlink_id'    => $stepInterlink->interlink_id,
                'role'            => $stepInterlink->role,
                'team_id'         => $stepInterlink->team_id,
                'message'         => 'Step-Interlink-Zuordnung erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren der Step-Interlink-Zuordnung: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'process_step_interlinks', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
