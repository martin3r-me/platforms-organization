<?php

namespace Platform\Organization\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Optional für EntityLinkProvider: Objekte, die selbst nicht an einer Entity
 * hängen, erben ihre Verortung vom Eltern-Objekt (Task → Projekt,
 * Issue → Package, Ticket → Board). Das Modul kennt seine Struktur —
 * organization fragt nur. Wird per instanceof geprüft, bricht keine Provider.
 */
interface HasContextParent
{
    /**
     * @return array{0: string, 1: int}|null [parentMorphAlias, parentId] oder null, wenn kein Elter
     */
    public function contextParent(string $morphAlias, Model $object): ?array;
}
