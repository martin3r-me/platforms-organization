<?php

namespace Platform\Organization\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityType;
use Platform\Organization\Models\OrganizationEntityVsmAssignment;
use Platform\Organization\Models\OrganizationSignal;
use Platform\Organization\Services\PerspectiveService;

/**
 * Ops-Room — Hommage an Stafford Beers Operations Room (Santiago, 1972).
 *
 * Synoptisches Dashboard pro Carrier-Perspektive: S5 -> S4 -> S3* -> S3 -> S2 -> S1,
 * Owner-Belegung, offene + eskalierte + algedonische Signale je Ebene,
 * S1-Strip der aktiven Operativ-Einheiten.
 *
 * Kein Detail-Drilldown hier (das ist Signal/Index und Entity/Show) — der Ops-Room
 * ist eine Kommandobrueke, kein Verwaltungstool.
 */
class OpsRoom extends Component
{
    public ?int $perspectiveEntityId = null;

    public function mount(?int $perspective = null): void
    {
        $teamId = auth()->user()->currentTeam->id;
        $userId = auth()->id();

        $entity = null;
        if ($perspective) {
            $entity = OrganizationEntity::with('type')
                ->where('id', $perspective)
                ->where('team_id', $teamId)
                ->first();
        }
        if (! $entity || $entity->type?->vsm_class !== OrganizationEntityType::VSM_CLASS_CARRIER) {
            $entity = PerspectiveService::getActiveEntity($teamId, $userId)
                ?? PerspectiveService::getDefaultEntity($teamId);
        }
        $this->perspectiveEntityId = $entity?->id;
    }

    public function switchPerspective(int $entityId): void
    {
        $teamId = auth()->user()->currentTeam->id;
        $entity = PerspectiveService::setActiveEntity($entityId, $teamId);
        if ($entity) {
            $this->perspectiveEntityId = $entity->id;
            unset($this->perspective, $this->levels, $this->s1Units, $this->availablePerspectives);
        }
    }

    #[Computed]
    public function perspective(): ?OrganizationEntity
    {
        if (! $this->perspectiveEntityId) {
            return null;
        }
        return OrganizationEntity::with('type')->find($this->perspectiveEntityId);
    }

    #[Computed]
    public function availablePerspectives()
    {
        $teamId = auth()->user()->currentTeam->id;
        return PerspectiveService::getCarriersForTeam($teamId)
            ->map(fn ($e) => ['id' => $e->id, 'name' => $e->name])
            ->values();
    }

    /**
     * Pro VSM-Ebene: Owner-Namen, offene/eskalierte/algedonische Signal-Counts.
     */
    #[Computed]
    public function levels(): array
    {
        if (! $this->perspectiveEntityId) {
            return [];
        }

        // Assignees pro Ebene
        $assignmentsByLevel = OrganizationEntityVsmAssignment::query()
            ->where('perspective_entity_id', $this->perspectiveEntityId)
            ->activeAt()
            ->with('assignedEntity:id,name')
            ->get()
            ->groupBy('vsm_system');

        // Signal-Counts pro Ebene aus aktueller Perspektive
        $signalCounts = OrganizationSignal::query()
            ->where('perspective_entity_id', $this->perspectiveEntityId)
            ->where('status', 'open')
            ->selectRaw('vsm_level, COUNT(*) as total, SUM(CASE WHEN escalated_at IS NOT NULL THEN 1 ELSE 0 END) as escalated, SUM(CASE WHEN source_type = ? THEN 1 ELSE 0 END) as algedonic', [OrganizationSignal::SOURCE_TYPE_HUMAN_ALGEDONIC])
            ->groupBy('vsm_level')
            ->get()
            ->keyBy('vsm_level');

        $rows = [];
        foreach (OrganizationEntityVsmAssignment::VSM_DEFINITIONS as $code => $def) {
            $assignees = ($assignmentsByLevel[$code] ?? collect())
                ->map(fn ($a) => $a->assignedEntity?->name)
                ->filter()
                ->values()
                ->all();

            $counts = $signalCounts[$code] ?? null;

            $rows[] = [
                'code' => $code,
                'display' => $def['code_display'],
                'label' => $def['label'],
                'description' => $def['description'],
                'assignees' => $assignees,
                'vacant' => count($assignees) === 0,
                'open' => $counts ? (int) $counts->total : 0,
                'escalated' => $counts ? (int) $counts->escalated : 0,
                'algedonic' => $counts ? (int) $counts->algedonic : 0,
            ];
        }
        return $rows;
    }

    /**
     * S1-Strip — aktive S1-Assignees mit ihren entity-spezifischen offenen Signalen.
     */
    #[Computed]
    public function s1Units(): array
    {
        if (! $this->perspectiveEntityId) {
            return [];
        }

        $assignedIds = OrganizationEntityVsmAssignment::query()
            ->where('perspective_entity_id', $this->perspectiveEntityId)
            ->where('vsm_system', OrganizationEntityVsmAssignment::VSM_S1)
            ->activeAt()
            ->pluck('assigned_entity_id')
            ->all();

        if (empty($assignedIds)) {
            return [];
        }

        $entities = OrganizationEntity::query()
            ->whereIn('id', $assignedIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $openCounts = OrganizationSignal::query()
            ->whereIn('entity_id', $assignedIds)
            ->where('perspective_entity_id', $this->perspectiveEntityId)
            ->where('status', 'open')
            ->selectRaw('entity_id, COUNT(*) as c')
            ->groupBy('entity_id')
            ->pluck('c', 'entity_id');

        return $entities->map(fn ($e) => [
            'id' => $e->id,
            'name' => $e->name,
            'open' => (int) ($openCounts[$e->id] ?? 0),
        ])->all();
    }

    /**
     * Top-Line-Summen fuer die Kopfzeile.
     */
    #[Computed]
    public function totals(): array
    {
        $levels = $this->levels;
        return [
            'open' => array_sum(array_column($levels, 'open')),
            'escalated' => array_sum(array_column($levels, 'escalated')),
            'algedonic' => array_sum(array_column($levels, 'algedonic')),
            'vacant_cells' => count(array_filter($levels, fn ($l) => $l['vacant'])),
        ];
    }

    public function render()
    {
        return view('organization::livewire.ops-room')
            ->layout('platform::layouts.app');
    }
}
