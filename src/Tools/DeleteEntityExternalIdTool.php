<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationEntity;

/**
 * Entfernt eine Fremd-ID (z.B. die Kostenstelle) von einer Entity.
 */
class DeleteEntityExternalIdTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.entity_external_ids.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/entity-external-ids - Entfernt eine Fremd-ID von einer Entity (per entity_id + system).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'entity_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID der Entity.',
                ],
                'system' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: System der zu entfernenden Fremd-ID (z.B. "kostenstelle").',
                ],
            ],
            'required' => ['entity_id', 'system'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $entityId = (int) ($arguments['entity_id'] ?? 0);
            $system = trim((string) ($arguments['system'] ?? ''));

            if (!$entityId || $system === '') {
                return ToolResult::error('VALIDATION_ERROR', 'entity_id und system sind erforderlich.');
            }

            $entity = OrganizationEntity::find($entityId);
            if (!$entity) {
                return ToolResult::error('NOT_FOUND', "Entity {$entityId} nicht gefunden.");
            }

            $entity->setExternalId($system, null);

            return ToolResult::success([
                'entity_id' => $entity->id,
                'system' => $system,
                'message' => "Fremd-ID '{$system}' entfernt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'entities', 'external-ids', 'cost-center'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
