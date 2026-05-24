<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntitySnapshot;
use Platform\Organization\Services\SnapshotMovementService;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class EntityMovementTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.entity_movement.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/entity-movement - Zeigt Metrik-Bewegung (Deltas/Trends) fuer eine Entity oder das gesamte Team. Vergleicht aktuelle Snapshot-Werte mit N Tagen zuvor. Filterbar nach Domain/Stream (group).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'entity_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Entity-ID. Ohne = Team-Aggregation ueber alle Entities.',
                ],
                'days' => [
                    'type' => 'integer',
                    'description' => 'Optional: Vergleichszeitraum in Tagen. Default: 7.',
                    'default' => 7,
                ],
                'group' => [
                    'type' => 'string',
                    'description' => 'Optional: Filter nach Domain/Stream (z.B. "dev", "planner", "recruiting", "core").',
                ],
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: Team aus Kontext.',
                ],
                'debug' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Raw snapshot metrics + Diagnose-Info zurueckgeben.',
                ],
            ],
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

            $days = max(1, min(90, (int) ($arguments['days'] ?? 7)));
            $group = $arguments['group'] ?? null;
            $entityId = $arguments['entity_id'] ?? null;

            $debug = (bool) ($arguments['debug'] ?? false);
            $service = resolve(SnapshotMovementService::class);

            if ($entityId) {
                $entity = OrganizationEntity::where('id', (int) $entityId)
                    ->where('team_id', $rootTeamId)
                    ->first();

                if (!$entity) {
                    return ToolResult::error('NOT_FOUND', 'Entity nicht gefunden oder gehoert nicht zum Team.');
                }

                if ($debug) {
                    $snap = OrganizationEntitySnapshot::where('entity_id', $entity->id)
                        ->orderByDesc('snapshot_date')
                        ->orderByRaw("FIELD(snapshot_period, 'morning', 'evening') DESC")
                        ->first();
                    $personKeys = array_filter(
                        array_keys($snap->metrics ?? []),
                        fn ($k) => str_starts_with($k, 'person_') || str_starts_with($k, 'active_persons') || str_starts_with($k, 'persons_')
                    );
                    $personMetrics = [];
                    foreach ($personKeys as $k) {
                        $personMetrics[$k] = $snap->metrics[$k];
                    }

                    // Also show all snapshots for today
                    $allToday = OrganizationEntitySnapshot::where('entity_id', $entity->id)
                        ->where('snapshot_date', now()->toDateString())
                        ->get()
                        ->map(fn ($s) => [
                            'period' => $s->snapshot_period,
                            'keys_count' => count($s->metrics ?? []),
                            'has_person' => count(array_filter(array_keys($s->metrics ?? []), fn ($k) => str_starts_with($k, 'person_'))) > 0,
                        ])->all();

                    return ToolResult::success([
                        'debug' => true,
                        'entity_id' => $entityId,
                        'snapshot_date' => $snap?->snapshot_date?->toDateString(),
                        'snapshot_period' => $snap?->snapshot_period,
                        'total_metric_keys' => count($snap->metrics ?? []),
                        'person_metrics' => $personMetrics,
                        'all_today' => $allToday,
                        'movement_service_class' => get_class($service),
                        'has_person_skip' => str_contains(
                            file_get_contents((new \ReflectionClass($service))->getFileName()),
                            "str_starts_with(\$key, 'person_')"
                        ),
                    ]);
                }

                $result = $service->forEntity($entity->id, $days, $group);
            } else {
                $entityIds = OrganizationEntity::forTeam($rootTeamId)->pluck('id')->toArray();
                $result = $service->forEntities($entityIds, $days, $group);
            }

            return ToolResult::success(array_merge($result->toArray(), [
                'team_id' => $resolved['team_id'],
                'root_team_id' => $rootTeamId,
                'entity_id' => $entityId,
            ]));
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'metrics', 'movement', 'analytics'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
