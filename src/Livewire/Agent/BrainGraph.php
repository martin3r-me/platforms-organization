<?php

namespace Platform\Organization\Livewire\Agent;

use Livewire\Component;
use Platform\Organization\Models\OrganizationEntity;

/**
 * Wissensgraph-Reiter eines Agenten: der Neocortex-Graph (Knoten = Fakten, Kanten = typisierte
 * Relationen) aus dem GEPUSHTEN brain_snapshot — host-agnostisch, kein Vault-Mount. Dieselbe
 * Tiefe wie die lokale Leitwarte, aber sichtbar wo der Agent einzahlt, egal wo er läuft. Der
 * Graph ist ein zeitgestempelter Snapshot (Stand vom letzten Push, ~10 min), read-only.
 */
class BrainGraph extends Component
{
    public OrganizationEntity $entity;

    public function mount(OrganizationEntity $entity): void
    {
        $this->entity = $entity;
    }

    /** Knoten + Kanten aus dem gepushten Snapshot (leer, solange noch kein Push kam). */
    public function graph(): array
    {
        $snap = $this->entity->agentProfile?->brain_snapshot;
        if (! is_array($snap)) {
            return ['nodes' => [], 'links' => []];
        }

        return [
            'nodes' => array_values(is_array($snap['nodes'] ?? null) ? $snap['nodes'] : []),
            'links' => array_values(is_array($snap['links'] ?? null) ? $snap['links'] : []),
        ];
    }

    public function render()
    {
        $snap = $this->entity->agentProfile?->brain_snapshot;

        return view('organization::livewire.agent.brain-graph', [
            'graph' => $this->graph(),
            'snapshotAt' => $this->entity->agentProfile?->brain_snapshot_at,
            'factCount' => is_array($snap) ? (int) ($snap['facts'] ?? 0) : 0,
            'edgeCount' => is_array($snap) ? (int) ($snap['edges'] ?? 0) : 0,
        ]);
    }
}
