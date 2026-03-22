<?php

namespace Platform\Organization\Services;

use Platform\Organization\Models\OrganizationTimePlanned;

class StorePlannedTime
{
    /**
     * Erstellt einen neuen Planned-Time-Eintrag.
     *
     * Kein Cascade mehr – der Eintrag speichert nur context_type + context_id.
     * Die Auflösung "welche geplanten Zeiten gehören zu Entity X" passiert
     * zur Lesezeit über EntityTimeResolver.
     *
     * @param array $data Planned-Daten (team_id, user_id, context_type, context_id, planned_minutes, note, is_active)
     * @return OrganizationTimePlanned
     */
    public function store(array $data): OrganizationTimePlanned
    {
        $planned = OrganizationTimePlanned::create([
            'team_id' => $data['team_id'],
            'user_id' => $data['user_id'],
            'context_type' => $data['context_type'],
            'context_id' => $data['context_id'],
            'planned_minutes' => $data['planned_minutes'],
            'note' => $data['note'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return $planned->fresh();
    }
}
