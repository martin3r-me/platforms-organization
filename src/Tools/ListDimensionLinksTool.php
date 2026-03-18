<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Services\DimensionLinkService;

/**
 * Listet alle verknüpften Dimensions-Einträge für ein beliebiges Objekt.
 *
 * Beispiel: "Welche Kostenstellen sind dem Projekt X zugeordnet?"
 * → organization.dimension_links.GET(dimension="cost-centers", context_type="App\\Models\\Project", context_id=42)
 */
class ListDimensionLinksTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.dimension_links.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/dimension-links - Zwei Modi: (1) Forward: Welche Dimensions-Einträge hängen an Objekt X? → context_type + context_id angeben. (2) Reverse: Was hängt alles an Dimensions-Element Y? → dimension_item_id angeben. Verfügbare Dimensionen: cost-centers, customers, persons.';
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
                    'description' => 'Forward-Modus: Model-Klassenname oder Morph-Alias des Ziel-Objekts.',
                ],
                'context_id' => [
                    'type' => 'integer',
                    'description' => 'Forward-Modus: ID des Ziel-Objekts.',
                ],
                'dimension_item_id' => [
                    'type' => 'integer',
                    'description' => 'Reverse-Modus: ID des Dimensions-Elements. Zeigt alle verknüpften Objekte gruppiert nach Typ.',
                ],
            ],
            'required' => ['dimension'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $dimension = $arguments['dimension'] ?? '';
            $dimensionItemId = isset($arguments['dimension_item_id']) ? (int) $arguments['dimension_item_id'] : null;
            $contextType = $arguments['context_type'] ?? '';
            $contextId = (int) ($arguments['context_id'] ?? 0);

            $cfg = DimensionLinkService::getDimension($dimension);
            if (!$cfg) {
                $available = implode(', ', array_keys(DimensionLinkService::getDimensions()));
                return ToolResult::error('VALIDATION_ERROR', "Unbekannte Dimension '{$dimension}'. Verfügbar: {$available}");
            }

            $service = new DimensionLinkService();

            // Reverse-Modus: "Was hängt alles an Kunde 5?"
            if ($dimensionItemId) {
                $groups = $service->getLinkedContexts($dimension, $dimensionItemId);

                return ToolResult::success([
                    'dimension' => $dimension,
                    'dimension_item_id' => $dimensionItemId,
                    'mode' => 'reverse',
                    'linked_contexts' => $groups->values()->toArray(),
                    'total_count' => $groups->sum('count'),
                ]);
            }

            // Forward-Modus: "Welche Kostenstellen hängen an Projekt 42?"
            if (!$contextType || !$contextId) {
                return ToolResult::error('VALIDATION_ERROR', 'Entweder dimension_item_id (Reverse) oder context_type + context_id (Forward) angeben.');
            }

            $items = $service->getLinked($dimension, $contextType, $contextId);

            return ToolResult::success([
                'dimension' => $dimension,
                'mode' => $cfg['mode'],
                'context_type' => $contextType,
                'context_id' => $contextId,
                'data' => $items->values()->toArray(),
                'count' => $items->count(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'dimensions', 'links', 'lookup'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
