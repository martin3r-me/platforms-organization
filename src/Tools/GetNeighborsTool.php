<?php

namespace Platform\Organization\Tools;

use Illuminate\Database\Eloquent\Relations\Relation;
use Platform\Core\Contracts\HasDisplayName;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Authz\DimensionLinkAuthorizer;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityRelationship;
use Platform\Organization\Models\OrganizationSignal;
use Platform\Organization\Services\EntityDimensionBridge;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

/**
 * Graph-Nachbarschaft einer Entity: Eltern, Kinder, Beziehungen (inkl.
 * Beer-Kanal und Schnittstellen) bis Tiefe 2, plus verknüpfte Objekte des
 * Zentrums. Ergänzt organization.brief.GET — siehe docs/agent-einstieg.md.
 *
 * Authz: nur lesbare Entities (Org-Graph-Reichweite); Objekte nur, wenn das
 * Modul sie dem User zeigen würde. Team immer aus der Session. Alles begrenzt.
 */
class GetNeighborsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    private const EDGE_TYPES = ['parent', 'children', 'relationships', 'links'];
    private const MAX_DEPTH = 2;
    private const DEFAULT_LIMIT = 10;
    private const MAX_LIMIT = 25;
    private const MAX_NODES = 50;

    public function getName(): string
    {
        return 'organization.neighbors.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/neighbors - Nachbarschaft einer Entity im Org-Graphen: Eltern, Kinder, Beziehungen (Typ, Beer-Kanal, Schnittstellen) '
            . 'bis depth=2 sowie verknüpfte Objekte (Projekte, Tickets, …) der Entity selbst. Antwort als nodes + edges, '
            . 'jeder Knoten mit Link-Zählern und offenen Signalen. Für ein Objekt erst organization.brief.GET (liefert entity_id).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'entity_id' => [
                    'type' => 'integer',
                    'description' => 'Zentrum der Abfrage.',
                ],
                'depth' => [
                    'type' => 'integer',
                    'description' => 'Hops ab Zentrum: 1 (Default) oder 2.',
                ],
                'edge_types' => [
                    'type' => 'array',
                    'items' => ['type' => 'string', 'enum' => self::EDGE_TYPES],
                    'description' => 'Welche Kanten: parent, children, relationships, links. Default: alle. links gilt nur für das Zentrum.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Max. Einträge je Kantenart und Knoten (Default 10, max 25).',
                ],
                'include_inactive' => [
                    'type' => 'boolean',
                    'description' => 'Auch inaktive Kinder/Entities zeigen (Default false).',
                ],
            ],
            'required' => ['entity_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot([], $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];
            $teamIds = array_values(array_filter([(int) $resolved['team_id'], $rootTeamId]));
            $user = $context->user;
            $authz = new DimensionLinkAuthorizer();
            $readable = array_flip($authz->readableEntityIds($user));

            $centerId = (int) ($arguments['entity_id'] ?? 0);
            $depth = max(1, min(self::MAX_DEPTH, (int) ($arguments['depth'] ?? 1)));
            $limit = max(1, min(self::MAX_LIMIT, (int) ($arguments['limit'] ?? self::DEFAULT_LIMIT)));
            $edgeTypes = array_values(array_intersect(self::EDGE_TYPES, (array) ($arguments['edge_types'] ?? self::EDGE_TYPES)));
            if ($edgeTypes === []) {
                $edgeTypes = self::EDGE_TYPES;
            }
            $includeInactive = (bool) ($arguments['include_inactive'] ?? false);

            $center = $this->entityQuery($rootTeamId, $includeInactive)->find($centerId);
            if (! $center || ! isset($readable[$center->id])) {
                return ToolResult::error('NOT_FOUND', 'Entity nicht gefunden oder kein Lesezugriff.');
            }

            /** @var array<int, array{entity: OrganizationEntity, depth: int}> $nodes */
            $nodes = [$center->id => ['entity' => $center, 'depth' => 0]];
            $edges = [];
            $edgeKeys = [];
            $truncated = [];
            $hiddenEntities = 0;
            $frontier = [$center->id];

            $addEdge = function (array $edge) use (&$edges, &$edgeKeys) {
                $key = $edge['kind'] . ':' . ($edge['relationship_id'] ?? '') . ':' . $edge['from'] . '>' . $edge['to'];
                if (! isset($edgeKeys[$key])) {
                    $edgeKeys[$key] = true;
                    $edges[] = $edge;
                }
            };

            for ($d = 1; $d <= $depth && $frontier !== []; $d++) {
                $candidates = []; // entity_id => entity (neu entdeckt)

                if (in_array('parent', $edgeTypes, true)) {
                    $parentIds = collect($frontier)->map(fn ($id) => $nodes[$id]['entity']->parent_entity_id)->filter()->unique()->all();
                    $parents = $parentIds ? $this->entityQuery($rootTeamId, true)->whereIn('id', $parentIds)->get()->keyBy('id') : collect();
                    foreach ($frontier as $id) {
                        $pid = $nodes[$id]['entity']->parent_entity_id;
                        if (! $pid || ! $parents->has($pid)) {
                            continue;
                        }
                        if (! isset($readable[$pid])) {
                            $hiddenEntities++;
                            continue;
                        }
                        $addEdge(['kind' => 'child_of', 'from' => $id, 'to' => (int) $pid]);
                        $candidates[$pid] = $parents[$pid];
                    }
                }

                if (in_array('children', $edgeTypes, true)) {
                    $children = $this->entityQuery($rootTeamId, $includeInactive)
                        ->whereIn('parent_entity_id', $frontier)
                        ->orderBy('name')
                        ->get()
                        ->groupBy('parent_entity_id');
                    foreach ($children as $pid => $group) {
                        $visible = $group->filter(fn ($c) => isset($readable[$c->id]));
                        $hiddenEntities += $group->count() - $visible->count();
                        if ($visible->count() > $limit) {
                            $truncated[] = ['entity_id' => (int) $pid, 'edge' => 'children', 'total' => $visible->count(), 'shown' => $limit];
                        }
                        foreach ($visible->take($limit) as $child) {
                            $addEdge(['kind' => 'child_of', 'from' => $child->id, 'to' => (int) $pid]);
                            $candidates[$child->id] = $child;
                        }
                    }
                }

                if (in_array('relationships', $edgeTypes, true)) {
                    $rels = OrganizationEntityRelationship::active()
                        ->where(fn ($q) => $q->whereIn('from_entity_id', $frontier)->orWhereIn('to_entity_id', $frontier))
                        ->with([
                            'relationType:id,name,code,channel_class,traversal_direction',
                            'interlinks.interlink:id,name,url,reference',
                        ])
                        ->get();
                    $counterpartIds = $rels->flatMap(fn ($r) => [(int) $r->from_entity_id, (int) $r->to_entity_id])->unique()->diff(array_keys($nodes))->all();
                    $counterparts = $counterpartIds ? $this->entityQuery($rootTeamId, $includeInactive)->whereIn('id', $counterpartIds)->get()->keyBy('id') : collect();

                    $perNode = [];
                    foreach ($rels as $r) {
                        foreach ([(int) $r->from_entity_id, (int) $r->to_entity_id] as $side) {
                            if (! in_array($side, $frontier, true)) {
                                continue;
                            }
                            $otherId = $side === (int) $r->from_entity_id ? (int) $r->to_entity_id : (int) $r->from_entity_id;
                            $other = $nodes[$otherId]['entity'] ?? $candidates[$otherId] ?? $counterparts[$otherId] ?? null;
                            if (! $other) {
                                continue;
                            }
                            if (! isset($readable[$otherId])) {
                                $hiddenEntities++;
                                continue;
                            }
                            $perNode[$side] = ($perNode[$side] ?? 0) + 1;
                            if ($perNode[$side] > $limit) {
                                continue;
                            }
                            $addEdge(array_filter([
                                'kind' => 'relationship',
                                'relationship_id' => $r->id,
                                'from' => (int) $r->from_entity_id,
                                'to' => (int) $r->to_entity_id,
                                'type' => $r->relationType?->name,
                                'channel' => $r->relationType?->channel_class,
                                'via' => $r->interlinks
                                    ->map(fn ($ri) => $ri->interlink ? array_filter([
                                        'name' => $ri->interlink->name,
                                        'url' => $ri->interlink->url,
                                        'reference' => $ri->interlink->reference,
                                    ]) : null)
                                    ->filter()->values()->all() ?: null,
                            ], fn ($v) => $v !== null));
                            if (! isset($nodes[$otherId])) {
                                $candidates[$otherId] = $other;
                            }
                        }
                    }
                    foreach ($perNode as $nodeId => $total) {
                        if ($total > $limit) {
                            $truncated[] = ['entity_id' => $nodeId, 'edge' => 'relationships', 'total' => $total, 'shown' => $limit];
                        }
                    }
                }

                $frontier = [];
                foreach ($candidates as $id => $entity) {
                    if (isset($nodes[$id])) {
                        continue;
                    }
                    if (count($nodes) >= self::MAX_NODES) {
                        $truncated[] = ['edge' => 'nodes', 'max' => self::MAX_NODES];
                        break;
                    }
                    $nodes[$id] = ['entity' => $entity, 'depth' => $d];
                    $frontier[] = $id;
                }
            }

            // Kanten auf Knoten außerhalb der Auswahl (Node-Cap) entfernen
            $edges = array_values(array_filter($edges, fn ($e) => isset($nodes[$e['from']], $nodes[$e['to']])));

            $nodeIds = array_keys($nodes);
            $linkCounts = EntityDimensionBridge::linkCountsByEntityAndType($nodeIds);
            $signalCounts = OrganizationSignal::whereIn('entity_id', $nodeIds)->actionable()
                ->selectRaw('entity_id, count(*) as c')->groupBy('entity_id')->pluck('c', 'entity_id');
            $childCounts = $this->entityQuery($rootTeamId, $includeInactive)->whereIn('parent_entity_id', $nodeIds)
                ->selectRaw('parent_entity_id, count(*) as c')->groupBy('parent_entity_id')->pluck('c', 'parent_entity_id');

            $response = [
                'center_entity_id' => $center->id,
                'depth' => $depth,
                'nodes' => collect($nodes)->map(fn ($n, $id) => array_filter([
                    'id' => $id,
                    'name' => $n['entity']->name,
                    'type' => $n['entity']->type?->name,
                    'depth' => $n['depth'],
                    'is_active' => $n['entity']->is_active ? null : false,
                    'children' => (int) ($childCounts[$id] ?? 0) ?: null,
                    'open_signals' => (int) ($signalCounts[$id] ?? 0) ?: null,
                    'links' => $linkCounts[$id] ?? null,
                ], fn ($v) => $v !== null))->values()->all(),
                'edges' => $edges,
            ];

            if (in_array('links', $edgeTypes, true)) {
                $response['center_links'] = $this->centerLinks($center->id, $limit, $user, $authz, $teamIds);
            }
            if ($truncated !== []) {
                $response['truncated'] = $truncated;
            }
            if ($hiddenEntities > 0) {
                $response['hidden_entities'] = $hiddenEntities;
            }
            $response['next'] = [
                'organization.neighbors.GET' => 'Mit entity_id eines Knotens weiterwandern',
                'organization.brief.GET' => 'Zuständige, Strategie, Signale einer Entity',
                'organization.entity.detail.GET' => 'Alle verknüpften Objekte einer Entity',
            ];

            return ToolResult::success($response);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Nachbarschaft: ' . $e->getMessage());
        }
    }

    private function entityQuery(int $rootTeamId, bool $includeInactive)
    {
        $q = OrganizationEntity::where('team_id', $rootTeamId)
            ->with('type:id,name,code,vsm_class')
            ->select(['id', 'name', 'parent_entity_id', 'entity_type_id', 'is_active']);

        return $includeInactive ? $q : $q->where('is_active', true);
    }

    /**
     * Verknüpfte Objekte des Zentrums, je Typ begrenzt, nur lesbare mit Label.
     */
    private function centerLinks(int $entityId, int $limit, $user, DimensionLinkAuthorizer $authz, array $teamIds): array
    {
        $reverseMorphMap = array_flip(Relation::morphMap());
        $byType = EntityDimensionBridge::linksForEntity($entityId)->groupBy('linkable_type');

        $result = [];
        foreach ($byType as $storedType => $links) {
            $alias = $reverseMorphMap[$storedType] ?? $storedType;
            $class = Relation::getMorphedModel($storedType) ?? $storedType;
            $entry = ['type' => $alias, 'total' => $links->count()];

            if (class_exists($class)) {
                $ids = $links->pluck('linkable_id')->map(fn ($id) => (int) $id)->unique()->values();
                $items = [];
                $hidden = 0;
                // Mehr laden als angezeigt, damit nicht lesbare Objekte das Limit nicht leeren.
                foreach ($class::whereIn((new $class)->getKeyName(), $ids->take($limit * 3)->all())->get() as $obj) {
                    if (count($items) >= $limit) {
                        break;
                    }
                    if (! $authz->canReadObject($user, $obj, $teamIds)) {
                        $hidden++;
                        continue;
                    }
                    $items[] = ['id' => $obj->getKey(), 'label' => $this->label($obj)];
                }
                $entry['items'] = $items;
                if ($hidden > 0) {
                    $entry['hidden'] = $hidden;
                }
            }

            $result[] = $entry;
        }

        usort($result, fn ($a, $b) => $b['total'] <=> $a['total']);

        return $result;
    }

    private function label(object $model): ?string
    {
        foreach (['title', 'name', 'subject'] as $attr) {
            if (! empty($model->{$attr})) {
                return mb_strimwidth((string) $model->{$attr}, 0, 120, '…');
            }
        }

        return $model instanceof HasDisplayName ? $model->getDisplayName() : null;
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'graph', 'neighbors', 'navigation', 'agent'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
