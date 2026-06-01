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
            'valid_from' => $data['valid_from'] ?? null,
            'valid_to' => $data['valid_to'] ?? null,
        ]);

        return $planned->fresh();
    }

    public function update(OrganizationTimePlanned $planned, array $data): OrganizationTimePlanned
    {
        $update = [];

        if (array_key_exists('planned_minutes', $data)) {
            $update['planned_minutes'] = $data['planned_minutes'];
        }

        if (array_key_exists('note', $data)) {
            $update['note'] = $data['note'];
        }

        if (array_key_exists('is_active', $data)) {
            $update['is_active'] = $data['is_active'];
        }

        if (array_key_exists('context_type', $data)) {
            $update['context_type'] = $data['context_type'];
        }

        if (array_key_exists('context_id', $data)) {
            $update['context_id'] = $data['context_id'];
        }

        if (array_key_exists('valid_from', $data)) {
            $update['valid_from'] = $data['valid_from'];
        }

        if (array_key_exists('valid_to', $data)) {
            $update['valid_to'] = $data['valid_to'];
        }

        if (!empty($update)) {
            $planned->update($update);
        }

        return $planned->fresh();
    }
}
