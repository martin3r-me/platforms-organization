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
 * Listet alle verknüpften Dimensions-Einträge für ein beliebiges Objekt.
 *
 * Beispiel: "Welche Kostenstellen sind dem Projekt X zugeordnet?"
 * → organization.dimension_links.GET(dimension="cost-centers", context_type="App\\Models\\Project", context_id=42)
 *
 * WICHTIG fuer LLMs:
 *  - dimension_value_id != entity_id. Dim-Values sind interne IDs, Entity-IDs
 *    sind die natuerlichen Referenzen.
 *  - Bei entity-basierten Dimensionen IMMER 'entity_id' aus dem Response lesen
 *    und damit weiterarbeiten — niemals 'id'/'dim_value_id' annehmen.
 *  - Reverse-Modus akzeptiert sowohl 'entity_id' (Shortcut, empfohlen) als
 *    auch 'dimension_item_id' (raw dim_value_id, fuer Power-User).
 */
class ListDimensionLinksTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.dimension_links.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/dimension-links - Zwei Modi: (1) Forward: Welche Dimensions-Eintraege haengen an Objekt X? context_type + context_id angeben. (2) Reverse: Was haengt alles an Dimensions-Element Y? entity_id (empfohlen) oder dimension_item_id angeben. ACHTUNG fuer LLMs: dim_value_id != entity_id. Antwort enthaelt entity_id fuer entity-basierte Dimensionen — IMMER damit weiterarbeiten, nicht mit der internen id/dim_value_id.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'dimension' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Dimensions-Key (z.B. cost-centers, entity). Wird dynamisch aus verfuegbaren Dimensionen validiert.',
                ],
                'context_type' => [
                    'type' => 'string',
                    'description' => 'Forward-Modus: Model-Klassenname oder Morph-Alias des Ziel-Objekts.',
                ],
                'context_id' => [
                    'type' => 'integer',
                    'description' => 'Forward-Modus: ID des Ziel-Objekts.',
                ],
                'entity_id' => [
                    'type' => 'integer',
                    'description' => 'Reverse-Modus EMPFOHLEN (entity-basierte Dimensionen): Organization-Entity-ID. Wird automatisch zur dim_value_id aufgeloest. Sicherer als dimension_item_id, weil dim_value_id != entity_id und LLMs sich sonst leicht vertun.',
                ],
                'dimension_item_id' => [
                    'type' => 'integer',
                    'description' => 'Reverse-Modus (Power-User): dim_value_id direkt. WICHTIG: dies ist NICHT die entity_id. Bei entity-basierten Dimensionen lieber entity_id-Parameter verwenden.',
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
            $entityIdInput = isset($arguments['entity_id']) ? (int) $arguments['entity_id'] : null;
            $contextType = $arguments['context_type'] ?? '';
            $contextId = (int) ($arguments['context_id'] ?? 0);

            $cfg = DimensionLinkService::getDimension($dimension);
            if (!$cfg) {
                $available = implode(', ', array_keys(DimensionLinkService::getDimensions()));
                return ToolResult::error('VALIDATION_ERROR', "Unbekannte Dimension '{$dimension}'. Verfuegbar: {$available}");
            }

            $service = new DimensionLinkService();
            $def = OrganizationDimensionDefinition::findByKey($dimension);
            $isEntityBased = $def && $def->value_source === 'entity';

            // entity_id-Shortcut: aufloesen zu dim_value_id (nur fuer
            // entity-basierte Dimensionen sinnvoll).
            if ($entityIdInput && !$dimensionItemId) {
                if (!$isEntityBased) {
                    return ToolResult::error('VALIDATION_ERROR', "entity_id-Shortcut ist nur fuer entity-basierte Dimensionen verfuegbar (aktuelle: '{$dimension}', value_source='{$def?->value_source}'). Nutze dimension_item_id.");
                }
                $dimValue = OrganizationDimensionValue::where('dimension_definition_id', $def->id)
                    ->where('metadata->source_entity_id', $entityIdInput)
                    ->first();
                if (!$dimValue) {
                    return ToolResult::error('NOT_FOUND', "Keine DimensionValue fuer Entity-ID {$entityIdInput} in Dimension '{$dimension}' gefunden. Pruefe ob die Entity existiert.");
                }
                $dimensionItemId = $dimValue->id;
            }

            // Reverse-Modus: "Was haengt alles an Entity X?"
            if ($dimensionItemId) {
                $groups = $service->getLinkedContexts($dimension, $dimensionItemId);

                // Echo: dim_value -> entity aufloesen, damit der Caller eindeutig
                // weiss, welche Entity wirklich angesprochen ist.
                $resolvedEntityId = null;
                $resolvedEntityName = null;
                $resolvedEntityCode = null;
                if ($isEntityBased) {
                    $dimValue = OrganizationDimensionValue::find($dimensionItemId);
                    if ($dimValue) {
                        $meta = $dimValue->metadata;
                        if (is_array($meta) && isset($meta['source_entity_id'])) {
                            $resolvedEntityId = (int) $meta['source_entity_id'];
                            $entity = OrganizationEntity::find($resolvedEntityId);
                            $resolvedEntityName = $entity?->name;
                            $resolvedEntityCode = $entity?->code;
                        }
                    }
                }

                $response = [
                    'dimension' => $dimension,
                    'dimension_item_id' => $dimensionItemId,
                    'mode' => 'reverse',
                    'linked_contexts' => $groups->values()->toArray(),
                    'total_count' => $groups->sum('count'),
                ];

                if ($isEntityBased) {
                    // Klares Signal fuer LLM: welche Entity wurde tatsaechlich
                    // angesprochen. Lies das, nicht die dim_value_id, wenn du
                    // Folge-Operationen planst.
                    $response['resolved_entity_id'] = $resolvedEntityId;
                    $response['resolved_entity_name'] = $resolvedEntityName;
                    $response['resolved_entity_code'] = $resolvedEntityCode;
                    $response['note'] = 'Bei entity-basierten Dimensionen: dimension_item_id ist die interne dim_value_id. resolved_entity_id ist die Organization-Entity-ID — diese fuer DELETE/POST verwenden.';
                }

                return ToolResult::success($response);
            }

            // Forward-Modus: "Welche Entities haengen an Projekt 42?"
            if (!$contextType || !$contextId) {
                return ToolResult::error('VALIDATION_ERROR', 'Entweder entity_id/dimension_item_id (Reverse) oder context_type + context_id (Forward) angeben.');
            }

            $items = $service->getLinked($dimension, $contextType, $contextId);

            $response = [
                'dimension' => $dimension,
                'mode' => $cfg['mode'],
                'context_type' => $contextType,
                'context_id' => $contextId,
                'data' => $items->values()->toArray(),
                'count' => $items->count(),
            ];

            if ($isEntityBased) {
                $response['note'] = 'Eintraege enthalten jeweils "entity_id" (Organization-Entity-ID — IMMER fuer Folge-Operationen verwenden) und "id"/"dim_value_id" (interne Dim-Value-ID — nicht verwechseln).';
            }

            return ToolResult::success($response);
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
