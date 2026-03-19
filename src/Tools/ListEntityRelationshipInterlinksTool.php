<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationEntityRelationshipInterlink;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ListEntityRelationshipInterlinksTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.entity_relationship_interlinks.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/entity-relationship-interlinks - Listet Interlink-Zuordnungen zu Entity Relationships. Nutze entity_relationship_id oder interlink_id zum Filtern.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'entity_relationship_id', 'interlink_id']),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Default: Team aus Kontext. Wird auf Root/Elterteam aufgelöst.',
                    ],
                    'entity_relationship_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach Entity Relationship ID. Nutze organization.entity_relationships.GET.',
                    ],
                    'interlink_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach Interlink ID. Nutze organization.interlinks.GET.',
                    ],
                    'include_relations' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Relationship- und Interlink-Details mitladen. Default: true.',
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

            $q = OrganizationEntityRelationshipInterlink::query()->where('team_id', $rootTeamId);

            if (array_key_exists('entity_relationship_id', $arguments) && $arguments['entity_relationship_id'] !== null) {
                $q->where('entity_relationship_id', (int) $arguments['entity_relationship_id']);
            }

            if (array_key_exists('interlink_id', $arguments) && $arguments['interlink_id'] !== null) {
                $q->where('interlink_id', (int) $arguments['interlink_id']);
            }

            $includeRelations = (bool) ($arguments['include_relations'] ?? true);
            if ($includeRelations) {
                $q->with(['entityRelationship.fromEntity', 'entityRelationship.toEntity', 'entityRelationship.relationType', 'interlink.category', 'interlink.type']);
            }

            $this->applyStandardFilters($q, $arguments, ['team_id', 'entity_relationship_id', 'interlink_id', 'created_at']);
            $this->applyStandardSearch($q, $arguments, ['note']);
            $this->applyStandardSort($q, $arguments, ['id', 'created_at'], 'created_at', 'desc');

            $result = $this->applyStandardPaginationResult($q, $arguments);

            $items = $result['data']->map(function ($pivot) use ($includeRelations) {
                $item = [
                    'id' => $pivot->id,
                    'uuid' => $pivot->uuid,
                    'entity_relationship_id' => $pivot->entity_relationship_id,
                    'interlink_id' => $pivot->interlink_id,
                    'note' => $pivot->note,
                    'is_active' => (bool) $pivot->is_active,
                    'metadata' => $pivot->metadata,
                    'created_at' => $pivot->created_at?->toIso8601String(),
                ];

                if ($includeRelations) {
                    $rel = $pivot->entityRelationship;
                    $item['relationship_summary'] = $rel ? ($rel->fromEntity?->name . ' ' . ($rel->relationType?->name ?? '?') . ' ' . $rel->toEntity?->name) : null;
                    $item['interlink_name'] = $pivot->interlink?->name;
                    $item['interlink_category'] = $pivot->interlink?->category?->name;
                    $item['interlink_type'] = $pivot->interlink?->type?->name;
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
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Relationship-Interlink-Zuordnungen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'entity_relationship_interlinks', 'lookup'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
