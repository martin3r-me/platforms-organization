<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Services\EntityLinkRegistry;

class MetricDefinitionsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.metric_definitions.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/metric-definitions - Listet alle bekannten Metrik-Definitionen mit Label, Group, Direction, Unit, Dimension (7½), Type (stock/flow/modulator). Filterbar nach Group oder Dimension.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'group' => [
                    'type' => 'string',
                    'description' => 'Optional: Filter nach fachlicher Gruppe (z.B. "work", "core", "crm", "okr").',
                ],
                'dimension' => [
                    'type' => 'string',
                    'description' => 'Optional: Filter nach 7½-Dimension (complexity, energy, throughput, org_capital, costs, revenue, potential, quality).',
                    'enum' => ['complexity', 'energy', 'throughput', 'org_capital', 'costs', 'revenue', 'potential', 'quality'],
                ],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $registry = resolve(EntityLinkRegistry::class);
            $group = $arguments['group'] ?? null;
            $dimension = $arguments['dimension'] ?? null;

            if ($dimension) {
                $definitions = $registry->metricDefinitionsForDimension($dimension);
            } elseif ($group) {
                $definitions = $registry->metricDefinitionsForGroup($group);
            } else {
                $definitions = $registry->allMetricDefinitions();
            }

            $groups = $registry->allMetricGroups();
            $dimensions = EntityLinkRegistry::allDimensions();

            return ToolResult::success([
                'definitions' => $definitions,
                'groups' => $groups,
                'dimensions' => $dimensions,
                'total' => count($definitions),
                'filter_group' => $group,
                'filter_dimension' => $dimension,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'metrics', 'definitions', 'lookup'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
