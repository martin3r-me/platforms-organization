<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationDimensionDefinition;
use Platform\Organization\Models\OrganizationDimensionValue;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Services\DimensionLinkService;

/**
 * Entfernt die Verknuepfung eines Dimensions-Elements von einem Objekt.
 *
 * Architektur: Kostenstellen (cost-centers) existieren nur an Entities.
 * Entities (entities) koennen an beliebige externe Objekte verknuepft sein.
 *
 * WICHTIG fuer LLMs:
 *  - Empfohlener Pfad bei entity-basierten Dimensionen: entity_id
 *    (Organization-Entity-ID). Wird automatisch zu dim_value_id aufgeloest.
 *  - dimension_item_id (raw dim_value_id) ist Power-User-Pfad und gefaehrlich,
 *    weil dim_value_id != entity_id ist und Verwechslungs-Fallen entstehen.
 */
class UnlinkDimensionTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.dimension_links.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/dimension-links - Entfernt die Verknuepfung eines Dimensions-Elements von einem Objekt. EMPFOHLEN bei entity-basierten Dimensionen: entity_id-Parameter nutzen. ACHTUNG: dim_value_id != entity_id — niemals raten, immer entity_id-Shortcut verwenden.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'dimension' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Dimensions-Key (z.B. entity, cost-centers).',
                ],
                'context_type' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Vollstaendiger Model-Klassenname oder Morph-Alias des Ziel-Objekts (z.B. project, canvas, organization_process).',
                ],
                'context_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID des Ziel-Objekts (z.B. Project-ID, Canvas-ID).',
                ],
                'entity_id' => [
                    'type' => 'integer',
                    'description' => 'EMPFOHLEN (entity-basierte Dimensionen): Organization-Entity-ID. Wird automatisch zur dim_value_id aufgeloest. Sicherer als dimension_item_id — bei dim_value_id != entity_id leicht zu verwechseln.',
                ],
                'dimension_item_id' => [
                    'type' => 'integer',
                    'description' => 'Power-User: dim_value_id direkt. WICHTIG: dies ist NICHT die entity_id. Bei entity-basierten Dimensionen ZWINGEND entity_id-Parameter verwenden, dann ist diese Falle ausgeschlossen.',
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
            $entityIdInput = isset($arguments['entity_id']) ? (int) $arguments['entity_id'] : null;

            $cfg = DimensionLinkService::getDimension($dimension);
            if (!$cfg) {
                $available = implode(', ', array_keys(DimensionLinkService::getDimensions()));
                return ToolResult::error('VALIDATION_ERROR', "Unbekannte Dimension '{$dimension}'. Verfuegbar: {$available}");
            }

            // entity_id-Shortcut: aufloesen zu dim_value_id (LLM-sicher).
            if ($entityIdInput && !$dimensionItemId) {
                $def = OrganizationDimensionDefinition::findByKey($dimension);
                if ($def && $def->value_source === 'entity') {
                    $dimValue = OrganizationDimensionValue::where('dimension_definition_id', $def->id)
                        ->where('metadata->source_entity_id', $entityIdInput)
                        ->first();
                    if (!$dimValue) {
                        return ToolResult::error('NOT_FOUND', "Keine DimensionValue fuer Entity-ID {$entityIdInput} in Dimension '{$dimension}' gefunden.");
                    }
                    $dimensionItemId = $dimValue->id;
                } elseif ($def) {
                    return ToolResult::error('VALIDATION_ERROR', "entity_id-Shortcut ist nur fuer entity-basierte Dimensionen verfuegbar. Nutze dimension_item_id.");
                }
            }

            if (!$contextType || !$contextId || !$dimensionItemId) {
                return ToolResult::error('VALIDATION_ERROR', 'context_type, context_id und entity_id ODER dimension_item_id sind erforderlich. Bei entity-Dimensionen: entity_id verwenden (sicher).');
            }

            // Enforcement: Kostenstellen duerfen nur an Entities gehaengt werden
            if ($dimension === 'cost-centers') {
                $allowedTypes = ['organization_entity', \Platform\Organization\Models\OrganizationEntity::class];
                if (!in_array($contextType, $allowedTypes, true)) {
                    return ToolResult::error('VALIDATION_ERROR', "Kostenstellen-Links koennen nur an Organisationseinheiten (Entities) existieren.");
                }
            }

            $service = new DimensionLinkService();
            $deleted = $service->unlink($dimension, $contextType, $contextId, $dimensionItemId);

            if (!$deleted) {
                return ToolResult::error('NOT_FOUND', 'Link nicht gefunden oder bereits entfernt.');
            }

            // Resolved entity ausweisen — LLM sieht eindeutig, was tatsaechlich
            // entfernt wurde.
            $resolvedEntityId = null;
            $resolvedEntityName = null;
            $def = OrganizationDimensionDefinition::findByKey($dimension);
            if ($def && $def->value_source === 'entity') {
                $dimValue = OrganizationDimensionValue::find($dimensionItemId);
                if ($dimValue) {
                    $meta = $dimValue->metadata;
                    if (is_array($meta) && isset($meta['source_entity_id'])) {
                        $resolvedEntityId = (int) $meta['source_entity_id'];
                        $entity = OrganizationEntity::find($resolvedEntityId);
                        $resolvedEntityName = $entity?->name;
                    }
                }
            }

            $response = [
                'dimension' => $dimension,
                'dimension_item_id' => $dimensionItemId,
                'context_type' => $contextType,
                'context_id' => $contextId,
                'message' => "{$cfg['label']}-Link erfolgreich entfernt.",
            ];

            if ($resolvedEntityId !== null) {
                $response['resolved_entity_id'] = $resolvedEntityId;
                $response['resolved_entity_name'] = $resolvedEntityName;
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
            'tags' => ['organization', 'dimensions', 'links', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
