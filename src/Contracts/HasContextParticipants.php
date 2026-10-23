<?php

namespace Platform\Organization\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Optional für EntityLinkProvider: wer gehört zu einem Objekt (Projekt-Verantwortliche,
 * Zuständige von Aufgaben, …)? Genutzt, um ein Gespräch im Kontext (Parlan) gleich mit den
 * richtigen Leuten zu eröffnen. Das Modul kennt seine Struktur — organization fragt nur.
 * Wird per instanceof geprüft, bricht keine Provider. Sichtbarkeit prüft der Aufrufer.
 */
interface HasContextParticipants
{
    /**
     * @return list<int> User-IDs der Beteiligten (leer, wenn der Alias nicht zum Modul gehört)
     */
    public function contextParticipants(string $morphAlias, Model $object): array;
}
