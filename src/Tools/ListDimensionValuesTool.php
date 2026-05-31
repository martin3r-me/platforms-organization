<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationDimensionDefinition;
use Platform\Organization\Models\OrganizationDimensionValue;

/**
 * Listet Dimensions-Werte für eine gegebene Dimension.
 *
 * Nutze dieses Tool um dimension_item_id-Werte für dimension_links.POST zu ermitteln.
 * Beispiel: organization.dimension_values.GET(dimension="entity", search="offline")
 */
class ListDimensionValuesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;

    public function getName(): string
    {
        return 'organization.dimension_values.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/dimension-values - Listet Werte einer Dimension. WICHTIG: Nutze dieses Tool um dimension_item_id für dimension_links.POST zu finden (IDs nie raten). Dimensionen: entity, vsm-system, vsm-function, cost-center. Filtere nach code für exakten Match.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => [
                    'dimension' => [
                        'type' => 'string',
                        'description' => 'ERFORDERLICH: Dimensions-Key. Beispiel: "entity", "vsm-system", "vsm-function", "cost-center".',
                    ],
                    'code' => [
                        'type' => 'string',
                        'description' => 'Direkter Filter: exakter Code-Match. Beispiel: code="S1" für VSM System 1.',
                    ],
                    'is_active' => [
                        'type' => 'boolean',
                        'description' => 'Optional: true = nur aktive, false = nur inaktive. Default: true.',
                        'default' => true,
                    ],
                    'source_entity_id' => [
                        'type' => 'integer',
                        'description' => 'Direkter Filter (nur dimension="entity"): filtert nach verknüpfter Organization-Entity-ID.',
                    ],
                ],
                'required' => ['dimension'],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $dimensionKey = $arguments['dimension'] ?? '';

            $def = OrganizationDimensionDefinition::findByKey($dimensionKey);
            if (!$def) {
                $available = OrganizationDimensionDefinition::active()
                    ->pluck('key')
                    ->toArray();
                return ToolResult::error(
                    'VALIDATION_ERROR',
                    "Unbekannte Dimension '{$dimensionKey}'. Verfügbar: " . implode(', ', $available)
                );
            }

            $teamId = $context->team?->id;

            $q = OrganizationDimensionValue::query()
                ->where('dimension_definition_id', $def->id);

            if ($teamId) {
                $q->forTeam($teamId);
            }

            $activeOnly = (bool) ($arguments['is_active'] ?? true);
            if ($activeOnly) {
                $q->active();
            } elseif (array_key_exists('is_active', $arguments)) {
                $q->where('is_active', false);
            }

            if (!empty($arguments['code'])) {
                $q->where('code', trim((string) $arguments['code']));
            }

            if (!empty($arguments['source_entity_id'])) {
                $q->where('metadata->source_entity_id', (int) $arguments['source_entity_id']);
            }

            $this->applyStandardFilters($q, $arguments, ['created_at']);
            $this->applyStandardSearch($q, $arguments, ['code', 'name', 'description']);
            $this->applyStandardSort($q, $arguments, ['name', 'code', 'id', 'sort_order'], 'sort_order', 'asc');

            $result = $this->applyStandardPaginationResult($q, $arguments);
            $items = $result['data']->map(fn ($v) => [
                'id' => $v->id,
                'code' => $v->code,
                'name' => $v->name,
                'description' => $v->description,
                'is_active' => $v->is_active,
                'source_entity_id' => $v->metadata['source_entity_id'] ?? null,
            ])->values()->toArray();

            return ToolResult::success([
                'dimension' => $dimensionKey,
                'definition_id' => $def->id,
                'data' => $items,
                'pagination' => $result['pagination'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'dimensions', 'values', 'lookup'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
