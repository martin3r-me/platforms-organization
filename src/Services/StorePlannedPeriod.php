<?php

namespace Platform\Organization\Services;

use Platform\Organization\Models\OrganizationTimePeriod;

class StorePlannedPeriod
{
    /**
     * Erstellt einen neuen Soll-Zeitraum-Eintrag.
     *
     * @param array $data (team_id, user_id, context_type, context_id, planned_start, planned_end, note, is_active)
     * @return OrganizationTimePeriod
     */
    public function store(array $data): OrganizationTimePeriod
    {
        $period = OrganizationTimePeriod::create([
            'team_id' => $data['team_id'],
            'user_id' => $data['user_id'],
            'context_type' => $data['context_type'],
            'context_id' => $data['context_id'],
            'planned_start' => $data['planned_start'] ?? null,
            'planned_end' => $data['planned_end'] ?? null,
            'note' => $data['note'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return $period->fresh();
    }

    public function update(OrganizationTimePeriod $period, array $data): OrganizationTimePeriod
    {
        $update = [];

        if (array_key_exists('planned_start', $data)) {
            $update['planned_start'] = $data['planned_start'];
        }

        if (array_key_exists('planned_end', $data)) {
            $update['planned_end'] = $data['planned_end'];
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

        if (!empty($update)) {
            $period->update($update);
        }

        return $period->fresh();
    }
}
