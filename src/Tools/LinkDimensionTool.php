<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationDimensionDefinition;
use Platform\Organization\Models\OrganizationDimensionValue;
use Platform\Organization\Models\OrganizationEntityExternalId;
use Platform\Organization\Services\DimensionLinkService;

/**
 * Verknüpft ein Dimensions-Element mit einem Objekt.
 *
 * Architektur: Verlinkt wird immer gegen eine Entity (entity-Dimension).
 * Eine Kostenstelle ist kein eigenes Link-Ziel, sondern nur eine Fremd-ID-
 * Adresse einer Entity: "hänge X an Kostenstelle KST-4200" wird über
 * cost_center/external_* zur zugehörigen Entity aufgelöst und dann verlinkt.
 */
class LinkDimensionTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.dimension_links.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/dimension-links - Verknuepft ein Objekt mit einer Entity (dimension="entity"). EMPFOHLEN: entity_id-Parameter (Organization-Entity-ID, sicher). Alternativ per Fremd-ID adressieren: cost_center="KST-4200" ODER external_system+external_value (z.B. datev/10001) — wird zur Entity aufgeloest, dimension wird dann automatisch "entity". ACHTUNG fuer LLMs: dim_value_id != entity_id — niemals dim_value_id raten, immer entity_id oder cost_center nutzen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'dimension' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Dimensions-Key (z.B. "entity", "vsm-system", "vsm-function"). Bei cost_center/external_* wird automatisch "entity" verwendet.',
                ],
                'cost_center' => [
                    'type' => 'string',
                    'description' => 'Optional: Kostenstellen-Kürzel (z.B. "KST-4200"). Wird zur zugehörigen Entity aufgelöst; dimension wird automatisch "entity".',
                ],
                'external_system' => [
                    'type' => 'string',
                    'description' => 'Optional (mit external_value): Fremd-System (z.B. "datev", "kreditor"). Adressiert die Entity per Fremd-ID.',
                ],
                'external_value' => [
                    'type' => 'string',
                    'description' => 'Optional (mit external_system): Wert der Fremd-ID (z.B. "10001").',
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

            // Fremd-ID-Resolver: "hänge X an Kostenstelle KST-4200" (oder DATEV/Kreditor …).
            // Die Fremd-ID adressiert eine Entity → dimension wird "entity", entity_id gesetzt.
            $externalSystem = null;
            $externalValue = null;
            if (!empty($arguments['cost_center'])) {
                $externalSystem = OrganizationEntityExternalId::SYSTEM_COST_CENTER;
                $externalValue = trim((string) $arguments['cost_center']);
            } elseif (!empty($arguments['external_system']) && !empty($arguments['external_value'])) {
                $externalSystem = trim((string) $arguments['external_system']);
                $externalValue = trim((string) $arguments['external_value']);
            }
            if ($externalSystem !== null) {
                $teamId = $context->team?->id ?? auth()->user()?->currentTeam?->id;
                $resolved = OrganizationEntityExternalId::resolveEntity($externalSystem, $externalValue, $teamId);
                if (!$resolved) {
                    return ToolResult::error('NOT_FOUND', "Keine Entity mit {$externalSystem}='{$externalValue}' gefunden.");
                }
                $dimension = 'entity';
                $entityId = $resolved->id;
            }

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
                return ToolResult::error('VALIDATION_ERROR', 'context_type, context_id und dimension_item_id (oder entity_id/cost_center bei dimension="entity") sind erforderlich.');
            }

            // Prüfe ob das Dimensions-Element existiert (generische Dimension)
            $item = OrganizationDimensionValue::where('id', $dimensionItemId)
                ->where('dimension_definition_id', $cfg['definition_id'] ?? 0)
                ->first();
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
