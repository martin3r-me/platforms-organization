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
 * $options: details (bool), sub (?string), since (?Carbon — letzter Besuch, für „neu"-Marken),
 * hidden (alias => [id, …] — in der Freigabe Ausgeblendetes; die Modelle sind schon gefiltert, nützlich
 * für Typen, die mehrere Items zu einem Objekt bündeln),
 * link (callable(?string $sub): string — URL zu diesem Objekt bzw. einer Unterseite).
 * null → 404 (z. B. unbekannte Unterseite). Tabs dürfen 'order' (int, Standard 50) tragen;
 * organization ergänzt selbst den Tab „Arbeiten" (order 20) mit den gebuchten Zeiten.
 *
 *   public function shareActivity(string $alias, Collection $models, array $options): array
 *
 * liefert Ereignisse für den Aktivitätsstrom der Übersicht (neueste zuerst, höchstens
 * ~60 Tage): [['at' => Carbon, 'kind' => 'done'|'canvas'|'state'|…, 'title' => string,
 * 'text' => ?string, 'parent_id' => ?int (Objekt-ID des Typs, für den Link)]].
 *
 *   public function shareSummary(string $alias, Collection $models, array $options): ?array
 *
 * Kurzstatus für Sidebar und Übersicht: ['status' => ?['text' => '● online', 'tone' => 'success'|'warning'|'danger'|'info'|'neutral'],
 * 'kpis' => [['label' => 'Besucher (30 T.)', 'value' => '1.240', 'hint' => ?'+12 %', 'tone' => ?string]]].
 * kpis erscheinen in der Übersicht unter „Wirkung".
 *
 *   public function shareSection(string $alias, Collection $models, array $options): ?array
 *
 * Für Typen mit nav='section': EINE Seite für alle sichtbaren Objekte des Typs (Format wie
 * shareDetail, $options['sub'] für Unterseiten, $options['link'](?sub) für URLs).
 * Views bekommen data + $link und sollten nur --nx-*-Tokens/x-nx-* nutzen; Inhalt in voller
 * Breite mit der Klasse `gp-wrap` (Seitenränder wie die Hülle), Randloses (Board) mit `gp-bleed`.
 */
interface ProvidesShareView
{
    /**
     * Typen mit Kundenansicht.
     * Format: [alias => ['label' => 'Projekte', 'singular' => 'Projekt', 'icon' => 'folder', 'featured' => bool, 'slug' => 'projekt',
     *                    'group' => 'arbeit'|'wirkung'|'service'|'konto', 'nav' => 'items'|'section', 'detail' => bool, 'order' => int]]
     * `featured` = eigene, große Sektion oben (z. B. Projekte mit Fortschritt).
     * `slug` = URL-Teil der Detailseite (sonst der Alias).
     * `group` = Gruppe in der Sidebar (Standard arbeit). `nav` = items: jedes Objekt ein Eintrag mit
     * Detailseite (shareDetail); section: ein Eintrag für alle (shareSection). `detail` = false:
     * keine Detailseite (nur in Listen). `opt_in` = true: erscheint nur in Freigaben, die den Typ
     * ausdrücklich gewählt haben (nicht bei „alle Typen", z. B. Meetings).
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
