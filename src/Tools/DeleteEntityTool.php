<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class DeleteEntityTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.entities.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/entities/{id} - Löscht eine Entity (soft delete). Bevorzuge is_active=false wenn die Entity noch verlinkt ist.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (wird auf Root/Elterteam aufgelöst). Default: Team aus Kontext.',
                ],
                'entity_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID der Entity.',
                ],
            ],
            'required' => ['entity_id'],
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
                'entity_id',
                OrganizationEntity::class,
                'NOT_FOUND',
                'Entity nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            $entity = $found['model'];
            if ((int) $entity->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Entity gehört nicht zum Root/Elterteam des angegebenen Teams.');
            }

            // Safety: Children vorhanden?
            if ($entity->children()->exists()) {
                return ToolResult::error('VALIDATION_ERROR', 'Entity hat untergeordnete Entities und kann nicht gelöscht werden. Verschiebe oder lösche zuerst die Children.');
            }

            // Safety: Contexts (Module Entities) vorhanden?
            if ($entity->contexts()->exists()) {
                return ToolResult::error('VALIDATION_ERROR', 'Entity hat verknüpfte Module-Entities. Setze stattdessen is_active=false oder entferne zuerst die Verknüpfungen.');
            }

            $entity->delete();

            return ToolResult::success([
                'id' => $entity->id,
                'message' => 'Entity gelöscht (soft delete).',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen der Entity: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'entities', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
