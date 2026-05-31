<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationEntityRelationship;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

/**
 * Listet Entity Relationships (team-scoped).
 */
class ListEntityRelationshipsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.entity_relationships.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/entity-relationships - Listet Beziehungen zwischen Entities. Nutze entity_id um ALLE Relationen einer Entity zu sehen (beide Richtungen). Oder from_entity_id / to_entity_id für eine Richtung. relation_type_code filtert nach Beziehungstyp ohne vorherige ID-Abfrage.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Default: Team aus Kontext. Wird auf Root/Elterteam aufgelöst.',
                    ],
                    'entity_id' => [
                        'type' => 'integer',
                        'description' => 'Direkter Filter: alle Relationen dieser Entity (egal ob from oder to). Beispiel: entity_id=5 zeigt alles was mit Entity 5 verbunden ist.',
                    ],
                    'from_entity_id' => [
                        'type' => 'integer',
                        'description' => 'Direkter Filter: nur Relationen VON dieser Entity (ausgehend).',
                    ],
                    'to_entity_id' => [
                        'type' => 'integer',
                        'description' => 'Direkter Filter: nur Relationen ZU dieser Entity (eingehend).',
                    ],
                    'relation_type_id' => [
                        'type' => 'integer',
                        'description' => 'Direkter Filter: nur Relationen dieses Typs (ID).',
                    ],
                    'relation_type_code' => [
                        'type' => 'string',
                        'description' => 'Direkter Filter: nur Relationen dieses Typs (Code). Beispiel: relation_type_code="reports_to". Erspart vorherige ID-Abfrage.',
                    ],
                    'active_only' => [
                        'type' => 'boolean',
                        'description' => 'Optional: true = nur zeitlich gültige Relationen (valid_from/to). Default: false.',
                        'default' => false,
                    ],
                    'include_relations' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Entity-Namen und Relation-Type-Details mitladen. Default: true.',
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

            $q = OrganizationEntityRelationship::query()->where('team_id', $rootTeamId);

            if (!empty($arguments['entity_id'])) {
                $entityId = (int) $arguments['entity_id'];
                $q->where(function ($query) use ($entityId) {
                    $query->where('from_entity_id', $entityId)
                          ->orWhere('to_entity_id', $entityId);
                });
            } else {
                if (!empty($arguments['from_entity_id'])) {
                    $q->where('from_entity_id', (int) $arguments['from_entity_id']);
                }
                if (!empty($arguments['to_entity_id'])) {
                    $q->where('to_entity_id', (int) $arguments['to_entity_id']);
                }
            }

            if (!empty($arguments['relation_type_id'])) {
                $q->where('relation_type_id', (int) $arguments['relation_type_id']);
            } elseif (!empty($arguments['relation_type_code'])) {
                $relType = \Platform\Organization\Models\OrganizationEntityRelationType::where('code', $arguments['relation_type_code'])->first();
                if ($relType) {
                    $q->where('relation_type_id', $relType->id);
                }
            }

            if (!empty($arguments['active_only'])) {
                $q->active();
            }

            $includeRelations = (bool) ($arguments['include_relations'] ?? true);
            if ($includeRelations) {
                $q->with(['fromEntity', 'toEntity', 'relationType']);
            }

            $this->applyStandardFilters($q, $arguments, ['created_at']);
            $this->applyStandardSearch($q, $arguments, []);
            $this->applyStandardSort($q, $arguments, ['id', 'created_at', 'valid_from', 'valid_to'], 'created_at', 'desc');

            $result = $this->applyStandardPaginationResult($q, $arguments);

            $items = $result['data']->map(function ($rel) use ($includeRelations) {
                $item = [
                    'id' => $rel->id,
                    'uuid' => $rel->uuid,
                    'from_entity_id' => $rel->from_entity_id,
                    'to_entity_id' => $rel->to_entity_id,
                    'relation_type_id' => $rel->relation_type_id,
                    'valid_from' => $rel->valid_from?->toDateString(),
                    'valid_to' => $rel->valid_to?->toDateString(),
                    'metadata' => $rel->metadata,
                    'created_at' => $rel->created_at?->toIso8601String(),
                ];

                if ($includeRelations) {
                    $item['from_entity_name'] = $rel->fromEntity?->name;
                    $item['to_entity_name'] = $rel->toEntity?->name;
                    $item['relation_type_name'] = $rel->relationType?->name;
                    $item['relation_type_code'] = $rel->relationType?->code;
                    $item['summary'] = $rel->summary;
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
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Entity Relationships: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'entity_relationships', 'lookup'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
