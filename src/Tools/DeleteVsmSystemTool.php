<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationVsmSystem;

class DeleteVsmSystemTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.vsm_systems.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/vsm-systems/{id} - Löscht ein VSM-System. Wenn Entities zugeordnet sind, setze stattdessen is_active=false.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'vsm_system_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID des VSM-Systems.',
                ],
            ],
            'required' => ['vsm_system_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $id = (int) ($arguments['vsm_system_id'] ?? 0);
            if (!$id) {
                return ToolResult::error('VALIDATION_ERROR', 'vsm_system_id ist erforderlich.');
            }

            $system = OrganizationVsmSystem::find($id);
            if (!$system) {
                return ToolResult::error('NOT_FOUND', 'VSM-System nicht gefunden.');
            }

            if ($system->entities()->exists()) {
                return ToolResult::error('VALIDATION_ERROR', 'VSM-System hat zugeordnete Entities und kann nicht gelöscht werden. Setze stattdessen is_active=false.');
            }

            $system->delete();

            return ToolResult::success([
                'id' => $id,
                'message' => 'VSM-System erfolgreich gelöscht.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen des VSM-Systems: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'vsm', 'systems', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
