<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationDimensionDefinition;
use Platform\Organization\Models\OrganizationDimensionValue;
use Platform\Organization\Services\DimensionLinkService;

/**
 * Verknüpft ein Dimensions-Element mit einem Objekt.
 *
 * Architektur: Kostenstellen (cost-centers) dürfen NUR an Entities verknüpft werden.
 * Entities (entities) können an beliebige externe Objekte verknüpft werden.
 *
 * Modi:
 * - multi_percent (cost-centers): Mehrere Links mit Prozent-Verteilung (nur an Entities)
 * - multi (entities): Mehrere Links erlaubt (an beliebige Objekte)
 */
class LinkDimensionTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.dimension_links.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/dimension-links - Verknuepft ein Dimensions-Element mit einem Objekt. EMPFOHLEN bei entity-basierten Dimensionen: entity_id-Parameter (Organization-Entity-ID, sicher). ACHTUNG fuer LLMs: dim_value_id != entity_id — niemals dim_value_id raten oder aus Reverse-Query uebernehmen, immer entity_id-Shortcut nutzen. WICHTIG: cost-centers nur an Entities erlaubt (context_type=organization_entity).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'dimension' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Dimensions-Key (z.B. "cost-centers", "entity", "vsm-system", "vsm-function", "cost-center").',
                ],
                'context_type' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Vollstaendiger Model-Klassenname oder Morph-Alias des Ziel-Objekts.',
                ],
                'context_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID des Ziel-Objekts.',
                ],
                'entity_id' => [
                    'type' => 'integer',
                    'description' => 'EMPFOHLEN (entity-basierte Dimensionen wie entity, cost-driver): Organization-Entity-ID. Wird automatisch zur dim_value_id aufgeloest. Sicherer als dimension_item_id, weil dim_value_id != entity_id und LLMs sich sonst leicht vertun.',
                ],
                'dimension_item_id' => [
                    'type' => 'integer',
                    'description' => 'Power-User: dim_value_id direkt. WICHTIG: dies ist NICHT die entity_id. Bei entity-basierten Dimensionen ZWINGEND entity_id-Parameter verwenden — dann ist die Verwechslungs-Falle ausgeschlossen.',
                ],
                'percentage' => [
                    'type' => 'number',
                    'description' => 'Optional: Prozent-Anteil (0-100). Relevant bei multi_percent-Dimensionen (z.B. cost-centers) oder Kostenaufteilung.',
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
            'required' => ['dimension', 'context_type', 'context_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $dimension = $arguments['dimension'] ?? '';
            $contextType = $arguments['context_type'] ?? '';
            $contextId = (int) ($arguments['context_id'] ?? 0);
            $dimensionItemId = (int) ($arguments['dimension_item_id'] ?? 0);
            $entityId = isset($arguments['entity_id']) ? (int) $arguments['entity_id'] : null;

            $cfg = DimensionLinkService::getDimension($dimension);
            if (!$cfg) {
                $available = implode(', ', array_keys(DimensionLinkService::getDimensions()));
                return ToolResult::error('VALIDATION_ERROR', "Unbekannte Dimension '{$dimension}'. Verfügbar: {$available}");
            }

            $def = null;

            // entity_id shortcut: resolve Organization Entity ID → DimensionValue ID
            // Works for any dimension with value_source='entity' (entity, cost-driver, etc.)
            if ($entityId) {
                $def = OrganizationDimensionDefinition::findByKey($dimension);
                if ($def && $def->value_source === 'entity') {
                    $dimValue = OrganizationDimensionValue::where('dimension_definition_id', $def->id)
                        ->where('metadata->source_entity_id', $entityId)
                        ->first();
                    if (!$dimValue) {
                        return ToolResult::error('NOT_FOUND', "Keine DimensionValue für Entity-ID {$entityId} in Dimension '{$dimension}' gefunden.");
                    }
                    $dimensionItemId = $dimValue->id;
                } elseif ($def) {
                    return ToolResult::error('VALIDATION_ERROR', "entity_id-Shortcut ist nur für entity-basierte Dimensionen verfügbar. Nutze dimension_item_id.");
                }
            }

            if (!$contextType || !$contextId || !$dimensionItemId) {
                return ToolResult::error('VALIDATION_ERROR', 'context_type, context_id und dimension_item_id (oder entity_id bei dimension="entity") sind erforderlich.');
            }

            // Enforcement: Kostenstellen dürfen nur an Entities gehängt werden
            if ($dimension === 'cost-centers') {
                $allowedTypes = ['organization_entity', \Platform\Organization\Models\OrganizationEntity::class];
                if (!in_array($contextType, $allowedTypes, true)) {
                    return ToolResult::error('VALIDATION_ERROR', "Kostenstellen können nur an Organisationseinheiten (Entities) verknüpft werden. Nutze dimension='entities' um externe Objekte mit Entities zu verknüpfen, dann Kostenstellen an die Entity.");
                }
            }

            // Prüfe ob das Dimensions-Element existiert
            if (isset($cfg['model'])) {
                // Legacy dimension (cost-centers)
                $item = $cfg['model']::find($dimensionItemId);
            } else {
                // Generic dimension (entity, vsm-system, etc.)
                $item = OrganizationDimensionValue::where('id', $dimensionItemId)
                    ->where('dimension_definition_id', $cfg['definition_id'] ?? 0)
                    ->first();
            }
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

            $mode = $cfg['mode'] ?? 'multi';
            $label = $cfg['label'] ?? ucfirst($dimension);
            $modeInfo = $mode === 'single'
                ? ' (single-Modus: vorheriger Link wurde ersetzt)'
                : '';

            // Resolved entity ausweisen — LLM sieht eindeutig, welche Entity
            // wirklich verlinkt wurde (entity_id ist die natuerliche Referenz).
            $resolvedEntityId = null;
            $resolvedEntityName = null;
            if ($def && $def->value_source === 'entity' && $item instanceof OrganizationDimensionValue) {
                $meta = $item->metadata;
                if (is_array($meta) && isset($meta['source_entity_id'])) {
                    $resolvedEntityId = (int) $meta['source_entity_id'];
                    $entity = \Platform\Organization\Models\OrganizationEntity::find($resolvedEntityId);
                    $resolvedEntityName = $entity?->name;
                }
            }

            $response = [
                'dimension' => $dimension,
                'dimension_item_id' => $dimensionItemId,
                'dimension_item_name' => $item->name,
                'context_type' => $contextType,
                'context_id' => $contextId,
                'message' => "{$label}-Link erfolgreich erstellt{$modeInfo}.",
            ];

            if ($resolvedEntityId !== null) {
                $response['resolved_entity_id'] = $resolvedEntityId;
                $response['resolved_entity_name'] = $resolvedEntityName;
                $response['note'] = 'resolved_entity_id ist die Organization-Entity-ID — fuer Folge-Operationen verwenden, nicht dimension_item_id.';
            }

            return ToolResult::success($response);
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
