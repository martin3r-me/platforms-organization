<?php

namespace Platform\Organization\People;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Platform\People\Support\ImpactSourceBase;

/**
 * Wirkungs-Quelle der Koordination (TRAAN/Parlan → organization_coordination_events) für people.
 *
 * Liefert nur die TRANSPARENZ-Facette (S2), je Akteur über actor_user_id:
 *   structured  verbindlich statt frei — Vorgangs-Handlungen (Anfrage, Entscheidung, Übergabe …) und
 *               Anmerkungen in einem Vorgang zählen als nachvollziehbar, freie Nachrichten (ohne Vorgang) nicht.
 *   situated    Vorgänge am Arbeitsobjekt — Vorgangs-Handlungen mit context_ref (Kontext-Raum einer Aufgabe,
 *               eines Tickets, Projekts …) zählen als nachvollziehbar, lose Vorgänge nicht.
 * Nachrichten tragen nur Metadaten (nie Inhalt).
 *
 * Registrierung in OrganizationServiceProvider::boot() nur, wenn people geladen ist.
 */
class CoordinationImpactSource extends ImpactSourceBase
{
    public function key(): string
    {
        return 'coordination';
    }

    public function transparency(int $teamId, array $subjects, CarbonInterface $from, CarbonInterface $to): array
    {
        $employeeByUser = [];
        foreach ($subjects as $subject) {
            if (! empty($subject['user_id'])) {
                $employeeByUser[(int) $subject['user_id']] = (int) $subject['employee_id'];
            }
        }
        if (! $employeeByUser || ! Schema::hasTable('organization_coordination_events')) {
            return [];
        }

        $rows = DB::table('organization_coordination_events')
            ->whereIn('actor_user_id', array_keys($employeeByUser))
            ->whereBetween('occurred_at', [$from, $to])
            ->selectRaw("actor_user_id,
                sum(case when kind <> 'message' or (thread is not null and thread <> '') then 1 else 0 end) as structured,
                count(*) as total,
                sum(case when kind <> 'message' and context_ref is not null and context_ref <> '' then 1 else 0 end) as situated,
                sum(case when kind <> 'message' then 1 else 0 end) as acts")
            ->groupBy('actor_user_id')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $employeeId = $employeeByUser[(int) $r->actor_user_id] ?? null;
            if ($employeeId === null) {
                continue;
            }
            $out[] = ['employee_id' => $employeeId, 'key' => 'structured', 'traced' => (int) $r->structured, 'total' => (int) $r->total];
            $out[] = ['employee_id' => $employeeId, 'key' => 'situated', 'traced' => (int) $r->situated, 'total' => (int) $r->acts];
        }

        return $out;
    }
}
