<?php

namespace Platform\Organization\Services;

use Platform\Organization\Models\OrganizationTimeEntry;
use Illuminate\Support\Carbon;

/**
 * Persönliche Zeit-Zusammenfassung (user-scoped) für die persönliche Sicht (home).
 *
 * Kontrakt für „meine gestempelten Zeiten" — analog dazu, wie der
 * PersonActivityRegistry Vital-Signs/Responsibilities liefert. Kapselt die
 * Abfrage von OrganizationTimeEntry, damit Konsumenten (home) nicht am Modell hängen.
 *
 * Abgrenzung zu EntityTimeResolver: der liefert Zeit *auf* einer Entity/Projekten
 * (context-scoped). Hier geht es um die vom User selbst gestempelten Einträge.
 */
class PersonTimeSummary
{
    /**
     * Getrackte Zeiten der letzten $days Tage für einen User, pro Tag + Summe.
     *
     * @return array{days: array<int, array{date: string, minutes: int}>, total_minutes: int, billed_minutes: int}
     */
    public function lastDays(int $userId, int $days = 7): array
    {
        $days = max(1, $days);
        $start = Carbon::today()->subDays($days - 1);
        $today = Carbon::today();

        // Tage vorinitialisieren (ältester zuerst).
        $byDay = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $byDay[$today->copy()->subDays($i)->toDateString()] = 0;
        }

        $rows = OrganizationTimeEntry::query()
            ->where('user_id', $userId)
            ->whereBetween('work_date', [$start->toDateString(), $today->toDateString()])
            ->get(['work_date', 'minutes', 'is_billed']);

        $total = 0;
        $billed = 0;
        foreach ($rows as $r) {
            $key = Carbon::parse($r->work_date)->toDateString();
            $m = (int) ($r->minutes ?? 0);
            if (array_key_exists($key, $byDay)) {
                $byDay[$key] += $m;
            }
            $total += $m;
            if ($r->is_billed) {
                $billed += $m;
            }
        }

        $daysOut = [];
        foreach ($byDay as $date => $minutes) {
            $daysOut[] = ['date' => $date, 'minutes' => $minutes];
        }

        return [
            'days'           => $daysOut,
            'total_minutes'  => $total,
            'billed_minutes' => $billed,
        ];
    }
}
