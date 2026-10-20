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
use Platform\Organization\Models\OrganizationEntityType;
use Platform\Organization\Models\OrganizationRoleAssignment;
use Platform\Organization\Models\OrganizationSignal;
use Platform\Organization\Services\EntityDimensionBridge;
use Platform\Organization\Services\EntityStrategyPresenter;
use Platform\Organization\Services\ObjectContextResolver;
use Platform\Organization\Services\ObjectTypeRegistry;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

/**
 * Agent-Einstieg: ein Call liefert die Orientierung zu einem Objekt oder einer
 * Entity — wo hängt es im Baum, wer ist zuständig, welche Beziehungen/Kanäle,
 * was ist offen, welche Strategie gilt. Siehe docs/agent-einstieg.md.
 *
 * Authz: Objekt nur bei read/owns; Entities nur, wenn lesbar (Org-Graph-Reichweite).
 * Team kommt immer aus der Session (team_id-Argument wird ignoriert).
 * Alle Listen sind begrenzt.
 */
class GetBriefTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    private const MAX_ENTITIES = 5;
    private const MAX_PEOPLE = 10;
    private const MAX_RELATIONS = 10;
    private const MAX_SIGNALS = 3;
    private const MAX_FOCUS_AREAS = 5;
    private const MAX_DEPTH = 25;

    public function getName(): string
    {
        return 'organization.brief.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/brief - EINSTIEG für Aufgaben: Orientierung zu einem Objekt (Projekt, Task, Ticket, Issue, Kontakt, …) '
            . 'oder einer Entity in einem Call. Liefert: zugehörige Organisationseinheit(en) mit Pfad im Baum, Zuständige (Rollen/Personen), '
            . 'Beziehungen inkl. Schnittstellen, offene Signale, Strategie des nächsten Carriers (Mission/Vision/Fokusräume) und Link-Übersicht. '
            . 'Nutze dies ZUERST, bevor du mit Modul-Tools arbeitest. Entweder object_type+object_id ODER entity_id angeben.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'object_type' => [
                    'type' => 'string',
                    'description' => 'Typ des Objekts: project, task, ticket, issue, package, contact, company, canvas, thread, note, applicant, position, process, brand, … oder Morph-Alias.',
                ],
                'object_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Objekts (zusammen mit object_type).',
                ],
                'entity_id' => [
                    'type' => 'integer',
                    'description' => 'Alternativ: direkt eine Organisationseinheit briefen.',
                ],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            // Mandant immer aus der Session — kein team_id-Override.
            $resolved = $this->resolveTeamAndRoot([], $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];
            $user = $context->user;
            $authz = new DimensionLinkAuthorizer();
            $readable = array_flip($authz->readableEntityIds($user));

            $response = [];
            $entityIds = [];

            if (! empty($arguments['entity_id'])) {
                $entityIds = [(int) $arguments['entity_id']];
            } elseif (! empty($arguments['object_type']) && ! empty($arguments['object_id'])) {
                $objectId = (int) $arguments['object_id'];
                $alias = ObjectContextResolver::resolveAlias((string) $arguments['object_type']);
                if (! $alias) {
                    return ToolResult::error('UNKNOWN_TYPE', "Unbekannter object_type '{$arguments['object_type']}'. Bekannte Typen: " . implode(', ', ObjectContextResolver::knownTypes()));
                }

                $fqcn = Relation::getMorphedModel($alias);
                $object = ($fqcn && class_exists($fqcn)) ? $fqcn::find($objectId) : null;
                $teamIds = array_values(array_filter([(int) $resolved['team_id'], $rootTeamId]));
                if (! $object || ! $authz->canReadObject($user, $object, $teamIds)) {
                    return ToolResult::error('NOT_FOUND', 'Objekt nicht gefunden oder kein Lesezugriff.');
                }

                ['links' => $links, 'resolved_via' => $resolvedVia] = ObjectContextResolver::entityLinks($alias, $objectId);
                $response['object'] = [
                    'type' => ObjectTypeRegistry::displayName($alias),
                    'id' => $objectId,
                    'label' => $this->label($object),
                    'resolved_via' => $resolvedVia,
                ];
                $entityIds = $links->pluck('entity_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'Entweder object_type + object_id oder entity_id angeben.');
            }

            $visible = array_values(array_filter($entityIds, fn ($id) => isset($readable[$id])));
            $hidden = count($entityIds) - count($visible);

            $entities = OrganizationEntity::where('team_id', $rootTeamId)
                ->whereIn('id', array_slice($visible, 0, self::MAX_ENTITIES))
                ->with('type:id,name,code,vsm_class')
                ->get();

            if (! empty($arguments['entity_id']) && $entities->isEmpty()) {
                return ToolResult::error('NOT_FOUND', 'Entity nicht gefunden oder kein Lesezugriff.');
            }

            $ancestors = $this->ancestorChains($entities->pluck('id')->all(), $rootTeamId);

            $response['entities'] = $entities->map(fn ($e) => $this->briefEntity($e, $ancestors[$e->id] ?? [], $readable))->values()->all();
            if ($hidden > 0) {
                $response['hidden_entities'] = $hidden;
            }
            if (count($visible) > self::MAX_ENTITIES) {
                $response['more_entities'] = count($visible) - self::MAX_ENTITIES;
            }
            if ($entities->isEmpty()) {
                $response['message'] = 'Objekt ist keiner (lesbaren) Organisationseinheit zugeordnet. Verknüpfen mit organization.dimension_links.POST.';
            }
            $response['next'] = [
                'organization.neighbors.GET' => 'Umfeld im Org-Graphen: Eltern, Kinder, Beziehungen, verknüpfte Objekte',
                'organization.entity.detail.GET' => 'Alle verknüpften Objekte einer Entity',
                'organization.strategy.GET' => 'Vollständige Strategie eines Carriers',
                'organization.signals.GET' => 'Signale filtern/bearbeiten',
                'tool_registry.SEARCH' => 'Modul-Tools für CRUD finden',
            ];

            return ToolResult::success($response);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Briefs: ' . $e->getMessage());
        }
    }

    private function briefEntity(OrganizationEntity $entity, array $chain, array $readable): array
    {
        // $chain: Vorfahren root→Elter (ohne die Entity selbst)
        $lineage = array_merge($chain, [$entity]);

        return [
            'id' => $entity->id,
            'name' => $entity->name,
            'type' => $entity->type?->name,
            'path' => implode(' → ', array_map(fn ($n) => $n->name, $lineage)),
            'responsible' => $this->responsible($lineage),
            'relationships' => $this->relationships($entity->id, $readable),
            'signals' => $this->signals($entity->id),
            'strategy' => $this->strategy($lineage),
            'links' => EntityDimensionBridge::linkCountsByEntityAndType([$entity->id])[$entity->id] ?? [],
        ];
    }

    /**
     * Rollen-Zuweisungen der Entity; falls keine, die des nächsten Vorfahren
     * mit Zuweisungen (Zuständigkeit „erbt“ nach unten).
     */
    private function responsible(array $lineage): array
    {
        foreach (array_reverse($lineage) as $node) {
            $rows = OrganizationRoleAssignment::forContext($node->id)
                ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', now()->toDateString()))
                ->with(['role:id,name', 'person:id,name'])
                ->limit(self::MAX_PEOPLE)
                ->get();

            if ($rows->isNotEmpty()) {
                return [
                    'via_entity' => $node->name,
                    'people' => $rows->map(fn ($r) => array_filter([
                        'person' => $r->person?->name,
                        'person_entity_id' => $r->person_entity_id,
                        'role' => $r->role?->name,
                        'percentage' => $r->percentage,
                    ], fn ($v) => $v !== null))->values()->all(),
                ];
            }
        }

        return [];
    }

    private function relationships(int $entityId, array $readable): array
    {
        $rels = OrganizationEntityRelationship::active()
            ->where(fn ($q) => $q->where('from_entity_id', $entityId)->orWhere('to_entity_id', $entityId))
            ->with(['relationType:id,name,code', 'fromEntity:id,name', 'toEntity:id,name', 'interlinks.interlink:id,name,url,reference'])
            ->limit(self::MAX_RELATIONS * 2)
            ->get();

        return $rels
            ->map(function ($r) use ($entityId, $readable) {
                $outgoing = (int) $r->from_entity_id === $entityId;
                $other = $outgoing ? $r->toEntity : $r->fromEntity;
                if (! $other || ! isset($readable[$other->id])) {
                    return null;
                }

                return array_filter([
                    'direction' => $outgoing ? 'out' : 'in',
                    'type' => $r->relationType?->name,
                    'entity_id' => $other->id,
                    'entity' => $other->name,
                    'via' => $r->interlinks
                        ->map(fn ($ri) => $ri->interlink ? array_filter([
                            'name' => $ri->interlink->name,
                            'url' => $ri->interlink->url,
                            'reference' => $ri->interlink->reference,
                        ]) : null)
                        ->filter()->values()->all() ?: null,
                ], fn ($v) => $v !== null);
            })
            ->filter()
            ->take(self::MAX_RELATIONS)
            ->values()
            ->all();
    }

    private function signals(int $entityId): array
    {
        $base = OrganizationSignal::where('entity_id', $entityId)->actionable();
        $total = (clone $base)->count();
        if ($total === 0) {
            return ['open' => 0];
        }

        $top = (clone $base)
            ->orderByRaw("FIELD(severity, 'algedonic', 'critical', 'warning', 'info')")
            ->latest()
            ->limit(self::MAX_SIGNALS)
            ->get(['id', 'severity', 'message', 'created_at']);

        return [
            'open' => $total,
            'top' => $top->map(fn ($s) => [
                'id' => $s->id,
                'severity' => $s->severity,
                'message' => mb_strimwidth((string) $s->message, 0, 200, '…'),
            ])->all(),
        ];
    }

    /**
     * Strategie des nächsten Carriers (Entity selbst oder Vorfahr) — kompakt.
     */
    private function strategy(array $lineage): ?array
    {
        foreach (array_reverse($lineage) as $node) {
            if ($node->type?->vsm_class !== OrganizationEntityType::VSM_CLASS_CARRIER) {
                continue;
            }
            $s = EntityStrategyPresenter::forEntity($node);
            if (! $s) {
                continue;
            }

            return array_filter([
                'carrier' => $node->name,
                'carrier_entity_id' => $node->id,
                'mission' => isset($s['mission']['content']) ? mb_strimwidth(strip_tags((string) $s['mission']['content']), 0, 400, '…') : null,
                'vision' => isset($s['vision']['content']) ? mb_strimwidth(strip_tags((string) $s['vision']['content']), 0, 400, '…') : null,
                'focus_areas' => collect($s['focus_areas'] ?? [])->take(self::MAX_FOCUS_AREAS)->pluck('title')->all() ?: null,
            ], fn ($v) => $v !== null);
        }

        return null;
    }

    /**
     * Vorfahren-Ketten je Entity (root zuerst, ohne die Entity), batch-geladen.
     *
     * @return array<int, OrganizationEntity[]>
     */
    private function ancestorChains(array $ids, int $rootTeamId): array
    {
        $nodes = [];
        $toLoad = $ids;
        for ($i = 0; $toLoad !== [] && $i < self::MAX_DEPTH; $i++) {
            $rows = OrganizationEntity::where('team_id', $rootTeamId)
                ->whereIn('id', $toLoad)
                ->with('type:id,name,code,vsm_class')
                ->get(['id', 'name', 'parent_entity_id', 'entity_type_id']);
            $toLoad = [];
            foreach ($rows as $row) {
                $nodes[$row->id] = $row;
                if ($row->parent_entity_id && ! isset($nodes[$row->parent_entity_id])) {
                    $toLoad[] = (int) $row->parent_entity_id;
                }
            }
            $toLoad = array_values(array_unique($toLoad));
        }

        $chains = [];
        foreach ($ids as $id) {
            $chain = [];
            $pid = $nodes[$id]->parent_entity_id ?? null;
            for ($d = 0; $pid && isset($nodes[$pid]) && $d < self::MAX_DEPTH; $d++) {
                array_unshift($chain, $nodes[$pid]);
                $pid = $nodes[$pid]->parent_entity_id;
            }
            $chains[$id] = $chain;
        }

        return $chains;
    }

    private function label(object $model): ?string
    {
        foreach (['title', 'name', 'subject'] as $attr) {
            if (! empty($model->{$attr})) {
                return (string) $model->{$attr};
            }
        }

        return $model instanceof HasDisplayName ? $model->getDisplayName() : null;
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'brief', 'context', 'onboarding', 'agent', 'entry'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
