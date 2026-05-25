<?php

namespace Platform\Organization\Contracts;

/**
 * Provider kann Cost-Driver-Adjustments berechnen.
 *
 * Cost-Driver-Adjustments verschieben Metrik-Anteile von der Default-Entity
 * (z.B. Kontengruppen-Owner) auf die tatsächlichen Kostenverursacher-Entities.
 *
 * Beispiel: Transaktion -3.860€ auf BHG.Digital-Konto, Cost-Driver 42,8% BroichCatering
 * → BHG.Digital: -1.652€ Korrektur, BroichCatering: +1.652€
 */
interface HasCostDriverMetrics
{
    /**
     * Berechnet Cost-Driver-Adjustments für den aktuellen Monat.
     *
     * @param array<int, int[]> $groupLinksByEntity [entityId => [groupIds]] — Entity-Dimension-Links für Kontengruppen
     * @return array<int, array<string, float>> [entityId => ['cashflow_in' => x, 'cashflow_out' => y, 'cashflow_net' => z]]
     */
    public function costDriverAdjustments(array $groupLinksByEntity): array;
}
