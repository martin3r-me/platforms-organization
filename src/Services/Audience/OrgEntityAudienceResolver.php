<?php

namespace Platform\Organization\Services\Audience;

use Platform\Core\Contracts\AudienceResolverInterface;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Services\EntityHierarchyService;

/**
 * Ziel = ein Org-Knoten (OrganizationEntity: Einheit/Position/Person).
 * Löst zu den konkreten Usern auf, die als Personen-Knoten unter dem Knoten
 * hängen (per linked_user_id), standardmäßig inklusive aller Nachfahren.
 *
 * Wird vom Organisation-Modul in der Core-AudienceResolverRegistry registriert —
 * Academy kennt weder dieses Modul noch diese Klasse.
 */
class OrgEntityAudienceResolver implements AudienceResolverInterface
{
    public function type(): string
    {
        return 'org_entity';
    }

    public function typeLabel(): string
    {
        return 'Organisation (Knoten)';
    }

    public function resolve(int $targetId, array $options = [], ?int $teamId = null): array
    {
        $ids = [$targetId];

        // Standard: gesamter Teilbaum unter dem Knoten.
        if (($options['include_descendants'] ?? true) !== false) {
            $map = app(EntityHierarchyService::class)->getAllDescendantMap([$targetId]);
            $ids = array_merge($ids, $map[$targetId] ?? []);
        }

        return OrganizationEntity::query()
            ->whereIn('id', $ids)
            ->when($teamId, fn ($q) => $q->where('team_id', $teamId))
            ->persons()
            ->whereNotNull('linked_user_id')
            ->pluck('linked_user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function label(int $targetId, ?int $teamId = null): ?string
    {
        return OrganizationEntity::query()->whereKey($targetId)->value('name');
    }

    public function options(?int $teamId = null): array
    {
        if (!$teamId) {
            return [];
        }

        return OrganizationEntity::query()
            ->where('team_id', $teamId)
            ->active()
            ->with('type:id,code')
            ->orderBy('name')
            ->get(['id', 'name', 'entity_type_id'])
            ->map(fn (OrganizationEntity $e) => [
                'id' => (int) $e->id,
                'label' => $e->type?->code ? $e->name . ' · ' . $e->type->code : (string) $e->name,
            ])
            ->all();
    }
}
