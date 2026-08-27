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
        $snap = is_array($this->entity->agentProfile?->brain_snapshot) ? $this->entity->agentProfile->brain_snapshot : [];

        return view('organization::livewire.agent.brain-graph', [
            'graph' => $this->graph(),
            'snapshotAt' => $this->entity->agentProfile?->brain_snapshot_at,
            'factCount' => (int) ($snap['facts'] ?? 0),
            'edgeCount' => (int) ($snap['edges'] ?? 0),
            'episodeCount' => (int) ($snap['episodes'] ?? 0),
            'skillCount' => (int) ($snap['skills'] ?? 0),
            // Die „was für ein Geist / in welcher Lage / wie kalibriert"-Schichten:
            'genome' => is_array($snap['genome'] ?? null) ? $snap['genome'] : null,
            'state' => is_array($snap['state'] ?? null) ? $snap['state'] : null,
            'rhythm' => is_array($snap['rhythm'] ?? null) ? $snap['rhythm'] : null,
            'changeGate' => is_array($snap['change_gate'] ?? null) ? $snap['change_gate'] : null,
            'calibration' => is_array($snap['calibration'] ?? null) ? $snap['calibration'] : null,
            'budget' => is_array($snap['budget'] ?? null) ? $snap['budget'] : null,
            'usage' => is_array($snap['usage'] ?? null) ? $snap['usage'] : null,
            // Die Listen:
            'episodes' => is_array($snap['episode_list'] ?? null) ? $snap['episode_list'] : [],
            'skills' => is_array($snap['skill_list'] ?? null) ? $snap['skill_list'] : [],
            'gateLog' => is_array($snap['gate_log'] ?? null) ? $snap['gate_log'] : [],
            'working' => is_array($snap['working'] ?? null) ? $snap['working'] : [],
        ]);
    }
}
