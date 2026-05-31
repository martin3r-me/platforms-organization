<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationEnvironmentSource;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ListEnvironmentSourcesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.environment_sources.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/environment_sources - Listet konfigurierte Umwelt-Datenquellen (RSS-Feeds etc.) für VSM S4/S5 Diagnostik.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'source_type', 'category', 'is_active']),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Default: Team aus Kontext.',
                    ],
                    'source_type' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Source-Typ (z.B. rss).',
                    ],
                    'category' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Kategorie (wirtschaft, arbeitsmarkt, wetter, regulatorik, branche, wettbewerb, technologie).',
                    ],
                    'is_active' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Nur aktive/inaktive Sources.',
                    ],
                ],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $q = OrganizationEnvironmentSource::query()->where('team_id', $rootTeamId);

            if (array_key_exists('is_active', $arguments) && $arguments['is_active'] !== null) {
                $q->where('is_active', (bool) $arguments['is_active']);
            }

            if (! empty($arguments['source_type'])) {
                $q->where('source_type', $arguments['source_type']);
            }

            if (! empty($arguments['category'])) {
                $q->where('category', $arguments['category']);
            }

            $this->applyStandardFilters($q, $arguments, ['team_id', 'source_type', 'category', 'is_active', 'created_at']);
            $this->applyStandardSearch($q, $arguments, ['name']);
            $this->applyStandardSort($q, $arguments, ['id', 'name', 'created_at', 'last_pulled_at', 'category'], 'created_at', 'desc');

            $result = $this->applyStandardPaginationResult($q, $arguments);

            $items = $result['data']->map(fn ($source) => [
                'id' => $source->id,
                'uuid' => $source->uuid,
                'name' => $source->name,
                'source_type' => $source->source_type,
                'category' => $source->category,
                'cluster' => $source->cluster,
                'config' => $source->config,
                'pull_interval_hours' => $source->pull_interval_hours,
                'is_active' => (bool) $source->is_active,
                'last_pulled_at' => $source->last_pulled_at?->toIso8601String(),
                'created_at' => $source->created_at?->toIso8601String(),
            ])->values()->toArray();

            return ToolResult::success([
                'data' => $items,
                'pagination' => $result['pagination'] ?? null,
                'team_id' => $resolved['team_id'],
                'root_team_id' => $rootTeamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Environment-Sources: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'environment', 'vsm', 'lookup'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
