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
 *
 * Optional (duck-typed, damit ältere Module ohne sie weiterlaufen):
 *
 *   public function shareDetail(string $alias, Model $model, array $options): ?array
 *
 * liefert die Detailseite eines Objekts (/teilen/{token}/{slug}/{id}/{sub?}). Der Zugriff
 * ist da bereits geprüft — das Objekt liegt im Ausschnitt der Freigabe. Format:
 *   [
 *     'title' => string, 'subtitle' => ?string, 'description' => ?string,
 *     'state' => 'active'|'done'|'paused'|'planned', 'state_label' => string,
 *     'progress' => ?['done' => int, 'total' => int],
 *     'facts' => [['label' => string, 'value' => string]],
 *     'tabs' => [['key' => string, 'label' => string, 'count' => ?int, 'view' => string, 'data' => array]],
 *     // statt tabs: Unterseite, wenn $options['sub'] gesetzt ist
 *     'page' => ?['title' => string, 'view' => string, 'data' => array],
 *   ]
 * $options: details (bool), sub (?string), link (callable(?string $sub): string — URL zu
 * diesem Objekt bzw. einer Unterseite). null → 404 (z. B. unbekannte Unterseite).
 * Views bekommen data + $link und sollten nur --nx-*-Tokens/x-nx-* nutzen; Inhalt im Raster
 * `mx-auto max-w-[1120px] px-4 sm:px-8`, randlos Breites (Board) mit der Klasse `gp-bleed`.
 */
interface ProvidesShareView
{
    /**
     * Typen mit Kundenansicht.
     * Format: [alias => ['label' => 'Projekte', 'singular' => 'Projekt', 'icon' => 'folder', 'featured' => bool, 'slug' => 'projekt']]
     * `featured` = eigene, große Sektion oben (z. B. Projekte mit Fortschritt).
     * `slug` = URL-Teil der Detailseite (sonst der Alias).
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
