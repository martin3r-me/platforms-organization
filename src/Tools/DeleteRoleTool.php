<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationRole;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class DeleteRoleTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.roles.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/roles/{id} - Löscht eine Rolle (soft delete). Wenn die Rolle Zuweisungen hat, bitte status=archived setzen statt löschen.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => ['type' => 'integer'],
                'role_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH.'],
            ],
            'required' => ['role_id'],
        ]);
    }

    protected function getAccessAction(): string
    {
        return 'delete';
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
                'role_id',
                OrganizationRole::class,
                'NOT_FOUND',
                'Rolle nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationRole $role */
            $role = $found['model'];
            if ((int) $role->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Rolle gehört nicht zum Root/Elterteam.');
            }

            if ($role->assignments()->exists()) {
                return ToolResult::error('VALIDATION_ERROR', 'Rolle hat Zuweisungen und kann nicht gelöscht werden. Setze stattdessen status=archived.');
            }

            $role->delete();

            return ToolResult::success([
                'id'      => $role->id,
                'message' => 'Rolle gelöscht (soft delete).',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen der Rolle: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'roles', 'delete'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
