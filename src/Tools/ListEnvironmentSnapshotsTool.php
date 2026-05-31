<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationEnvironmentSnapshot;
use Platform\Organization\Models\OrganizationEnvironmentSource;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ListEnvironmentSnapshotsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.environment_snapshots.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/environment_snapshots - Listet Snapshots einer Environment-Source mit optionaler Movement-Berechnung (Deltas zwischen aufeinanderfolgenden Snapshots).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: Team aus Kontext.',
                ],
                'source_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID der Environment-Source.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Optional: Max. Anzahl Snapshots. Default: 10.',
                ],
                'include_movement' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Deltas zwischen Snapshots berechnen. Default: true.',
                ],
            ],
            'required' => ['source_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $sourceId = (int) ($arguments['source_id'] ?? 0);
            if (! $sourceId) {
                return ToolResult::error('VALIDATION_ERROR', 'source_id ist erforderlich.');
            }

            $source = OrganizationEnvironmentSource::where('id', $sourceId)
                ->where('team_id', $rootTeamId)
                ->first();

            if (! $source) {
                return ToolResult::error('NOT_FOUND', 'Environment-Source nicht gefunden oder gehört nicht zum Team.');
            }

            $limit = min((int) ($arguments['limit'] ?? 10), 50);
            $includeMovement = (bool) ($arguments['include_movement'] ?? true);

            $snapshots = OrganizationEnvironmentSnapshot::where('source_id', $sourceId)
                ->orderByDesc('snapshot_date')
                ->limit($limit)
                ->get();

            $items = [];
            $previousMetrics = null;

            // Reverse to calculate deltas chronologically, then reverse back
            $chronological = $snapshots->reverse()->values();

            foreach ($chronological as $snapshot) {
                $entry = [
                    'id' => $snapshot->id,
                    'uuid' => $snapshot->uuid,
                    'snapshot_date' => $snapshot->snapshot_date->format('Y-m-d'),
                    'metrics' => $snapshot->metrics,
                    'summary' => $snapshot->summary,
                    'created_at' => $snapshot->created_at?->toIso8601String(),
                ];

                if ($includeMovement && $previousMetrics !== null) {
                    $currentMetrics = $snapshot->metrics ?? [];
                    $entry['delta'] = [
                        'sentiment_change' => ($currentMetrics['sentiment_score'] ?? 0) - ($previousMetrics['sentiment_score'] ?? 0),
                        'relevance_change' => ($currentMetrics['relevance_score'] ?? 0) - ($previousMetrics['relevance_score'] ?? 0),
                        'items_change' => ($currentMetrics['new_items_count'] ?? 0) - ($previousMetrics['new_items_count'] ?? 0),
                    ];
                } elseif ($includeMovement) {
                    $entry['delta'] = null;
                }

                $previousMetrics = $snapshot->metrics ?? [];
                $items[] = $entry;
            }

            // Reverse back to newest-first
            $items = array_reverse($items);

            return ToolResult::success([
                'source' => [
                    'id' => $source->id,
                    'name' => $source->name,
                    'category' => $source->category,
                    'source_type' => $source->source_type,
                ],
                'data' => $items,
                'count' => count($items),
                'team_id' => $resolved['team_id'],
                'root_team_id' => $rootTeamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Snapshots: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'environment', 'snapshots', 'movement', 'vsm'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
