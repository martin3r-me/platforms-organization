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
 * Ops-Room Drill-Down: eine VSM-Ebene im Fokus.
 * Zeigt alle offenen Signale dieser Ebene aus der gewählten Perspektive,
 * Owner-Belegung, Eskalations-/Algedonic-Marker. Fit-to-Viewport.
 */
class OpsRoomLevel extends Component
{
    public ?int $perspectiveEntityId = null;
    public string $vsmLevel = 's5';

    public function mount(int $perspective, string $vsm): void
    {
        $teamId = auth()->user()->currentTeam->id;

        $entity = OrganizationEntity::with('type')
            ->where('id', $perspective)
            ->where('team_id', $teamId)
            ->first();

        if (! $entity || $entity->type?->vsm_class !== OrganizationEntityType::VSM_CLASS_CARRIER) {
            abort(404, 'Perspektive nicht gefunden.');
        }
        if (! in_array($vsm, OrganizationEntityVsmAssignment::VSM_SYSTEMS, true)) {
            abort(404, 'VSM-Ebene unbekannt.');
        }

        $this->perspectiveEntityId = $entity->id;
        $this->vsmLevel = $vsm;
    }

    public function switchPerspective(int $entityId): void
    {
        $teamId = auth()->user()->currentTeam->id;
        $entity = PerspectiveService::setActiveEntity($entityId, $teamId);
        if ($entity) {
            $this->redirect(route('organization.ops-room.level', [
                'perspective' => $entity->id,
                'vsm' => $this->vsmLevel,
            ]), navigate: true);
        }
    }

    #[Computed]
    public function perspective(): ?OrganizationEntity
    {
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

    #[Computed]
    public function levelInfo(): array
    {
        $def = OrganizationEntityVsmAssignment::VSM_DEFINITIONS[$this->vsmLevel] ?? null;
        return [
            'code' => $this->vsmLevel,
            'display' => $def['code_display'] ?? strtoupper($this->vsmLevel),
            'label' => $def['label'] ?? $this->vsmLevel,
            'description' => $def['description'] ?? '',
        ];
    }

    #[Computed]
    public function assignees(): array
    {
        return OrganizationEntityVsmAssignment::query()
            ->where('perspective_entity_id', $this->perspectiveEntityId)
            ->where('vsm_system', $this->vsmLevel)
            ->activeAt()
            ->with('assignedEntity:id,name')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'entity_id' => $a->assigned_entity_id,
                'name' => $a->assignedEntity?->name ?? '#'.$a->assigned_entity_id,
                'scope' => $a->scope,
                'notes' => $a->notes,
            ])
            ->all();
    }

    #[Computed]
    public function signals(): array
    {
        return OrganizationSignal::query()
            ->where('perspective_entity_id', $this->perspectiveEntityId)
            ->where('vsm_level', $this->vsmLevel)
            ->where('status', 'open')
            ->with(['entity:id,name', 'currentOwner:id,name', 'createdByAgent:id,name'])
            ->orderByRaw("CASE source_type WHEN 'human_algedonic' THEN 0 ELSE 1 END")
            ->orderByRaw('escalated_at IS NULL')
            ->orderBy('deadline_at')
            ->orderByDesc('created_at')
            ->limit(40)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'message_short' => mb_strimwidth((string) $s->message, 0, 180, '…'),
                'severity' => $s->severity,
                'source_type' => $s->source_type,
                'entity_name' => $s->entity?->name,
                'owner_name' => $s->currentOwner?->name,
                'agent_name' => $s->createdByAgent?->name,
                'created_at' => $s->created_at?->diffForHumans(),
                'deadline_at' => $s->deadline_at?->diffForHumans(),
                'is_overdue' => $s->deadline_at && $s->deadline_at->isPast(),
                'is_escalated' => (bool) $s->escalated_at,
                'is_algedonic' => $s->source_type === OrganizationSignal::SOURCE_TYPE_HUMAN_ALGEDONIC,
            ])
            ->all();
    }

    #[Computed]
    public function totals(): array
    {
        $row = OrganizationSignal::query()
            ->where('perspective_entity_id', $this->perspectiveEntityId)
            ->where('status', 'open')
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN escalated_at IS NOT NULL THEN 1 ELSE 0 END) as escalated, SUM(CASE WHEN source_type = ? THEN 1 ELSE 0 END) as algedonic', [OrganizationSignal::SOURCE_TYPE_HUMAN_ALGEDONIC])
            ->first();

        $vacant = OrganizationEntityVsmAssignment::query()
            ->where('perspective_entity_id', $this->perspectiveEntityId)
            ->activeAt()
            ->selectRaw('vsm_system, COUNT(*) as c')
            ->groupBy('vsm_system')
            ->pluck('c', 'vsm_system');

        $vacantCount = 0;
        foreach (OrganizationEntityVsmAssignment::VSM_SYSTEMS as $sys) {
            if (($vacant[$sys] ?? 0) === 0) $vacantCount++;
        }

        return [
            'open' => (int) ($row->total ?? 0),
            'escalated' => (int) ($row->escalated ?? 0),
            'algedonic' => (int) ($row->algedonic ?? 0),
            'vacant_cells' => $vacantCount,
        ];
    }

    public function render()
    {
        return view('organization::livewire.ops-room-level')
            ->layout('platform::layouts.app');
    }
}
