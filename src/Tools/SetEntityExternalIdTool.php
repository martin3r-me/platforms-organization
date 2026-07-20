<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationEntity;

/**
 * Setzt/aktualisiert eine Fremd-ID an einer Entity (Kostenstelle, DATEV,
 * Buchungskonto, Kreditor, …). Upsert je (Entity, System).
 */
class SetEntityExternalIdTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.entity_external_ids.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/entity-external-ids - Setzt eine Fremd-ID an einer Entity (upsert je System). system=z.B. "kostenstelle", "datev", "buchungskonto", "kreditor". Die Kostenstelle einer Organisationseinheit setzt du mit system="kostenstelle".';
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
                    'description' => 'ERFORDERLICH: Namespace der Fremd-ID (z.B. "kostenstelle", "datev", "buchungskonto", "kreditor").',
                ],
                'value' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Der Bezeichner im Fremdsystem (z.B. "KST-4200", "10001").',
                ],
                'label' => [
                    'type' => 'string',
                    'description' => 'Optional: menschenlesbares Label (z.B. "Kreditor Hauptbank").',
                ],
            ],
            'required' => ['entity_id', 'system', 'value'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $entityId = (int) ($arguments['entity_id'] ?? 0);
            $system = trim((string) ($arguments['system'] ?? ''));
            $value = trim((string) ($arguments['value'] ?? ''));
            $label = isset($arguments['label']) ? trim((string) $arguments['label']) : null;

            if (!$entityId || $system === '' || $value === '') {
                return ToolResult::error('VALIDATION_ERROR', 'entity_id, system und value sind erforderlich.');
            }

            $entity = OrganizationEntity::find($entityId);
            if (!$entity) {
                return ToolResult::error('NOT_FOUND', "Entity {$entityId} nicht gefunden.");
            }

            $entity->setExternalId($system, $value, $label ?: null);

            return ToolResult::success([
                'entity_id' => $entity->id,
                'entity_name' => $entity->name,
                'system' => $system,
                'value' => $value,
                'message' => "Fremd-ID '{$system}' = '{$value}' gesetzt.",
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
