<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Services\DimensionLinkService;

/**
 * Verknüpft ein Dimensions-Element (Kostenstelle, Kunde, Person) mit einem beliebigen Objekt.
 *
 * Beispiel: "Ordne Kostenstelle 5 dem Projekt 42 zu mit 60%"
 * → organization.dimension_links.POST(dimension="cost-centers", context_type="...", context_id=42, dimension_item_id=5, percentage=60)
 *
 * Modi:
 * - single (customers): Ersetzt automatisch den vorherigen Link
 * - multi (persons): Mehrere Links erlaubt
 * - multi_percent (cost-centers): Mehrere Links mit Prozent-Verteilung
 */
class LinkDimensionTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.dimension_links.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/dimension-links - Verknüpft ein Dimensions-Element mit einem Objekt. Bei customers (single) wird der vorherige Link automatisch ersetzt. Bei cost-centers (multi_percent) kann percentage angegeben werden. Das Ziel-Objekt muss den entsprechenden Trait haben.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'dimension' => [
                    'type' => 'string',
                    'enum' => ['cost-centers', 'entities'],
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
                    'description' => 'ERFORDERLICH: ID des Dimensions-Elements (z.B. customer_id, cost_center_id, person_id). Nutze die jeweiligen GET-Tools um IDs zu ermitteln.',
                ],
                'percentage' => [
                    'type' => 'number',
                    'description' => 'Optional: Prozent-Anteil (0-100). Nur relevant bei cost-centers (multi_percent Modus).',
                ],
                'is_primary' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Als primären Link markieren. Default: false.',
                ],
                'start_date' => [
                    'type' => 'string',
                    'description' => 'Optional: Startdatum (YYYY-MM-DD).',
                ],
                'end_date' => [
                    'type' => 'string',
                    'description' => 'Optional: Enddatum (YYYY-MM-DD).',
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

            // Prüfe ob das Dimensions-Element existiert
            $dimensionModel = $cfg['model'];
            $item = $dimensionModel::find($dimensionItemId);
            if (!$item) {
                return ToolResult::error('NOT_FOUND', "Dimensions-Element mit ID {$dimensionItemId} nicht gefunden.");
            }

            $meta = [
                'percentage' => isset($arguments['percentage']) ? round((float) $arguments['percentage'], 2) : null,
                'is_primary' => (bool) ($arguments['is_primary'] ?? false),
                'start_date' => $arguments['start_date'] ?? null,
                'end_date' => $arguments['end_date'] ?? null,
                'team_id' => $context->team?->id ?? auth()->user()?->currentTeam?->id,
                'created_by_user_id' => $context->user?->id,
            ];

            $service = new DimensionLinkService();
            $created = $service->link($dimension, $contextType, $contextId, $dimensionItemId, $meta);

            if (!$created) {
                return ToolResult::error('DUPLICATE', 'Dieser Link existiert bereits.');
            }

            $modeInfo = $cfg['mode'] === 'single'
                ? ' (single-Modus: vorheriger Link wurde ersetzt)'
                : '';

            return ToolResult::success([
                'dimension' => $dimension,
                'dimension_item_id' => $dimensionItemId,
                'dimension_item_name' => $item->name,
                'context_type' => $contextType,
                'context_id' => $contextId,
                'message' => "{$cfg['label']}-Link erfolgreich erstellt{$modeInfo}.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'dimensions', 'links', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
