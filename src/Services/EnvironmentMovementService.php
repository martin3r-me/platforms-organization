<?php

namespace Platform\Organization\Services;

use Platform\Organization\Models\OrganizationEnvironmentSnapshot;
use Platform\Organization\Models\OrganizationEnvironmentSource;

class EnvironmentMovementService
{
    public function forSource(int $sourceId): array
    {
        $snapshots = OrganizationEnvironmentSnapshot::where('source_id', $sourceId)
            ->orderByDesc('snapshot_date')
            ->limit(2)
            ->get();

        if ($snapshots->isEmpty()) {
            return ['has_data' => false];
        }

        $latest = $snapshots->first();
        $previous = $snapshots->count() > 1 ? $snapshots->last() : null;

        $result = [
            'has_data' => true,
            'latest' => [
                'date' => $latest->snapshot_date->format('Y-m-d'),
                'metrics' => $latest->metrics,
                'summary' => $latest->summary,
            ],
            'delta' => null,
        ];

        if ($previous) {
            $latestMetrics = $latest->metrics ?? [];
            $previousMetrics = $previous->metrics ?? [];

            $result['delta'] = [
                'sentiment_change' => ($latestMetrics['sentiment_score'] ?? 0) - ($previousMetrics['sentiment_score'] ?? 0),
                'relevance_change' => ($latestMetrics['relevance_score'] ?? 0) - ($previousMetrics['relevance_score'] ?? 0),
                'items_change' => ($latestMetrics['new_items_count'] ?? 0) - ($previousMetrics['new_items_count'] ?? 0),
                'previous_date' => $previous->snapshot_date->format('Y-m-d'),
            ];
        }

        return $result;
    }

    public function buildInferenceContext(int $teamId): array
    {
        $sources = OrganizationEnvironmentSource::forTeam($teamId)
            ->active()
            ->get();

        if ($sources->isEmpty()) {
            return [];
        }

        $context = [];

        foreach ($sources as $source) {
            $movement = $this->forSource($source->id);

            if (! $movement['has_data']) {
                continue;
            }

            $entry = [
                'source' => $source->name,
                'category' => $source->category,
                'summary' => $movement['latest']['summary'] ?? '',
                'sentiment' => $movement['latest']['metrics']['sentiment_score'] ?? null,
                'relevance' => $movement['latest']['metrics']['relevance_score'] ?? null,
                'topics' => $movement['latest']['metrics']['topics'] ?? [],
                'snapshot_date' => $movement['latest']['date'] ?? null,
            ];

            if ($movement['delta']) {
                $entry['delta'] = [
                    'sentiment_change' => $movement['delta']['sentiment_change'],
                    'relevance_change' => $movement['delta']['relevance_change'],
                    'items_change' => $movement['delta']['items_change'],
                ];
            }

            $context[] = $entry;
        }

        return $context;
    }
}
