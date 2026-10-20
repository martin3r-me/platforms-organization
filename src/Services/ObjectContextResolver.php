<?php

namespace Platform\Organization\Services;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

/**
 * Löst ein beliebiges Modul-Objekt (Projekt, Task, Ticket, …) auf die
 * verknüpften Entities auf — direkt über DimensionLinks oder, falls das Objekt
 * selbst nicht verortet ist, über sein Eltern-Objekt (HasContextParent im Modul).
 * Typnamen kommen aus ObjectTypeRegistry.
 *
 * Gemeinsame Basis für organization.context.resolve.GET und organization.brief.GET.
 */
class ObjectContextResolver
{
    private const MAX_PARENT_HOPS = 3;

    /**
     * Übergangs-Rückfall, solange ein Modul HasContextParent noch nicht
     * implementiert (Deploy-Reihenfolge). Entfernen, sobald planner/dev/
     * helpdesk/change ausgerollt sind. fk_field darf ein Relationspfad sein.
     */
    private const LEGACY_PARENTS = [
        'planner_task' => ['project_id', 'project'],
        'dev_issue' => ['board.dev_package_id', 'dev_package'],
        'helpdesk_ticket' => ['helpdesk_board_id', 'helpdesk_board'],
        'change_action' => ['change_project_id', 'change_project'],
        'change_phase' => ['change_project_id', 'change_project'],
    ];

    /**
     * Kurzname / Morph-Alias / FQCN → Morph-Alias, oder null.
     */
    public static function resolveAlias(string $objectType): ?string
    {
        return ObjectTypeRegistry::resolve($objectType);
    }

    /** @return string[] */
    public static function knownTypes(): array
    {
        return ObjectTypeRegistry::knownTypes();
    }

    /**
     * Entity-Links des Objekts (mit eager-geladener Entity). Ist das Objekt
     * selbst nicht verortet, wird die Eltern-Kette (laut Modul) bis zu
     * MAX_PARENT_HOPS Schritte hochgegangen.
     *
     * @return array{links: Collection, resolved_via: ?string}
     */
    public static function entityLinks(string $morphAlias, int $objectId): array
    {
        $alias = $morphAlias;
        $id = $objectId;

        for ($hop = 0; $hop <= self::MAX_PARENT_HOPS; $hop++) {
            $links = EntityDimensionBridge::linksForLinkables(self::linkableTypes($alias), [$id], true);
            if ($links->isNotEmpty()) {
                return ['links' => $links, 'resolved_via' => $hop === 0 ? null : $alias];
            }

            $parent = self::parentOf($alias, $id);
            if (! $parent) {
                break;
            }
            [$alias, $id] = $parent;
        }

        return ['links' => collect(), 'resolved_via' => null];
    }

    /** @return array{0: string, 1: int}|null */
    private static function parentOf(string $alias, int $id): ?array
    {
        $class = ObjectTypeRegistry::modelClass($alias);
        $object = $class ? $class::find($id) : null;
        if (! $object) {
            return null;
        }

        $parent = app(EntityLinkRegistry::class)->contextParent($alias, $object);
        if ($parent === null && isset(self::LEGACY_PARENTS[$alias])) {
            [$fkField, $parentAlias] = self::LEGACY_PARENTS[$alias];
            $parentId = data_get($object, $fkField);
            $parent = $parentId ? [$parentAlias, (int) $parentId] : null;
        }

        return $parent && $parent[1] ? [(string) $parent[0], (int) $parent[1]] : null;
    }

    /** DB kann Alias oder FQCN als linkable_type halten. */
    private static function linkableTypes(string $morphAlias): array
    {
        $types = [$morphAlias];
        if ($fqcn = Relation::getMorphedModel($morphAlias)) {
            $types[] = $fqcn;
        }

        return $types;
    }
}
