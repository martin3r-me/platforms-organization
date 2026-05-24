<?php

namespace Platform\Organization\Contracts;

interface HasPersonMetrics
{
    /**
     * Per-Person aufgeschluesselte Metriken fuer Aggregation.
     *
     * @param int[] $userIds
     * @param int $teamId
     * @return array<int, array<string, int|float>> [userId => [metricKey => value]]
     *   Keys sind ungeprefixed (z.B. 'active_items', nicht 'person_active_items')
     */
    public function personMetrics(array $userIds, int $teamId): array;

    /**
     * Metrik-Definitionen fuer Person-Level Keys.
     * @return array<string, array{label: string, group: string, direction: string, unit: string}>
     */
    public function personMetricDefinitions(): array;
}
