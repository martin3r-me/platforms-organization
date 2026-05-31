<?php

namespace Platform\Organization\Services;

use Platform\Organization\Models\OrganizationEnvironmentSnapshot;
use Platform\Organization\Models\OrganizationEnvironmentSource;
use Platform\Organization\Models\OrganizationMemoryEntry;

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

        // Load source_relevance memories indexed by source_id
        $relevanceMemories = OrganizationMemoryEntry::forTeam($teamId)
            ->ofType('source_relevance')
            ->active()
            ->valid()
            ->get()
            ->keyBy(fn ($m) => $m->structured_data['source_id'] ?? null);

        $context = [];

        foreach ($sources as $source) {
            $memory = $relevanceMemories->get($source->id);
            $learnedRelevance = $memory ? (float) ($memory->structured_data['relevance_rating'] ?? 0.5) : 0.5;
            $feedbackConfidence = $memory ? (float) $memory->confidence : 0.0;

            // Skip sources with very low learned relevance and high confidence
            if ($learnedRelevance < 0.2 && $feedbackConfidence >= 0.7) {
                continue;
            }

            $movement = $this->forSource($source->id);

            if (! $movement['has_data']) {
                continue;
            }

            $entry = [
                'source' => $source->name,
                'cluster' => $source->cluster,
                'category' => $source->category,
                'summary' => $movement['latest']['summary'] ?? '',
                'sentiment' => $movement['latest']['metrics']['sentiment_score'] ?? null,
                'relevance' => $movement['latest']['metrics']['relevance_score'] ?? null,
                'topics' => $movement['latest']['metrics']['topics'] ?? [],
                'snapshot_date' => $movement['latest']['date'] ?? null,
                'learned_relevance' => $learnedRelevance,
                'feedback_confidence' => $feedbackConfidence,
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

        // Sort by cluster, then by learned relevance (highest first, unknown = 0.5)
        usort($context, function ($a, $b) {
            $clusterCmp = ($a['cluster'] ?? '') <=> ($b['cluster'] ?? '');
            if ($clusterCmp !== 0) {
                return $clusterCmp;
            }

            return ($b['learned_relevance'] ?? 0.5) <=> ($a['learned_relevance'] ?? 0.5);
        });

        return $context;
    }
}
