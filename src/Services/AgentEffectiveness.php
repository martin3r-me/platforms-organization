<?php

namespace Platform\Organization\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Platform\Organization\Models\OrganizationEntity;

/**
 * WIRKSAMKEITS-BILANZ der Agenten — was sie in der Organisation bewirken, nicht wie gesund ihr Gehirn
 * läuft (das zeigt die Leitwarte). Je Agent und Zeitraum aus den Quellen, in denen die Arbeit wirklich
 * landet:
 *   Ergebnis  — erledigte Aufgaben (Planner), Tickets (Dev, Helpdesk), erledigte Vorgänge (TRAAN)
 *   Anfragen  — was der Agent Menschen vorgelegt hat (Freigaben, Aufgaben, Formulare …)
 *   Qualität  — abgelehnte Übergaben/Vereinbarungen, verworfene Aufgaben, Gedächtnis-Korrekturen
 *   Tempo     — Reaktionszeit des Agenten auf Vorgänge
 *   Einsatz   — verbuchte Zeit; Kosten (Token, nur aktuelles 7-Tage-Fenster aus dem Gehirn-Snapshot)
 * Lose gekoppelt: fehlt ein Modul (Tabelle), bleibt sein Beitrag null statt Fehler.
 */
class AgentEffectiveness
{
    /** @return list<array<string,mixed>> eine Zeile je Agent */
    public function period(Carbon $from, Carbon $to, bool $withCost = true): array
    {
        $agents = OrganizationEntity::query()->agents()->with(['linkedUser', 'agentProfile'])->orderBy('name')->get()
            ->filter(fn ($a) => $a->linked_user_id);
        if ($agents->isEmpty()) {
            return [];
        }
        $userIds = $agents->pluck('linked_user_id')->map(fn ($v) => (int) $v)->all();
        $handles = $agents->mapWithKeys(fn ($a) => [(int) $a->linked_user_id => mb_strtolower((string) $a->linkedUser?->email)])->all();

        $tasks = $this->countBy('planner_tasks', 'user_in_charge_id', $userIds, fn ($q) => $q
            ->where('lifecycle_state', 'erledigt')->whereBetween('lifecycle_state_changed_at', [$from, $to]));
        $tasksDropped = $this->countBy('planner_tasks', 'user_in_charge_id', $userIds, fn ($q) => $q
            ->where('lifecycle_state', 'verworfen')->whereBetween('lifecycle_state_changed_at', [$from, $to]));
        $issues = $this->countBy('dev_issues', 'user_in_charge_id', $userIds, fn ($q) => $q
            ->where('is_done', true)->whereBetween('done_at', [$from, $to]));
        $tickets = $this->countBy('helpdesk_tickets', 'user_in_charge_id', $userIds, fn ($q) => $q
            ->where('is_done', true)->whereBetween('done_at', [$from, $to]));
        $minutes = $this->sumBy('organization_time_entries', 'user_id', 'minutes', $userIds, fn ($q) => $q
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()]));

        $coord = $this->coordination($userIds, $handles, $from, $to);
        // Korrektur = ein Mensch ändert/streicht etwas am Gedächtnis; Freigaben (approve*) sind keine.
        $corrections = $this->countBy('organization_agent_curations', 'organization_entity_id', $agents->pluck('id')->all(), fn ($q) => $q
            ->whereIn('op', ['replace', 'retire', 'revise'])->where('status', 'applied')->whereBetween('created_at', [$from, $to]));

        // Kosten je Ergebnis nur dort, wo Zeitraum und Token-Fenster übereinstimmen: das Gehirn meldet ein
        // gleitendes 7-Tage-Fenster — also Ergebnisse derselben 7 Tage (eigener Lauf, nicht der gewählte Zeitraum).
        $done7 = null;
        if ($to->isFuture() || $to->isToday()) {
            $done7 = $withCost ? collect($this->period(now()->subDays(7), now(), false))->pluck('done', 'entity_id')->all() : null;
        }
        $rows = [];
        foreach ($agents as $a) {
            $uid = (int) $a->linked_user_id;
            $c = $coord[$uid] ?? [];
            $done = ($tasks[$uid] ?? 0) + ($issues[$uid] ?? 0) + ($tickets[$uid] ?? 0) + ($c['handoff_done'] ?? 0);
            $tokens = null;
            $done7a = null;
            if ($withCost && ($to->isFuture() || $to->isToday())) {
                $snap = $a->agentProfile?->brain_snapshot;
                $tokens = is_array($snap) && isset($snap['budget']['tokens_7d']) ? (int) $snap['budget']['tokens_7d'] : null;
                $done7a = $done7[(int) $a->id] ?? $done;
            }
            $rows[] = [
                'entity_id' => (int) $a->id,
                'name' => $a->name,
                'tasks' => $tasks[$uid] ?? 0,
                'issues' => $issues[$uid] ?? 0,
                'tickets' => $tickets[$uid] ?? 0,
                'cases_done' => $c['handoff_done'] ?? 0,
                'done' => $done,
                'requests' => $c['requests'] ?? 0,
                'declined' => $c['declined'] ?? 0,
                'dropped' => $tasksDropped[$uid] ?? 0,
                'corrections' => $corrections[(int) $a->id] ?? 0,
                'reaction_median_s' => $c['reaction_median_s'] ?? null,
                'hours' => round(($minutes[$uid] ?? 0) / 60, 1),
                'tokens_7d' => $tokens,
                'done_7d' => $done7a,
                'tokens_per_result' => ($tokens !== null && $done7a > 0) ? (int) round($tokens / $done7a) : null,
            ];
        }

        return $rows;
    }

    /** Ergebnisse je Woche (für den Verlauf): [entity_id => [wochenstart => done]] über n Wochen. */
    public function weeklyDone(int $weeks): array
    {
        $out = [];
        $start = now()->startOfWeek()->subWeeks($weeks - 1);
        for ($i = 0; $i < $weeks; $i++) {
            $from = $start->copy()->addWeeks($i);
            foreach ($this->period($from, $from->copy()->endOfWeek(), false) as $r) {
                $out[$r['entity_id']][$from->toDateString()] = $r['done'];
            }
        }

        return $out;
    }

    /** TRAAN-Koordination aus den von Parlan gemeldeten Ereignissen. */
    private function coordination(array $userIds, array $handles, Carbon $from, Carbon $to): array
    {
        if (! Schema::hasTable('organization_coordination_events')) {
            return [];
        }
        $out = [];
        try {
            // Was der Agent selbst getan hat (Akteur): erledigte Übergaben, Anfragen an andere, Reaktionszeiten.
            $own = DB::table('organization_coordination_events')
                ->whereIn('actor_user_id', $userIds)->whereBetween('occurred_at', [$from, $to])
                ->get(['actor_user_id', 'kind', 'latency_seconds']);
            $lat = [];
            foreach ($own as $e) {
                $u = (int) $e->actor_user_id;
                if ($e->kind === 'handoff_done') {
                    $out[$u]['handoff_done'] = ($out[$u]['handoff_done'] ?? 0) + 1;
                } elseif ($e->kind === 'request') {
                    $out[$u]['requests'] = ($out[$u]['requests'] ?? 0) + 1;
                }
                if ($e->kind !== 'request' && $e->kind !== 'message' && $e->latency_seconds !== null) {
                    $lat[$u][] = (int) $e->latency_seconds;
                }
            }
            foreach ($lat as $u => $vals) {
                sort($vals);
                $out[$u]['reaction_median_s'] = $vals[intdiv(count($vals), 2)];
            }

            // Was andere mit den Anfragen des Agenten getan haben (Gegenüber = Agent): Ablehnungen.
            $byHandle = array_flip(array_filter($handles));
            if ($byHandle) {
                $rej = DB::table('organization_coordination_events')
                    ->whereIn('counterpart_handle', array_keys($byHandle))
                    ->whereIn('kind', ['handoff_decline', 'agreement_decline'])
                    ->whereBetween('occurred_at', [$from, $to])
                    ->selectRaw('counterpart_handle, count(*) as n')->groupBy('counterpart_handle')->get();
                foreach ($rej as $r) {
                    $u = $byHandle[$r->counterpart_handle] ?? null;
                    if ($u) {
                        $out[$u]['declined'] = (int) $r->n;
                    }
                }
            }
        } catch (\Throwable $e) {
            return $out;
        }

        return $out;
    }

    private function countBy(string $table, string $col, array $ids, \Closure $where): array
    {
        if (! $ids || ! Schema::hasTable($table)) {
            return [];
        }
        try {
            $q = DB::table($table)->whereIn($col, $ids);
            if (Schema::hasColumn($table, 'deleted_at')) {
                $q->whereNull('deleted_at');
            }
            $where($q);

            return $q->selectRaw("{$col} as k, count(*) as n")->groupBy($col)->pluck('n', 'k')->map(fn ($n) => (int) $n)->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function sumBy(string $table, string $col, string $sumCol, array $ids, \Closure $where): array
    {
        if (! $ids || ! Schema::hasTable($table)) {
            return [];
        }
        try {
            $q = DB::table($table)->whereIn($col, $ids);
            if (Schema::hasColumn($table, 'deleted_at')) {
                $q->whereNull('deleted_at');
            }
            $where($q);

            return $q->selectRaw("{$col} as k, sum({$sumCol}) as n")->groupBy($col)->pluck('n', 'k')->map(fn ($n) => (int) $n)->all();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
