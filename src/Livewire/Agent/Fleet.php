<?php

namespace Platform\Organization\Livewire\Agent;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationRoleAssignment;

/**
 * Fleet — die Roster-Sicht der Organisation auf ALLE ihre Agent-Mitglieder. Host-AGNOSTISCH: sichtbar
 * ist, wer bei der Org EINZAHLT (Heartbeat meldet Status + Usage + Kalibrierung), egal wo gehostet —
 * kein Vault-Mount wie in der lokalen Leitwarte. Die Org hält, was die Agenten melden; hier wird es
 * gezeigt. Klick auf einen Agenten → seine Entity-/ProfilePanel-Seite (Tiefe).
 */
class Fleet extends Component
{
    #[Computed]
    public function agents(): array
    {
        $entities = OrganizationEntity::query()
            ->agents()
            ->with('agentProfile')
            ->orderBy('name')
            ->get();

        // Domäne je Agent aus den Rollen-Assignments (eine Query, gruppiert — die erste nicht-leere zählt).
        $domains = OrganizationRoleAssignment::query()
            ->whereIn('person_entity_id', $entities->pluck('id'))
            ->with('role')
            ->get()
            ->groupBy('person_entity_id')
            ->map(fn ($rows) => $rows->pluck('role.domain')->filter()->first());

        return $entities->map(function ($e) use ($domains) {
            $p = $e->agentProfile;
            // Liveness großzügig (20 min): der Wach-Loop ruht bei Leerlauf bis ~15 min, ohne offline zu sein.
            $online = $p && $p->last_heartbeat_at && $p->last_heartbeat_at->greaterThan(now()->subMinutes(20));

            return [
                'id' => $e->id,
                'name' => $e->name,
                'domain' => $domains[$e->id] ?? null,
                'active' => $p ? (bool) $p->active : false,
                'online' => $online,
                'status' => $p?->status,
                'subscription' => $p?->claude_subscription,
                // Usage AUS DEM STREAM (Gehirn-Snapshot) statt aus dem einfrierenden Heartbeat-Feld:
                // ok=false / kein Snapshot → null → das Dashboard zeigt ehrlich „—" statt Alt-Wert.
                'five_hour_pct' => (is_array($p?->brain_snapshot) && ! empty($p->brain_snapshot['usage']['ok'])) ? (float) ($p->brain_snapshot['usage']['five_hour_pct'] ?? 0) : null,
                'seven_day_pct' => (is_array($p?->brain_snapshot) && ! empty($p->brain_snapshot['usage']['ok'])) ? (float) ($p->brain_snapshot['usage']['seven_day_pct'] ?? 0) : null,
                'calib_n' => (int) ($p?->calib_n ?? 0),
                'calib_gap' => $p?->calib_gap !== null ? (float) $p->calib_gap : null,
                'calib_accuracy' => $p?->calib_accuracy !== null ? (float) $p->calib_accuracy : null,
                'last_heartbeat' => $p?->last_heartbeat_at,
                'snapshot_at' => $p?->brain_snapshot_at,
            ];
        })->all();
    }

    public function render()
    {
        return view('organization::livewire.agent.fleet')
            ->layout('platform::layouts.app');
    }
}
