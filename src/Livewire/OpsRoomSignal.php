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
 * Ops-Room Drill-Down: ein einzelnes Signal im Detail.
 * Pfad-Visualisierung S1->S5 mit gegangenen Schritten, Inferenz-Grundlage,
 * Owner-Trail, Quick-Actions (acknowledge, resolve, dismiss).
 */
class OpsRoomSignal extends Component
{
    public OrganizationSignal $signal;

    public function mount(OrganizationSignal $signal): void
    {
        $teamId = auth()->user()->currentTeam->id;
        if ((int) $signal->team_id !== $teamId) {
            abort(403, 'Signal gehört nicht zum aktuellen Team.');
        }
        $this->signal = $signal->load(['entity:id,name', 'currentOwner:id,name', 'createdByAgent:id,name', 'perspectiveEntity:id,name', 'inferencePrompt:id,name,vsm_system']);
    }

    public function switchPerspective(int $entityId): void
    {
        $teamId = auth()->user()->currentTeam->id;
        if ($entity = PerspectiveService::setActiveEntity($entityId, $teamId)) {
            $this->redirect(route('organization.ops-room'), navigate: true);
        }
    }

    public function acknowledge(): void
    {
        $this->signal->update([
            'status' => 'acknowledged',
            'acknowledged_at' => $this->signal->acknowledged_at ?? now(),
        ]);
        $this->redirect(route('organization.ops-room.level', [
            'perspective' => $this->signal->perspective_entity_id ?? 0,
            'vsm' => $this->signal->vsm_level ?? 's5',
        ]), navigate: true);
    }

    public function resolve(): void
    {
        $this->signal->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => auth()->id(),
            'acknowledged_at' => $this->signal->acknowledged_at ?? now(),
        ]);
        $this->redirect(route('organization.ops-room.level', [
            'perspective' => $this->signal->perspective_entity_id ?? 0,
            'vsm' => $this->signal->vsm_level ?? 's5',
        ]), navigate: true);
    }

    public function dismiss(): void
    {
        $this->signal->update([
            'status' => 'dismissed',
            'acknowledged_at' => $this->signal->acknowledged_at ?? now(),
        ]);
        $this->redirect(route('organization.ops-room.level', [
            'perspective' => $this->signal->perspective_entity_id ?? 0,
            'vsm' => $this->signal->vsm_level ?? 's5',
        ]), navigate: true);
    }

    #[Computed]
    public function perspective(): ?OrganizationEntity
    {
        return $this->signal->perspectiveEntity ?? null;
    }

    #[Computed]
    public function availablePerspectives()
    {
        $teamId = auth()->user()->currentTeam->id;
        return PerspectiveService::getCarriersForTeam($teamId)
            ->map(fn ($e) => ['id' => $e->id, 'name' => $e->name])
            ->values();
    }

    /** Wir setzen die Header-Totals auf das aktuelle Signal — kompakt. */
    #[Computed]
    public function totals(): array
    {
        return [
            'open' => $this->signal->status === 'open' ? 1 : 0,
            'escalated' => $this->signal->escalated_at ? 1 : 0,
            'algedonic' => $this->signal->source_type === OrganizationSignal::SOURCE_TYPE_HUMAN_ALGEDONIC ? 1 : 0,
            'vacant_cells' => 0,
        ];
    }

    /**
     * Pfad-Visualisierung: VSM-Reihe S5..S1, Marker fuer Ursprungs-Ebene und aktuelle Ebene.
     */
    #[Computed]
    public function path(): array
    {
        $signal = $this->signal;
        $originLevel = $signal->inferencePrompt?->vsm_system ?? $signal->vsm_level;
        $currentLevel = $signal->vsm_level;
        $isEscalated = (bool) $signal->escalated_at;

        $rows = [];
        foreach (OrganizationEntityVsmAssignment::VSM_DEFINITIONS as $code => $def) {
            $rows[] = [
                'code' => $code,
                'display' => $def['code_display'],
                'label' => $def['label'],
                'is_origin' => $code === $originLevel,
                'is_current' => $code === $currentLevel,
                'is_passed' => $isEscalated && $this->isLevelBetween($code, $originLevel, $currentLevel),
            ];
        }
        return $rows;
    }

    protected function isLevelBetween(string $code, ?string $origin, ?string $current): bool
    {
        if (! $origin || ! $current || $origin === $current) return false;
        $order = array_keys(OrganizationEntityVsmAssignment::VSM_DEFINITIONS);
        $iOrigin = array_search($origin, $order, true);
        $iCurrent = array_search($current, $order, true);
        $iCode = array_search($code, $order, true);
        if ($iOrigin === false || $iCurrent === false || $iCode === false) return false;
        $lo = min($iOrigin, $iCurrent);
        $hi = max($iOrigin, $iCurrent);
        return $iCode >= $lo && $iCode <= $hi;
    }

    public function render()
    {
        return view('organization::livewire.ops-room-signal')
            ->layout('platform::layouts.app');
    }
}
