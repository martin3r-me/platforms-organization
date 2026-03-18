<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityRelationship;
use Platform\Organization\Models\OrganizationEntityRelationType;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class CreateEntityRelationshipTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.entity_relationships.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/entity-relationships - Erstellt eine Beziehung zwischen zwei Entities. Nutze organization.entities.GET und organization.relation_types.GET um IDs zu ermitteln.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (wird auf Root/Elterteam aufgelöst). Default: Team aus Kontext.',
                ],
                'from_entity_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID der Quell-Entity. Nutze organization.entities.GET.',
                ],
                'to_entity_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID der Ziel-Entity. Nutze organization.entities.GET.',
                ],
                'relation_type_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID des Relation Types. Nutze organization.relation_types.GET.',
                ],
                'valid_from' => [
                    'type' => 'string',
                    'description' => 'Optional: Gültig ab (YYYY-MM-DD).',
                ],
                'valid_to' => [
                    'type' => 'string',
                    'description' => 'Optional: Gültig bis (YYYY-MM-DD).',
                ],
                'metadata' => [
                    'type' => 'object',
                    'description' => 'Optional: Freies JSON-Metadatenobjekt.',
                ],
            ],
            'required' => ['from_entity_id', 'to_entity_id', 'relation_type_id'],
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

            $fromEntityId = $arguments['from_entity_id'] ?? null;
            $toEntityId = $arguments['to_entity_id'] ?? null;
            $relationTypeId = $arguments['relation_type_id'] ?? null;

            if (!$fromEntityId) {
                return ToolResult::error('VALIDATION_ERROR', 'from_entity_id ist erforderlich.');
            }
            if (!$toEntityId) {
                return ToolResult::error('VALIDATION_ERROR', 'to_entity_id ist erforderlich.');
            }
            if (!$relationTypeId) {
                return ToolResult::error('VALIDATION_ERROR', 'relation_type_id ist erforderlich.');
            }

            $fromEntityId = (int) $fromEntityId;
            $toEntityId = (int) $toEntityId;
            $relationTypeId = (int) $relationTypeId;

            if ($fromEntityId === $toEntityId) {
                return ToolResult::error('VALIDATION_ERROR', 'from_entity_id und to_entity_id dürfen nicht identisch sein.');
            }

            // Validate from_entity exists and belongs to team
            $fromEntity = OrganizationEntity::query()
                ->where('id', $fromEntityId)
                ->where('team_id', $rootTeamId)
                ->first();
            if (!$fromEntity) {
                return ToolResult::error('NOT_FOUND', "Quell-Entity mit ID {$fromEntityId} nicht gefunden im Team. Nutze organization.entities.GET.");
            }

            // Validate to_entity exists and belongs to team
            $toEntity = OrganizationEntity::query()
                ->where('id', $toEntityId)
                ->where('team_id', $rootTeamId)
                ->first();
            if (!$toEntity) {
                return ToolResult::error('NOT_FOUND', "Ziel-Entity mit ID {$toEntityId} nicht gefunden im Team. Nutze organization.entities.GET.");
            }

            // Validate relation type exists and is active
            $relationType = OrganizationEntityRelationType::query()
                ->where('id', $relationTypeId)
                ->first();
            if (!$relationType) {
                return ToolResult::error('NOT_FOUND', "Relation Type mit ID {$relationTypeId} nicht gefunden. Nutze organization.relation_types.GET.");
            }
            if (!$relationType->is_active) {
                return ToolResult::error('VALIDATION_ERROR', "Relation Type '{$relationType->name}' ist inaktiv.");
            }

            // Check for duplicate
            $exists = OrganizationEntityRelationship::query()
                ->where('from_entity_id', $fromEntityId)
                ->where('to_entity_id', $toEntityId)
                ->where('relation_type_id', $relationTypeId)
                ->exists();
            if ($exists) {
                return ToolResult::error('VALIDATION_ERROR', 'Diese Relation existiert bereits.');
            }

            // Validate dates
            $validFrom = isset($arguments['valid_from']) && $arguments['valid_from'] !== '' ? $arguments['valid_from'] : null;
            $validTo = isset($arguments['valid_to']) && $arguments['valid_to'] !== '' ? $arguments['valid_to'] : null;

            if ($validFrom && $validTo && $validTo < $validFrom) {
                return ToolResult::error('VALIDATION_ERROR', 'valid_to muss nach valid_from liegen.');
            }

            $relationship = OrganizationEntityRelationship::create([
                'from_entity_id' => $fromEntityId,
                'to_entity_id' => $toEntityId,
                'relation_type_id' => $relationTypeId,
                'valid_from' => $validFrom,
                'valid_to' => $validTo,
                'team_id' => $rootTeamId,
                'user_id' => $context->user?->id,
                'metadata' => (isset($arguments['metadata']) && is_array($arguments['metadata'])) ? $arguments['metadata'] : null,
            ]);

            $relationship->load(['fromEntity', 'toEntity', 'relationType']);

            return ToolResult::success([
                'id' => $relationship->id,
                'uuid' => $relationship->uuid,
                'from_entity_id' => $relationship->from_entity_id,
                'from_entity_name' => $relationship->fromEntity?->name,
                'to_entity_id' => $relationship->to_entity_id,
                'to_entity_name' => $relationship->toEntity?->name,
                'relation_type_id' => $relationship->relation_type_id,
                'relation_type_name' => $relationship->relationType?->name,
                'valid_from' => $relationship->valid_from?->toDateString(),
                'valid_to' => $relationship->valid_to?->toDateString(),
                'metadata' => $relationship->metadata,
                'summary' => $relationship->summary,
                'message' => 'Entity Relationship erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Entity Relationships: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'entity_relationships', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
