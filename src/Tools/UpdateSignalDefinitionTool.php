<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationSignalDefinition;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class UpdateSignalDefinitionTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.signal_definitions.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/signal_definitions/{id} - Aktualisiert eine Signal-Definition. Nutze organization.signal_definitions.GET um IDs zu ermitteln.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (wird auf Root/Elterteam aufgelöst). Default: Team aus Kontext.',
                ],
                'signal_definition_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID der Signal-Definition.',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Optional: Name.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung ("" zum Leeren).',
                ],
                'pattern_type' => [
                    'type' => 'string',
                    'description' => 'Optional: threshold, trend, cross_dimension, ratio.',
                ],
                'conditions' => [
                    'type' => 'object',
                    'description' => 'Optional: Neue Bedingungen.',
                ],
                'scope_type' => [
                    'type' => 'string',
                    'description' => 'Optional: all, entity_type, entity_ids, subtree.',
                ],
                'scope_value' => [
                    'type' => 'array',
                    'description' => 'Optional: Neue Scope-Werte (null zum Leeren).',
                ],
                'frequency' => [
                    'type' => 'string',
                    'description' => 'Optional: every_snapshot, daily, weekly.',
                ],
                'severity' => [
                    'type' => 'string',
                    'description' => 'Optional: info, warning, critical.',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: aktiv/inaktiv.',
                ],
            ],
            'required' => ['signal_definition_id'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $found = $this->validateAndFindModel(
                $arguments,
                $context,
                'signal_definition_id',
                OrganizationSignalDefinition::class,
                'NOT_FOUND',
                'Signal-Definition nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationSignalDefinition $def */
            $def = $found['model'];

            if ((int) $def->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Signal-Definition gehört nicht zum Root/Elterteam des angegebenen Teams.');
            }

            $update = [];

            if (array_key_exists('name', $arguments)) {
                $name = trim((string) ($arguments['name'] ?? ''));
                if ($name === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'name darf nicht leer sein.');
                }
                $update['name'] = $name;
            }

            if (array_key_exists('description', $arguments)) {
                $d = (string) ($arguments['description'] ?? '');
                $update['description'] = $d === '' ? null : $d;
            }

            if (array_key_exists('pattern_type', $arguments)) {
                $pt = $arguments['pattern_type'];
                if (! in_array($pt, ['threshold', 'trend', 'cross_dimension', 'ratio'])) {
                    return ToolResult::error('VALIDATION_ERROR', 'pattern_type muss threshold, trend, cross_dimension oder ratio sein.');
                }
                $update['pattern_type'] = $pt;
            }

            if (array_key_exists('conditions', $arguments)) {
                $update['conditions'] = $arguments['conditions'];
            }

            if (array_key_exists('scope_type', $arguments)) {
                $st = $arguments['scope_type'];
                if (! in_array($st, ['all', 'entity_type', 'entity_ids', 'subtree'])) {
                    return ToolResult::error('VALIDATION_ERROR', 'scope_type muss all, entity_type, entity_ids oder subtree sein.');
                }
                $update['scope_type'] = $st;
            }

            if (array_key_exists('scope_value', $arguments)) {
                $update['scope_value'] = $arguments['scope_value'];
            }

            if (array_key_exists('frequency', $arguments)) {
                $f = $arguments['frequency'];
                if (! in_array($f, ['every_snapshot', 'daily', 'weekly'])) {
                    return ToolResult::error('VALIDATION_ERROR', 'frequency muss every_snapshot, daily oder weekly sein.');
                }
                $update['frequency'] = $f;
            }

            if (array_key_exists('severity', $arguments)) {
                $s = $arguments['severity'];
                if (! in_array($s, ['info', 'warning', 'critical'])) {
                    return ToolResult::error('VALIDATION_ERROR', 'severity muss info, warning oder critical sein.');
                }
                $update['severity'] = $s;
            }

            if (array_key_exists('is_active', $arguments)) {
                $update['is_active'] = (bool) $arguments['is_active'];
            }

            if (! empty($update)) {
                $def->update($update);
            }
            $def->refresh();

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
                'message' => 'Signal-Definition erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren der Signal-Definition: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'signals', 'algedonic', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
