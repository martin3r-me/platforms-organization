<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityRelationship;
use Platform\Organization\Models\OrganizationEntityRelationType;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class UpdateEntityRelationshipTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.entity_relationships.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/entity-relationships/{id} - Aktualisiert eine Entity Relationship. Nutze organization.entity_relationships.GET um IDs zu ermitteln.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (wird auf Root/Elterteam aufgelöst). Default: Team aus Kontext.',
                ],
                'relationship_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID der Relationship.',
                ],
                'from_entity_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Neue Quell-Entity ID.',
                ],
                'to_entity_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Neue Ziel-Entity ID.',
                ],
                'relation_type_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Neuer Relation Type ID.',
                ],
                'valid_from' => [
                    'type' => 'string',
                    'description' => 'Optional: Gültig ab (YYYY-MM-DD, "" zum Leeren).',
                ],
                'valid_to' => [
                    'type' => 'string',
                    'description' => 'Optional: Gültig bis (YYYY-MM-DD, "" zum Leeren).',
                ],
                'metadata' => [
                    'type' => 'object',
                    'description' => 'Optional: Metadatenobjekt (null zum Leeren).',
                ],
            ],
            'required' => ['relationship_id'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $found = $this->validateAndFindModel(
                $arguments,
                $context,
                'relationship_id',
                OrganizationEntityRelationship::class,
                'NOT_FOUND',
                'Entity Relationship nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationEntityRelationship $rel */
            $rel = $found['model'];

            if ((int) $rel->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Relationship gehört nicht zum Root/Elterteam des angegebenen Teams.');
            }

            $update = [];

            if (array_key_exists('from_entity_id', $arguments) && $arguments['from_entity_id'] !== null) {
                $fromId = (int) $arguments['from_entity_id'];
                $fromEntity = OrganizationEntity::query()
                    ->where('id', $fromId)
                    ->where('team_id', $rootTeamId)
                    ->first();
                if (!$fromEntity) {
                    return ToolResult::error('NOT_FOUND', "Quell-Entity mit ID {$fromId} nicht gefunden im Team.");
                }
                $update['from_entity_id'] = $fromId;
            }

            if (array_key_exists('to_entity_id', $arguments) && $arguments['to_entity_id'] !== null) {
                $toId = (int) $arguments['to_entity_id'];
                $toEntity = OrganizationEntity::query()
                    ->where('id', $toId)
                    ->where('team_id', $rootTeamId)
                    ->first();
                if (!$toEntity) {
                    return ToolResult::error('NOT_FOUND', "Ziel-Entity mit ID {$toId} nicht gefunden im Team.");
                }
                $update['to_entity_id'] = $toId;
            }

            if (array_key_exists('relation_type_id', $arguments) && $arguments['relation_type_id'] !== null) {
                $rtId = (int) $arguments['relation_type_id'];
                $relationType = OrganizationEntityRelationType::query()->where('id', $rtId)->first();
                if (!$relationType) {
                    return ToolResult::error('NOT_FOUND', "Relation Type mit ID {$rtId} nicht gefunden.");
                }
                if (!$relationType->is_active) {
                    return ToolResult::error('VALIDATION_ERROR', "Relation Type '{$relationType->name}' ist inaktiv.");
                }
                $update['relation_type_id'] = $rtId;
            }

            // Self-reference check
            $finalFrom = $update['from_entity_id'] ?? $rel->from_entity_id;
            $finalTo = $update['to_entity_id'] ?? $rel->to_entity_id;
            if ($finalFrom === $finalTo) {
                return ToolResult::error('VALIDATION_ERROR', 'from_entity_id und to_entity_id dürfen nicht identisch sein.');
            }

            // Duplicate check (only if key fields changed)
            if (isset($update['from_entity_id']) || isset($update['to_entity_id']) || isset($update['relation_type_id'])) {
                $finalRtId = $update['relation_type_id'] ?? $rel->relation_type_id;
                $exists = OrganizationEntityRelationship::query()
                    ->where('from_entity_id', $finalFrom)
                    ->where('to_entity_id', $finalTo)
                    ->where('relation_type_id', $finalRtId)
                    ->where('id', '!=', $rel->id)
                    ->exists();
                if ($exists) {
                    return ToolResult::error('VALIDATION_ERROR', 'Diese Relation existiert bereits.');
                }
            }

            if (array_key_exists('valid_from', $arguments)) {
                $vf = (string) ($arguments['valid_from'] ?? '');
                $update['valid_from'] = $vf === '' ? null : $vf;
            }
            if (array_key_exists('valid_to', $arguments)) {
                $vt = (string) ($arguments['valid_to'] ?? '');
                $update['valid_to'] = $vt === '' ? null : $vt;
            }

            // Date validation
            $finalValidFrom = $update['valid_from'] ?? $rel->valid_from?->toDateString();
            $finalValidTo = $update['valid_to'] ?? $rel->valid_to?->toDateString();
            if ($finalValidFrom && $finalValidTo && $finalValidTo < $finalValidFrom) {
                return ToolResult::error('VALIDATION_ERROR', 'valid_to muss nach valid_from liegen.');
            }

            if (array_key_exists('metadata', $arguments)) {
                $update['metadata'] = (isset($arguments['metadata']) && is_array($arguments['metadata'])) ? $arguments['metadata'] : null;
            }

            if (!empty($update)) {
                $rel->update($update);
            }
            $rel->refresh();
            $rel->load(['fromEntity', 'toEntity', 'relationType']);

            return ToolResult::success([
                'id' => $rel->id,
                'uuid' => $rel->uuid,
                'from_entity_id' => $rel->from_entity_id,
                'from_entity_name' => $rel->fromEntity?->name,
                'to_entity_id' => $rel->to_entity_id,
                'to_entity_name' => $rel->toEntity?->name,
                'relation_type_id' => $rel->relation_type_id,
                'relation_type_name' => $rel->relationType?->name,
                'valid_from' => $rel->valid_from?->toDateString(),
                'valid_to' => $rel->valid_to?->toDateString(),
                'metadata' => $rel->metadata,
                'summary' => $rel->summary,
                'message' => 'Entity Relationship erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Entity Relationships: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'entity_relationships', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
