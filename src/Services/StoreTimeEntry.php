<?php

namespace Platform\Organization\Services;

use Platform\Organization\Models\OrganizationTimeEntry;

class StoreTimeEntry
{
    /**
     * Erstellt einen neuen Time-Entry.
     *
     * Kein Cascade mehr – der Entry speichert nur context_type + context_id
     * (worauf direkt gestempelt wurde). Die Auflösung "welche Zeiten gehören
     * zu Entity X" passiert zur Lesezeit über EntityTimeResolver.
     *
     * @param array $data Entry-Daten (team_id, user_id, context_type, context_id, work_date, minutes, etc.)
     * @return OrganizationTimeEntry
     */
    public function store(array $data): OrganizationTimeEntry
    {
        $entry = OrganizationTimeEntry::create([
            'team_id' => $data['team_id'],
            'user_id' => $data['user_id'],
            'context_type' => $data['context_type'],
            'context_id' => $data['context_id'],
            'work_date' => $data['work_date'],
            'minutes' => $data['minutes'],
            'rate_cents' => $data['rate_cents'] ?? null,
            'amount_cents' => $data['amount_cents'] ?? null,
            'is_billed' => $data['is_billed'] ?? false,
            'metadata' => $data['metadata'] ?? null,
            'note' => $data['note'] ?? null,
        ]);

        // KeyResult-Bezug prüfen
        $hasKeyResult = $this->checkKeyResultLink($data['context_type'], $data['context_id']);

        if ($hasKeyResult) {
            $entry->update(['has_key_result' => true]);
        }

        return $entry->fresh();
    }

    /**
     * Prüft ob ein Context einen KeyResult-Bezug hat.
     */
    protected function checkKeyResultLink(string $contextType, int $contextId): bool
    {
        if (! class_exists(\Platform\Okr\Models\KeyResultContext::class)) {
            return false;
        }

        // Prüfe primären Context direkt
        $hasKeyResult = \Platform\Okr\Models\KeyResultContext::where('context_type', $contextType)
            ->where('context_id', $contextId)
            ->where('is_primary', true)
            ->exists();

        if ($hasKeyResult) {
            return true;
        }

        // Für Tasks: Prüfe über Project
        if ($contextType === 'Platform\Planner\Models\PlannerTask' || $contextType === \Platform\Planner\Models\PlannerTask::class) {
            $task = \Platform\Planner\Models\PlannerTask::find($contextId);
            if ($task && $task->project_id) {
                return \Platform\Okr\Models\KeyResultContext::where('context_type', 'Platform\Planner\Models\PlannerProject')
                    ->where('context_id', $task->project_id)
                    ->where('is_primary', true)
                    ->exists();
            }
        }

        return false;
    }
}
