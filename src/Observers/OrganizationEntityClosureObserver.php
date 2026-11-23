<?php

namespace Platform\Organization\Observers;

use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Services\AuthzClosureSync;

/**
 * Hält authz_scope_closure synchron (Hot-Path), damit vererbte Rechte (z. B. write am Elternknoten)
 * sofort für neue oder umgehängte Knoten greifen – nicht erst nach dem nächsten authz:materialize.
 */
class OrganizationEntityClosureObserver
{
    public function created(OrganizationEntity $entity): void
    {
        AuthzClosureSync::syncSubtree((int) $entity->id, $entity->team_id ? (int) $entity->team_id : null);
    }

    public function updated(OrganizationEntity $entity): void
    {
        if (! $entity->wasChanged(['parent_entity_id', 'team_id'])) {
            return;
        }

        if ($entity->wasChanged('team_id') && $entity->getOriginal('team_id')) {
            // Alt-Team: Teilbaum dort neu aufbauen (Knoten selbst ist weg → keine Zeilen mehr).
            AuthzClosureSync::syncSubtree((int) $entity->id, (int) $entity->getOriginal('team_id'));
        }

        AuthzClosureSync::syncSubtree((int) $entity->id, $entity->team_id ? (int) $entity->team_id : null);
    }

    public function deleted(OrganizationEntity $entity): void
    {
        AuthzClosureSync::syncSubtree((int) $entity->id, $entity->team_id ? (int) $entity->team_id : null);
    }

    public function restored(OrganizationEntity $entity): void
    {
        AuthzClosureSync::syncSubtree((int) $entity->id, $entity->team_id ? (int) $entity->team_id : null);
    }
}
