<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationVsmFunction;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ListVsmFunctionsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.vsm_functions.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/vsm-functions - Listet VSM-Funktionen (team-scoped). Unterstützt Hierarchie-Fallback: entity-spezifische Funktionen überschreiben globale. Nutze filters/search/sort/limit/offset.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'is_active', 'code', 'root_entity_id']),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Default: Team aus Kontext. Wird auf Root/Elterteam aufgelöst.',
                    ],
                    'is_active' => [
                        'type' => 'boolean',
                        'description' => 'Optional: aktive/inaktive Funktionen. Default: true.',
                        'default' => true,
                    ],
                    'code' => [
                        'type' => 'string',
                        'description' => 'Optional: Exakter Code-Filter.',
                    ],
                    'root_entity_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach root_entity_id (null = global, id = entity-spezifisch).',
                    ],
                    'entity_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Entity-ID für Hierarchie-Fallback. Gibt entity-spezifische + globale Funktionen zurück (entity-spezifische überschreiben globale bei gleichem Code).',
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

            // Hierarchie-Modus
            if (isset($arguments['entity_id']) && $arguments['entity_id']) {
                $items = OrganizationVsmFunction::getForEntityWithHierarchy($rootTeamId, (int) $arguments['entity_id']);

                return ToolResult::success([
                    'data' => $items->map(fn ($f) => [
                        'id' => $f->id,
                        'code' => $f->code,
                        'name' => $f->name,
                        'root_entity_id' => $f->root_entity_id,
                        'is_global' => $f->isGlobal(),
                        'is_active' => (bool) $f->is_active,
                    ])->values()->toArray(),
                    'count' => $items->count(),
                    'mode' => 'hierarchy',
                    'entity_id' => (int) $arguments['entity_id'],
                    'team_id' => $resolved['team_id'],
                    'root_team_id' => $rootTeamId,
                ]);
            }

            // Standard-Modus
            $activeOnly = (bool) ($arguments['is_active'] ?? true);
            $q = OrganizationVsmFunction::query()->where('team_id', $rootTeamId);

            if ($activeOnly) {
                $q->where('is_active', true);
            } elseif (array_key_exists('is_active', $arguments)) {
                $q->where('is_active', false);
            }

            if (array_key_exists('code', $arguments) && $arguments['code'] !== null && $arguments['code'] !== '') {
                $q->where('code', trim((string) $arguments['code']));
            }
            if (array_key_exists('root_entity_id', $arguments)) {
                $rid = $arguments['root_entity_id'];
                if ($rid === null || $rid === '' || $rid === 'null' || $rid === 0 || $rid === '0') {
                    $q->whereNull('root_entity_id');
                } else {
                    $q->where('root_entity_id', (int) $rid);
                }
            }

            $this->applyStandardFilters($q, $arguments, ['team_id', 'is_active', 'code', 'root_entity_id', 'entity_id', 'created_at']);
            $this->applyStandardSearch($q, $arguments, ['code', 'name', 'description']);
            $this->applyStandardSort($q, $arguments, ['name', 'code', 'id', 'created_at'], 'name', 'asc');

            $result = $this->applyStandardPaginationResult($q, $arguments);
            $items = $result['data']->map(fn ($f) => [
                'id' => $f->id,
                'code' => $f->code,
                'name' => $f->name,
                'root_entity_id' => $f->root_entity_id,
                'is_global' => $f->isGlobal(),
                'is_active' => (bool) $f->is_active,
            ])->values()->toArray();

            return ToolResult::success([
                'data' => $items,
                'pagination' => $result['pagination'] ?? null,
                'team_id' => $resolved['team_id'],
                'root_team_id' => $rootTeamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der VSM-Funktionen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'vsm', 'functions', 'lookup'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
