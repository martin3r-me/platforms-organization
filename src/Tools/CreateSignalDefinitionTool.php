<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationSignalDefinition;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class CreateSignalDefinitionTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.signal_definitions.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/signal_definitions - Erstellt eine Signal-Definition (algedonic alert rule). Pattern-Typen: threshold, trend, cross_dimension, ratio. Conditions-JSON abhängig vom Pattern-Typ.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (wird auf Root/Elterteam aufgelöst). Default: Team aus Kontext.',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Name der Signal-Definition.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung.',
                ],
                'pattern_type' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: threshold, trend, cross_dimension, ratio.',
                    'enum' => ['threshold', 'trend', 'cross_dimension', 'ratio'],
                ],
                'conditions' => [
                    'type' => 'object',
                    'description' => 'ERFORDERLICH: Bedingungen je Pattern-Typ. threshold: {metric, operator, value}. trend: {metric, direction, periods, min_change_percent}. cross_dimension: {metric_a, metric_b, relationship}. ratio: {numerator, denominator, operator, value}.',
                ],
                'scope_type' => [
                    'type' => 'string',
                    'description' => 'Optional: all (default), entity_type, entity_ids, subtree.',
                    'enum' => ['all', 'entity_type', 'entity_ids', 'subtree'],
                    'default' => 'all',
                ],
                'scope_value' => [
                    'type' => 'array',
                    'description' => 'Optional: Scope-Werte (Type-Codes, Entity-IDs oder Root-Entity-ID). Nur bei scope_type != all.',
                ],
                'frequency' => [
                    'type' => 'string',
                    'description' => 'Optional: every_snapshot (default), daily, weekly.',
                    'enum' => ['every_snapshot', 'daily', 'weekly'],
                    'default' => 'every_snapshot',
                ],
                'severity' => [
                    'type' => 'string',
                    'description' => 'Optional: info, warning (default), critical.',
                    'enum' => ['info', 'warning', 'critical'],
                    'default' => 'warning',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: aktiv/inaktiv. Default: true.',
                    'default' => true,
                ],
            ],
            'required' => ['name', 'pattern_type', 'conditions'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $name = trim((string) ($arguments['name'] ?? ''));
            if ($name === '') {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich.');
            }

            $patternType = $arguments['pattern_type'] ?? '';
            if (! in_array($patternType, ['threshold', 'trend', 'cross_dimension', 'ratio'])) {
                return ToolResult::error('VALIDATION_ERROR', 'pattern_type muss threshold, trend, cross_dimension oder ratio sein.');
            }

            $conditions = $arguments['conditions'] ?? [];
            if (empty($conditions)) {
                return ToolResult::error('VALIDATION_ERROR', 'conditions ist erforderlich.');
            }

            $scopeType = $arguments['scope_type'] ?? 'all';
            if (! in_array($scopeType, ['all', 'entity_type', 'entity_ids', 'subtree'])) {
                return ToolResult::error('VALIDATION_ERROR', 'scope_type muss all, entity_type, entity_ids oder subtree sein.');
            }

            $frequency = $arguments['frequency'] ?? 'every_snapshot';
            if (! in_array($frequency, ['every_snapshot', 'daily', 'weekly'])) {
                return ToolResult::error('VALIDATION_ERROR', 'frequency muss every_snapshot, daily oder weekly sein.');
            }

            $severity = $arguments['severity'] ?? 'warning';
            if (! in_array($severity, ['info', 'warning', 'critical'])) {
                return ToolResult::error('VALIDATION_ERROR', 'severity muss info, warning oder critical sein.');
            }

            $def = OrganizationSignalDefinition::create([
                'name' => $name,
                'description' => (array_key_exists('description', $arguments) && $arguments['description'] !== '') ? (string) $arguments['description'] : null,
                'pattern_type' => $patternType,
                'conditions' => $conditions,
                'scope_type' => $scopeType,
                'scope_value' => $arguments['scope_value'] ?? null,
                'frequency' => $frequency,
                'severity' => $severity,
                'is_active' => (bool) ($arguments['is_active'] ?? true),
                'team_id' => $rootTeamId,
                'user_id' => $context->user?->id,
            ]);

            return ToolResult::success([
                'id' => $def->id,
                'uuid' => $def->uuid,
                'name' => $def->name,
                'pattern_type' => $def->pattern_type,
                'conditions' => $def->conditions,
                'scope_type' => $def->scope_type,
                'frequency' => $def->frequency,
                'severity' => $def->severity,
                'is_active' => (bool) $def->is_active,
                'message' => 'Signal-Definition erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen der Signal-Definition: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'signals', 'algedonic', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
