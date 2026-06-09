<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityType;
use Platform\Organization\Models\OrganizationEntityVsmAssignment;
use Platform\Organization\Services\EntityHierarchyService;
use Platform\Organization\Services\PerspectiveService;

/**
 * Reverse-Hygiene-Check zum Vakanz-Tool:
 * Welche Actor-Entities haben in einer Perspektive KEINE VSM-Rolle?
 *
 * Strenger Beer: jeder Actor, der organisatorisch zu einem Carrier gehoert,
 * sollte dort mindestens eine Funktion ausfuellen — sonst ist er
 * organisationslos im VSM-Sinne.
 */
class ListVsmActorCoverageTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.vsm_actor_coverage.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/vsm-actor-coverage - Pro Actor-Entity: welche VSM-Zellen fuellt sie in der gewaehlten Perspektive aus? Standard-Scope = Subtree der Perspektive (Actors, die organisatorisch unter dem Carrier haengen). only_gaps=true zeigt Actors ohne jede Zuordnung — strenger Beer-Hygiene-Check.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'perspective_entity_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Carrier-Entity, aus deren Sicht geprueft werden soll. Default: aktive Perspektive aus Session.',
                ],
                'scope' => [
                    'type' => 'string',
                    'enum' => ['subtree', 'team'],
                    'description' => 'Optional: subtree (Default) = Actors im Subtree der Perspektive. team = alle Actor-Entities im Team.',
                ],
                'stop_at_subcarriers' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Nur bei scope=subtree relevant. Default: true. Wenn true, laeuft der Subtree-Walk nur durch Actor-Knoten (Personen, Boards, Capability-Areas) und stoppt an jedem Nicht-Actor-Kind (Sub-Carrier ODER observed). Strenger Beer: Actors unter Sub-Carriern gehoeren in deren Perspektive, Actors unter observed-Entities sind Umwelt.',
                ],
                'only_gaps' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Nur Actors ohne jede VSM-Zuordnung in dieser Perspektive. Default: true.',
                ],
                'active_only' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Nur heute gueltige Zuordnungen als "vorhanden" werten. Default: true.',
                ],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $context->team?->id;
            if (!$teamId) {
                return ToolResult::error('MISSING_TEAM', 'Kein Team im Kontext.');
            }

            $perspectiveEntity = null;
            if (!empty($arguments['perspective_entity_id'])) {
                $perspectiveEntity = OrganizationEntity::with('type')
                    ->where('id', (int) $arguments['perspective_entity_id'])
                    ->where('team_id', $teamId)
                    ->first();
                if (!$perspectiveEntity) {
                    return ToolResult::error('NOT_FOUND', "perspective_entity_id {$arguments['perspective_entity_id']} nicht im Team gefunden.");
                }
            } else {
                $perspectiveEntity = PerspectiveService::getActiveEntity($teamId, $context->user?->id);
            }

            if (!$perspectiveEntity || $perspectiveEntity->type?->vsm_class !== OrganizationEntityType::VSM_CLASS_CARRIER) {
                return ToolResult::error('VALIDATION_ERROR', 'Aktive/angegebene Perspektive ist kein Carrier-Entity.');
            }

            $scope = (string) ($arguments['scope'] ?? 'subtree');
            $stopAtSubcarriers = (bool) ($arguments['stop_at_subcarriers'] ?? true);
            $onlyGaps = (bool) ($arguments['only_gaps'] ?? true);
            $activeOnly = (bool) ($arguments['active_only'] ?? true);

            $actorQuery = OrganizationEntity::query()
                ->where('team_id', $teamId)
                ->active()
                ->whereHas('type', fn ($q) => $q->where('vsm_class', OrganizationEntityType::VSM_CLASS_ACTOR))
                ->with('type:id,name,code,vsm_class');

            if ($scope === 'subtree') {
                $subtreeIds = $stopAtSubcarriers
                    ? $this->walkSubtreeStoppingAtSubcarriers($perspectiveEntity->id, $teamId)
                    : array_merge(
                        [$perspectiveEntity->id],
                        resolve(EntityHierarchyService::class)
                            ->getAllDescendantMap([$perspectiveEntity->id])[$perspectiveEntity->id] ?? []
                    );

                $actorQuery->whereIn('id', $subtreeIds);
            }

            $actors = $actorQuery->orderBy('name')->get();
            if ($actors->isEmpty()) {
                return ToolResult::success([
                    'data' => [],
                    'count' => 0,
                    'summary' => [
                        'total_actors' => 0,
                        'covered_actors' => 0,
                        'gap_actors' => 0,
                        'coverage_pct' => 0,
                    ],
                    'perspective' => [
                        'entity_id' => $perspectiveEntity->id,
                        'name' => $perspectiveEntity->name,
                    ],
                    'message' => $scope === 'subtree'
                        ? 'Keine Actor-Entities im Subtree dieser Perspektive.'
                        : 'Keine Actor-Entities im Team.',
                ]);
            }

            $assignmentsQuery = OrganizationEntityVsmAssignment::query()
                ->where('perspective_entity_id', $perspectiveEntity->id)
                ->whereIn('assigned_entity_id', $actors->pluck('id'))
                ->select('assigned_entity_id', 'vsm_system');

            if ($activeOnly) {
                $assignmentsQuery->activeAt();
            }

            $assignments = $assignmentsQuery
                ->get()
                ->groupBy('assigned_entity_id')
                ->map(fn ($rows) => $rows->pluck('vsm_system')->unique()->values()->toArray())
                ->toArray();

            $results = [];
            $covered = 0;
            $gaps = 0;

            foreach ($actors as $actor) {
                $systems = $assignments[$actor->id] ?? [];
                $isCovered = count($systems) > 0;

                if ($isCovered) {
                    $covered++;
                } else {
                    $gaps++;
                }

                if ($onlyGaps && $isCovered) {
                    continue;
                }

                $results[] = [
                    'entity_id' => $actor->id,
                    'name' => $actor->name,
                    'code' => $actor->code,
                    'type' => $actor->type?->name,
                    'type_code' => $actor->type?->code,
                    'parent_entity_id' => $actor->parent_entity_id,
                    'vsm_systems' => $systems,
                    'is_covered' => $isCovered,
                ];
            }

            $total = $actors->count();

            return ToolResult::success([
                'data' => $results,
                'count' => count($results),
                'summary' => [
                    'total_actors' => $total,
                    'covered_actors' => $covered,
                    'gap_actors' => $gaps,
                    'coverage_pct' => $total > 0 ? (int) round(($covered / $total) * 100) : 0,
                ],
                'perspective' => [
                    'entity_id' => $perspectiveEntity->id,
                    'name' => $perspectiveEntity->name,
                ],
                'scope' => $scope,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Actor-Coverage-Check: ' . $e->getMessage());
        }
    }

    /**
     * Sammelt Entity-IDs im Subtree der Carrier-Perspektive.
     * Walk laeuft nur durch Actor-Knoten (Capability-Areas, etc.) und stoppt
     * an jedem Nicht-Actor-Kind: Sub-Carrier haben eigene Perspektive,
     * observed-Entities sind Umwelt — beider Subbaum gehoert nicht hierher.
     * Direkte Actor-Kinder werden eingesammelt; ihre weiteren Nachkommen
     * werden nur erkundet, wenn sie selbst Actor sind.
     */
    protected function walkSubtreeStoppingAtSubcarriers(int $rootId, int $teamId): array
    {
        $allEntities = OrganizationEntity::query()
            ->where('team_id', $teamId)
            ->select('id', 'parent_entity_id', 'entity_type_id')
            ->with('type:id,vsm_class')
            ->get();

        $childMap = [];
        foreach ($allEntities as $e) {
            if ($e->parent_entity_id !== null) {
                $childMap[$e->parent_entity_id][] = $e->id;
            }
        }
        $byId = $allEntities->keyBy('id');

        $collected = [$rootId];
        $stack = [$rootId];
        while (!empty($stack)) {
            $current = array_pop($stack);
            $children = $childMap[$current] ?? [];
            foreach ($children as $childId) {
                $child = $byId->get($childId);
                if (!$child) {
                    continue;
                }
                $collected[] = $childId;
                // Nur durch Actor-Knoten weitergehen — Sub-Carrier und observed
                // beenden den Walk an dieser Stelle.
                if ($child->type?->vsm_class !== OrganizationEntityType::VSM_CLASS_ACTOR) {
                    continue;
                }
                $stack[] = $childId;
            }
        }

        return $collected;
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'vsm', 'actors', 'coverage', 'hygiene'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
