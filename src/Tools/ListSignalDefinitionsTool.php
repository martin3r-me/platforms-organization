<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationSignalDefinition;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ListSignalDefinitionsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.signal_definitions.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/signal_definitions - Listet Signal-Definitionen (algedonic alerts) im Team. Unterstützt filters/search/sort/limit/offset.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'is_active', 'pattern_type', 'severity']),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Default: Team aus Kontext. Wird auf Root/Elterteam aufgelöst.',
                    ],
                    'is_active' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Nur aktive/inaktive Definitionen. Default: keine Filterung.',
                    ],
                    'pattern_type' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Pattern-Typ (threshold, trend, cross_dimension, ratio).',
                    ],
                    'severity' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Severity (info, warning, critical).',
                    ],
                ],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $q = OrganizationSignalDefinition::query()->where('team_id', $rootTeamId);

            if (array_key_exists('is_active', $arguments) && $arguments['is_active'] !== null) {
                $q->where('is_active', (bool) $arguments['is_active']);
            }

            if (! empty($arguments['pattern_type'])) {
                $q->where('pattern_type', $arguments['pattern_type']);
            }

            if (! empty($arguments['severity'])) {
                $q->where('severity', $arguments['severity']);
            }

            $this->applyStandardFilters($q, $arguments, ['team_id', 'is_active', 'pattern_type', 'severity', 'created_at']);
            $this->applyStandardSearch($q, $arguments, ['name', 'description']);
            $this->applyStandardSort($q, $arguments, ['id', 'name', 'created_at', 'pattern_type', 'severity'], 'created_at', 'desc');

            $result = $this->applyStandardPaginationResult($q, $arguments);

            $items = $result['data']->map(fn ($def) => [
                'id' => $def->id,
                'uuid' => $def->uuid,
                'name' => $def->name,
                'description' => $def->description,
                'pattern_type' => $def->pattern_type,
                'conditions' => $def->conditions,
                'scope_type' => $def->scope_type,
                'scope_value' => $def->scope_value,
                'frequency' => $def->frequency,
                'severity' => $def->severity,
                'is_active' => (bool) $def->is_active,
                'created_at' => $def->created_at?->toIso8601String(),
            ])->values()->toArray();

            return ToolResult::success([
                'data' => $items,
                'pagination' => $result['pagination'] ?? null,
                'team_id' => $resolved['team_id'],
                'root_team_id' => $rootTeamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Signal-Definitionen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'signals', 'algedonic', 'lookup'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
