<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Services\DimensionLinkService;

/**
 * Entfernt die Verknüpfung eines Dimensions-Elements von einem Objekt.
 *
 * Beispiel: "Entferne Kostenstelle 5 vom Projekt 42"
 * → organization.dimension_links.DELETE(dimension="cost-centers", context_type="...", context_id=42, dimension_item_id=5)
 */
class UnlinkDimensionTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.dimension_links.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/dimension-links - Entfernt die Verknüpfung eines Dimensions-Elements (Kostenstelle, Kunde, Person) von einem Objekt.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'dimension' => [
                    'type' => 'string',
                    'enum' => ['cost-centers', 'customers', 'persons'],
                    'description' => 'ERFORDERLICH: Dimensions-Key.',
                ],
                'context_type' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Vollständiger Model-Klassenname oder Morph-Alias des Ziel-Objekts.',
                ],
                'context_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID des Ziel-Objekts.',
                ],
                'dimension_item_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID des Dimensions-Elements das entfernt werden soll.',
                ],
            ],
            'required' => ['dimension', 'context_type', 'context_id', 'dimension_item_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $dimension = $arguments['dimension'] ?? '';
            $contextType = $arguments['context_type'] ?? '';
            $contextId = (int) ($arguments['context_id'] ?? 0);
            $dimensionItemId = (int) ($arguments['dimension_item_id'] ?? 0);

            $cfg = DimensionLinkService::getDimension($dimension);
            if (!$cfg) {
                $available = implode(', ', array_keys(DimensionLinkService::getDimensions()));
                return ToolResult::error('VALIDATION_ERROR', "Unbekannte Dimension '{$dimension}'. Verfügbar: {$available}");
            }

            if (!$contextType || !$contextId || !$dimensionItemId) {
                return ToolResult::error('VALIDATION_ERROR', 'context_type, context_id und dimension_item_id sind erforderlich.');
            }

            $service = new DimensionLinkService();
            $deleted = $service->unlink($dimension, $contextType, $contextId, $dimensionItemId);

            if (!$deleted) {
                return ToolResult::error('NOT_FOUND', 'Link nicht gefunden oder bereits entfernt.');
            }

            return ToolResult::success([
                'dimension' => $dimension,
                'dimension_item_id' => $dimensionItemId,
                'context_type' => $contextType,
                'context_id' => $contextId,
                'message' => "{$cfg['label']}-Link erfolgreich entfernt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'dimensions', 'links', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
