<?php

namespace Platform\Organization\Contracts;

use Symfony\Component\HttpFoundation\Response;

/**
 * Geld auf einer geteilten Knoten-Seite (Kunden-Tor): Rechnungen, Zahlungseingänge
 * und Belege des Kunden. Erscheint nur, wenn die Freigabe „Rechnungen & Zahlungen"
 * einschließt. Registriert über EntityLinkRegistry::registerShareFinance().
 *
 * Das Modul entscheidet selbst, welche Belege zum Ausschnitt gehören (z. B. über
 * Fremd-IDs der Knoten oder verknüpfte Transaktionen) — nur das landet auf der Seite.
 */
interface ProvidesShareFinance
{
    /**
     * @param int[] $entityIds Knoten im Ausschnitt (Wurzel zuerst)
     * @param array<string, int[]> $linked verknüpfte Objekte im Ausschnitt: alias => ids
     * @return array{
     *   currency: string,
     *   summary: array{invoiced: int, paid: int, open: int, overdue: int},
     *   invoices: array<int, array{ref: string, number: ?string, kind: string, date: ?\Carbon\CarbonInterface, due: ?\Carbon\CarbonInterface, gross: int, net: ?int, paid: int, open: int, status: string, status_label: string, paid_at: ?\Carbon\CarbonInterface, has_document: bool}>,
     *   payments: array<int, array{at: ?\Carbon\CarbonInterface, amount: int, label: string, invoices: string[]}>,
     *   hint: ?string,
     * }|null Beträge in Cent; null = nichts zu zeigen
     */
    public function shareFinance(int $teamId, array $entityIds, array $linked): ?array;

    /**
     * Beleg als Datei — nur, wenn er zu dem gehört, was shareFinance() für denselben
     * Ausschnitt liefert. null → 404.
     */
    public function shareDocument(int $teamId, array $entityIds, array $linked, string $ref): ?Response;
}
