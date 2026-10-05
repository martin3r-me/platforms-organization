<?php

namespace Platform\Organization\Livewire\Agent;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Organization\Models\OrganizationEntity;

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

        // Das WAS des Agenten = der JOB-PROFIL-Name (weich über owner_entity_id, wie im Profil-Endpoint).
        // Die „Domäne" als Label ist abgeschafft. class_exists-Gate: kein harter Cross-Modul-Zwang auf People.
        $jobNames = [];
        if (class_exists(\Platform\People\Models\JobProfile::class)) {
            $jobNames = \Platform\People\Models\JobProfile::query()
                ->whereIn('owner_entity_id', $entities->pluck('id'))
                ->where('status', 'active')
                ->orderByDesc('id')
                ->get(['owner_entity_id', 'name'])
                ->groupBy('owner_entity_id')
                ->map(fn ($g) => $g->first()->name)
                ->all();
        }

        return $entities->map(function ($e) use ($jobNames) {
            $p = $e->agentProfile;
            // Liveness großzügig (20 min): der Wach-Loop ruht bei Leerlauf bis ~15 min, ohne offline zu sein.
            $online = $p && $p->last_heartbeat_at && $p->last_heartbeat_at->greaterThan(now()->subMinutes(20));

            return [
                'id' => $e->id,
                'name' => $e->name,
                'domain' => $jobNames[$e->id] ?? null,
                // NEU: die quer-geschnittene Arbeits-Sicht — woran arbeitet er GERADE (dev+planner-Lock),
                // wieviel heute gebucht, und was war die letzte gemeldete Aktion. Macht die vorhandene
                // Selbstorg sichtbar; keine neue Mechanik, nur die schon gepushten Signale zusammengeführt.
                'work' => $this->currentWork($e->linked_user_id),
                'tracked_today_min' => $this->trackedToday($e->linked_user_id),
                'last_event' => $this->lastEvent($e->id),
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

    /**
     * Woran arbeitet der Agent GERADE: das zuletzt gelockte Arbeits-Item (dev_issues/planner_tasks,
     * agent_locked_at innerhalb 30 min = aktiv), quer über die Module. Cross-Modul nur lesend, per
     * Schema-Guard + try/catch (fehlt ein Modul/eine Spalte → bleibt leer statt zu brechen).
     */
    private function currentWork(?int $userId): ?array
    {
        if (! $userId) {
            return null;
        }
        $cands = [];
        try {
            if (Schema::hasTable('dev_issues')) {
                $r = DB::table('dev_issues')
                    ->leftJoin('dev_boards', 'dev_issues.dev_board_id', '=', 'dev_boards.id')
                    ->whereNull('dev_issues.deleted_at')
                    ->where('dev_issues.user_in_charge_id', $userId)
                    ->whereNotNull('dev_issues.agent_locked_at')
                    ->where('dev_issues.agent_locked_at', '>=', now()->subMinutes(30))
                    ->orderByDesc('dev_issues.agent_locked_at')
                    ->first(['dev_issues.title', 'dev_boards.name as where', 'dev_issues.agent_locked_at']);
                if ($r) {
                    $cands[] = ['title' => $r->title, 'where' => $r->where ?? 'Dev', 'since' => $r->agent_locked_at];
                }
            }
        } catch (\Throwable $e) {
        }
        try {
            if (Schema::hasTable('planner_tasks')) {
                $r = DB::table('planner_tasks')
                    ->leftJoin('planner_projects', 'planner_tasks.project_id', '=', 'planner_projects.id')
                    ->whereNull('planner_tasks.deleted_at')
                    ->where('planner_tasks.user_in_charge_id', $userId)
                    ->whereNotNull('planner_tasks.agent_locked_at')
                    ->where('planner_tasks.agent_locked_at', '>=', now()->subMinutes(30))
                    ->orderByDesc('planner_tasks.agent_locked_at')
                    ->first(['planner_tasks.title', 'planner_projects.name as where', 'planner_tasks.agent_locked_at']);
                if ($r) {
                    $cands[] = ['title' => $r->title, 'where' => $r->where ?? 'Planner', 'since' => $r->agent_locked_at];
                }
            }
        } catch (\Throwable $e) {
        }
        if ($cands === []) {
            return null;
        }
        usort($cands, fn ($a, $b) => strcmp((string) $b['since'], (string) $a['since']));
        $w = $cands[0];
        $since = $w['since'] ? Carbon::parse($w['since']) : null;

        return [
            'title' => $w['title'],
            'where' => $w['where'],
            'since_min' => $since ? (int) $since->diffInMinutes(now()) : null,
        ];
    }

    /** Heute gebuchte Minuten (OrganizationTimeEntry, work_date = heute) — die gestempelte Zeit. */
    private function trackedToday(?int $userId): int
    {
        if (! $userId) {
            return 0;
        }
        try {
            if (Schema::hasTable('organization_time_entries')) {
                return (int) DB::table('organization_time_entries')
                    ->where('user_id', $userId)
                    ->whereDate('work_date', today())
                    ->sum('minutes');
            }
        } catch (\Throwable $e) {
        }

        return 0;
    }

    /** Letzte gemeldete Aktion aus dem Activity-Log (organization_agent_run_events), human gelabelt. */
    private function lastEvent(int $entityId): ?array
    {
        $labels = [
            'claimed' => 'gerade übernommen', 'branch' => 'Branch angelegt', 'sync' => 'synchronisiert',
            'done' => 'zuletzt erledigt', 'fail' => 'gescheitert', 'ask' => 'wartet auf Rückfrage',
            'review' => 'in Prüfung', 'learn' => 'lernt', 'idle' => 'im Leerlauf',
        ];
        try {
            if (Schema::hasTable('organization_agent_run_events')) {
                $r = DB::table('organization_agent_run_events')
                    ->where('organization_entity_id', $entityId)
                    ->orderByDesc('id')
                    ->first(['kind', 'text', 'created_at']);
                if ($r) {
                    return [
                        'kind' => $r->kind,
                        'label' => $labels[$r->kind] ?? $r->kind,
                        'at' => $r->created_at ? Carbon::parse($r->created_at) : null,
                    ];
                }
            }
        } catch (\Throwable $e) {
        }

        return null;
    }

    public function render()
    {
        return view('organization::livewire.agent.fleet')
            ->layout('platform::layouts.app');
    }
}
