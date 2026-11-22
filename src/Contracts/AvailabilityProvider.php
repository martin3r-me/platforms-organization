<?php

namespace Platform\Organization\Contracts;

use DateTimeInterface;

/**
 * Ob eine Person zu einem Zeitpunkt abwesend ist (Urlaub, krank, Dienstreise …).
 *
 * Die Organisation fragt, ein anderes Modul (people) beantwortet: Es bindet diesen Vertrag im Container.
 * Ohne Bindung gelten alle Personen als anwesend. Agenten beantwortet die Organisation selbst (offline = abwesend).
 */
interface AvailabilityProvider
{
    /**
     * @return array{until:?string, kind:?string}|null  null = anwesend; sonst bis wann (Datum, ISO) und welche Art
     */
    public function absence(int $personEntityId, DateTimeInterface $at): ?array;
}
