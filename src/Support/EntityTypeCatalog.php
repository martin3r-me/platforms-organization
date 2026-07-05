<?php

namespace Platform\Organization\Support;

use Platform\Organization\Models\OrganizationEntityType;

/**
 * Code-owned Ontologie fuer Entity-Types.
 *
 * Trennt DB-Stammdaten (code, name, icon — pflegt der Nutzer) von der
 * architektonischen Klassifikation (vsm_class, can_be_perspective — gehoert
 * in Code, weil sie das Beer-VSM-Modell abbildet und Refactorings normal sind).
 *
 * Aendern: Code hier editieren, dann `php artisan organization:sync-catalog`.
 * Kein Migration-Overhead noetig.
 *
 * Konvention:
 *  - Carrier   = lebensfaehiges System mit eigener Identitaet, Team, Strategie,
 *                eigenen S1-S5. Traegt Mission/Vision/Regnose. Kann Perspektive sein.
 *  - Actor     = fuellt VSM-Funktionen aus (S1-S5-Zellen), empfaengt Signale.
 *                Keine eigene Perspektive.
 *  - Observed  = Umwelt, Beobachtungsgegenstand, operativer Container. Kein
 *                Carrier, kein Actor. Default fuer alles Nicht-Gelistete.
 *
 * can_be_perspective ist implizit = (vsm_class === carrier). Wird in DB
 * ebenfalls persistiert (via saving-Hook auf dem Model), damit bestehende
 * Queries auf `where('can_be_perspective', true)` weiter funktionieren.
 */
class EntityTypeCatalog
{
    /**
     * Carrier — echte strategische Traeger. Nur was HIER steht, bekommt eine
     * Strategie-Tab, VSM-Matrix und Perspektiven-Rolle.
     */
    public const CARRIERS = [
        'business_unit',
        'venture',
        'strategic_partner',
        'service_line',
    ];

    /**
     * Actor — Rollen und Funktionstraeger innerhalb einer Carrier-Perspektive.
     */
    public const ACTORS = [
        'person',
        'board',
        'capability_area',
        'system_agent',
    ];

    /**
     * Klassifikation nach code. Default = observed.
     */
    public static function vsmClass(string $code): string
    {
        if (in_array($code, self::CARRIERS, true)) {
            return OrganizationEntityType::VSM_CLASS_CARRIER;
        }
        if (in_array($code, self::ACTORS, true)) {
            return OrganizationEntityType::VSM_CLASS_ACTOR;
        }
        return OrganizationEntityType::VSM_CLASS_OBSERVED;
    }

    /**
     * Perspektiv-Faehigkeit. Aktuell 1:1 mit carrier — bewusst gebuendelt,
     * um Drift zu verhindern.
     */
    public static function canBePerspective(string $code): bool
    {
        return in_array($code, self::CARRIERS, true);
    }
}
