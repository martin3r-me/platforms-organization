<?php

namespace Platform\Organization\Services;

use Carbon\Carbon;

class ReportPeriodResolver
{
    /**
     * Berechnet den Zeitraum basierend auf der Frequenz.
     *
     * @return array{from: Carbon, to: Carbon}
     */
    public function resolve(string $frequency): array
    {
        $to = Carbon::now();

        $from = match ($frequency) {
            'daily' => $to->copy()->subDay(),
            'weekly' => $to->copy()->subWeek(),
            'monthly' => $to->copy()->subMonth(),
            default => $to->copy()->subWeek(), // manual → letzte 7 Tage
        };

        return [
            'from' => $from,
            'to' => $to,
        ];
    }
}
