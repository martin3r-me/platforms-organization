<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationProcessGroup;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class DeleteProcessGroupTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_groups.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/process-groups/{id} - Löscht eine Prozess-Gruppe (soft delete). Prozesse werden nicht gelöscht, nur die Gruppenzuordnung entfernt.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: Team aus Kontext.',
                ],
                'process_group_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID der Prozess-Gruppe.',
                ],
            ],
            'required' => ['process_group_id'],
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

            $found = $this->validateAndFindModel(
                $arguments, $context, 'process_group_id',
                OrganizationProcessGroup::class, 'NOT_FOUND', 'Prozess-Gruppe nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            $group = $found['model'];

            if ((int) $group->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Prozess-Gruppe gehört nicht zum Team.');
            }

            $group->delete();

            return ToolResult::success([
                'id' => $group->id,
                'message' => 'Prozess-Gruppe gelöscht (soft delete).',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen der Prozess-Gruppe: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'processes', 'groups', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'delete',
            'idempotent' => false,
        ];
    }
}
