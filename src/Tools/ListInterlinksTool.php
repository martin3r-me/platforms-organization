<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationInterlink;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ListInterlinksTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.interlinks.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/interlinks - Listet Interlinks im Team. Unterstützt filters/search/sort/limit/offset. Nutze category_id/type_id zum Filtern.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'category_id', 'type_id', 'is_active', 'is_bidirectional']),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Default: Team aus Kontext. Wird auf Root/Elterteam aufgelöst.',
                    ],
                    'category_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach Interlink-Kategorie ID. Nutze organization.interlink_categories.GET.',
                    ],
                    'type_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach Interlink-Typ ID. Nutze organization.interlink_types.GET.',
                    ],
                    'is_active' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Nur aktive/inaktive Interlinks. Default: keine Filterung.',
                    ],
                    'is_bidirectional' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Filter nach bidirektionalen Interlinks.',
                    ],
                    'active_only' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Nur zeitlich gültige Interlinks. Default: false.',
                        'default' => false,
                    ],
                    'include_relations' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Kategorie und Typ mitladen. Default: true.',
                        'default' => true,
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

            $q = OrganizationInterlink::query()->where('team_id', $rootTeamId);

            if (array_key_exists('category_id', $arguments) && $arguments['category_id'] !== null) {
                $q->where('category_id', (int) $arguments['category_id']);
            }

            if (array_key_exists('type_id', $arguments) && $arguments['type_id'] !== null) {
                $q->where('type_id', (int) $arguments['type_id']);
            }

            if (array_key_exists('is_active', $arguments) && $arguments['is_active'] !== null) {
                $q->where('is_active', (bool) $arguments['is_active']);
            }

            if (array_key_exists('is_bidirectional', $arguments) && $arguments['is_bidirectional'] !== null) {
                $q->where('is_bidirectional', (bool) $arguments['is_bidirectional']);
            }

            if (!empty($arguments['active_only'])) {
                $q->validNow();
            }

            $includeRelations = (bool) ($arguments['include_relations'] ?? true);
            if ($includeRelations) {
                $q->with(['category', 'type']);
            }

            $this->applyStandardFilters($q, $arguments, ['team_id', 'category_id', 'type_id', 'is_active', 'is_bidirectional', 'created_at']);
            $this->applyStandardSearch($q, $arguments, ['name', 'description']);
            $this->applyStandardSort($q, $arguments, ['id', 'name', 'created_at', 'valid_from', 'valid_to'], 'created_at', 'desc');

            $result = $this->applyStandardPaginationResult($q, $arguments);

            $items = $result['data']->map(function ($interlink) use ($includeRelations) {
                $item = [
                    'id' => $interlink->id,
                    'uuid' => $interlink->uuid,
                    'name' => $interlink->name,
                    'description' => $interlink->description,
                    'url' => $interlink->url,
                    'reference' => $interlink->reference,
                    'category_id' => $interlink->category_id,
                    'type_id' => $interlink->type_id,
                    'is_bidirectional' => (bool) $interlink->is_bidirectional,
                    'is_active' => (bool) $interlink->is_active,
                    'valid_from' => $interlink->valid_from?->toDateString(),
                    'valid_to' => $interlink->valid_to?->toDateString(),
                    'metadata' => $interlink->metadata,
                    'created_at' => $interlink->created_at?->toIso8601String(),
                ];

                if ($includeRelations) {
                    $item['category_name'] = $interlink->category?->name;
                    $item['category_code'] = $interlink->category?->code;
                    $item['type_name'] = $interlink->type?->name;
                    $item['type_code'] = $interlink->type?->code;
                }

                return $item;
            })->values()->toArray();

            return ToolResult::success([
                'data' => $items,
                'pagination' => $result['pagination'] ?? null,
                'team_id' => $resolved['team_id'],
                'root_team_id' => $rootTeamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Interlinks: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'interlinks', 'lookup'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
