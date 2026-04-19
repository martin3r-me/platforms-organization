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
        return 'GET /organization/metric-definitions - Listet alle bekannten Metrik-Definitionen mit Label, Group, Direction, Unit. Filterbar nach Group.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'group' => [
                    'type' => 'string',
                    'description' => 'Optional: Filter nach Domain/Stream (z.B. "dev", "planner", "core").',
                ],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $registry = resolve(EntityLinkRegistry::class);
            $group = $arguments['group'] ?? null;

            $definitions = $group
                ? $registry->metricDefinitionsForGroup($group)
                : $registry->allMetricDefinitions();

            $groups = $registry->allMetricGroups();

            return ToolResult::success([
                'definitions' => $definitions,
                'groups' => $groups,
                'total' => count($definitions),
                'filter_group' => $group,
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
