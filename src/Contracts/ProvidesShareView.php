<?php

namespace Platform\Organization\Contracts;

use Illuminate\Support\Collection;

/**
 * Kundenansicht eines Modul-Typs auf einer geteilten Knoten-Seite (Kunden-Tor).
 *
 * Das Modul entscheidet, was davon nach außen geht — nur das, was diese Methoden
 * liefern, landet auf der Seite. Typen ohne Kundenansicht erscheinen höchstens als
 * Titel-Liste und nur, wenn die Freigabe sie ausdrücklich einschließt.
 * Registriert über EntityLinkRegistry::registerShareView().
 */
interface ProvidesShareView
{
    /**
     * Typen mit Kundenansicht.
     * Format: [alias => ['label' => 'Projekte', 'singular' => 'Projekt', 'icon' => 'folder', 'featured' => bool]]
     * `featured` = eigene, große Sektion oben (z. B. Projekte mit Fortschritt).
     */
    public function shareTypes(): array;

    /**
     * Öffentliche Karten für die geladenen Modelle eines Typs — nur kundentaugliche Felder.
     *
     * Karte: [
     *   'id' => int, 'title' => string, 'subtitle' => ?string,
     *   'state' => 'active'|'done'|'paused'|'planned', 'state_label' => string,
     *   'progress' => ?['done' => int, 'total' => int],
     *   'due' => ?\Carbon\CarbonInterface, 'updated_at' => ?\Carbon\CarbonInterface,
     *   'recent' => [['title' => string, 'at' => ?Carbon]],   // zuletzt erledigt
     *   'next' => [['title' => string, 'due' => ?Carbon]],     // als Nächstes
     *   'counts' => ['open' => int, 'done' => int],
     * ]
     *
     * @param array{details: bool} $options details = einzelne Schritte (Aufgaben-Titel) zeigen
     */
    public function shareItems(string $alias, Collection $models, array $options): array;
}
