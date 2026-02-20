<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationEntityTypeGroup;

class DeleteEntityTypeGroupTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;

    public function getName(): string
    {
        return 'organization.entity_type_groups.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/entity-type-groups/{id} - Löscht eine Entity Type Group. Verweigert wenn die Gruppe noch Entity Types enthält.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'entity_type_group_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Entity Type Group (ERFORDERLICH). Nutze organization.entity_type_groups.GET.',
                ],
            ],
            'required' => ['entity_type_group_id'],
        ]);
    }

    protected function getAccessAction(): string
    {
        return 'delete';
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $found = $this->validateAndFindModel(
                $arguments,
                $context,
                'entity_type_group_id',
                OrganizationEntityTypeGroup::class,
                'NOT_FOUND',
                'Entity Type Group nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationEntityTypeGroup $group */
            $group = $found['model'];

            // Safety: don't delete if group still has entity types
            if ($group->entityTypes()->exists()) {
                return ToolResult::error('VALIDATION_ERROR', 'Entity Type Group enthält noch Entity Types und kann nicht gelöscht werden. Entferne oder verschiebe zuerst alle Entity Types.');
            }

            $group->delete();

            return ToolResult::success([
                'id' => $group->id,
                'message' => 'Entity Type Group erfolgreich gelöscht.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen der Entity Type Group: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'entity_type_groups', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
