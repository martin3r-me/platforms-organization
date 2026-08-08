<?php

namespace Platform\Organization\Support;

use Platform\Organization\Models\OrganizationEntity;

/**
 * CurrentPerson — löst die Personen-Entität des eingeloggten Users aus dem Org-Graphen auf
 * (via `linked_user_id`). Kanonisches „wer bin ich" für ALLE Module: jede Modul-Facette
 * (practice-Arzt, people-Employee, crm-Kontakt) hängt am selben Personen-Knoten, und dieser
 * Resolver sagt, welcher Knoten der aktuelle Nutzer ist — arzt-unabhängig.
 *
 * Fehlertolerant: null, wenn kein User/keine verknüpfte Person existiert.
 */
class CurrentPerson
{
    /** Personen-Entity-ID des Users (oder des aktuellen Users), oder null. */
    public static function entityId(?int $userId = null): ?int
    {
        $userId = $userId ?? (auth()->check() ? (int) auth()->id() : null);
        if (!$userId) {
            return null;
        }

        try {
            $id = OrganizationEntity::query()->persons()->linkedToUser($userId)->value('id');
            return $id ? (int) $id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Die Personen-Entität selbst (oder null). */
    public static function entity(?int $userId = null)
    {
        $userId = $userId ?? (auth()->check() ? (int) auth()->id() : null);
        if (!$userId) {
            return null;
        }

        try {
            return OrganizationEntity::query()->persons()->linkedToUser($userId)->first();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
