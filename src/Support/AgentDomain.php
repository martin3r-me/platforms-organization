<?php

namespace Platform\Organization\Support;

use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityRelationship;

/**
 * Der Verantwortungsbereich eines Agenten: die Einheiten, auf die er über eine Beziehung mit
 * Gestaltungsrecht (Capability manage/write, z. B. Venture Owner, Führt an) zeigt, plus die Einheit
 * seiner Stelle (people) — die WURZELN — und alle Einheiten darunter.
 */
final class AgentDomain
{
    /**
     * @param  list<int>  $roots
     * @param  list<int>  $all
     */
    private function __construct(public readonly array $roots, public readonly array $all) {}

    public static function of(OrganizationEntity $agent): self
    {
        $roots = OrganizationEntityRelationship::query()->active()
            ->where('from_entity_id', $agent->id)
            ->whereHas('relationType', fn ($q) => $q->where(fn ($w) => $w
                ->whereJsonContains('capabilities', 'manage')->orWhereJsonContains('capabilities', 'write')))
            ->pluck('to_entity_id')->map(fn ($id) => (int) $id)->all();
        $held = \Platform\People\Support\HeldJobProfile::class;
        if (class_exists($held)) {
            try {
                $unit = $held::forEntity((int) $agent->id)?->unit_entity_id;
                if ($unit) {
                    $roots[] = (int) $unit;
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }
        $roots = array_values(array_unique($roots));
        $all = $roots;
        $frontier = $roots;
        for ($depth = 0; $frontier !== [] && $depth < 12; $depth++) {
            $frontier = OrganizationEntity::query()->whereIn('parent_entity_id', $frontier)
                ->pluck('id')->map(fn ($id) => (int) $id)->diff($all)->values()->all();
            $all = array_merge($all, $frontier);
        }

        return new self($roots, $all);
    }

    public function contains(int $entityId): bool
    {
        return in_array($entityId, $this->all, true);
    }

    /** @return list<array{id:int, name:string, root:bool}> */
    public function units(): array
    {
        if ($this->all === []) {
            return [];
        }

        return OrganizationEntity::query()->whereIn('id', $this->all)->orderBy('name')->get(['id', 'name'])
            ->map(fn ($e) => ['id' => (int) $e->id, 'name' => (string) $e->name, 'root' => in_array((int) $e->id, $this->roots, true)])
            ->sortByDesc('root')->values()->all();
    }
}
