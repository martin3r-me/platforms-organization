<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Services\PerspectiveService;

/**
 * Wechselt die aktive Perspektive (= Carrier-Entity) der Session.
 */
class SwitchPerspectiveTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.perspectives.switch';
    }

    public function getDescription(): string
    {
        return 'POST /organization/perspectives/switch - Wechselt die aktive Perspektive (Carrier-Entity) fuer die aktuelle Session. Beeinflusst alle nachfolgenden Organization-Tool-Aufrufe, die VSM-Daten zurueckliefern. Nutze organization.perspectives.GET um waehlbare Carrier zu sehen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'perspective_entity_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Carrier-Entity, zu deren Sicht gewechselt werden soll.',
                ],
            ],
            'required' => ['perspective_entity_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $context->team?->id;
            if (!$teamId) {
                return ToolResult::error('MISSING_TEAM', 'Kein Team im Kontext.');
            }
            $entityId = (int) ($arguments['perspective_entity_id'] ?? 0);

            if (!$entityId) {
                return ToolResult::error('VALIDATION_ERROR', 'perspective_entity_id ist erforderlich.');
            }

            $entity = PerspectiveService::setActiveEntity($entityId, $teamId);

            if (!$entity) {
                return ToolResult::error('NOT_FOUND', "Carrier-Entity {$entityId} nicht gefunden, nicht im Team oder kein Carrier-Type.");
            }

            return ToolResult::success([
                'message' => "Perspektive gewechselt zu: {$entity->name}",
                'perspective' => [
                    'entity_id' => $entity->id,
                    'uuid' => $entity->uuid,
                    'name' => $entity->name,
                    'code' => $entity->code,
                    'type' => $entity->type?->name,
                    'is_root' => $entity->parent_entity_id === null,
                ],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Perspektive-Wechsel: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'write',
            'tags' => ['organization', 'perspectives', 'session', 'carrier'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
