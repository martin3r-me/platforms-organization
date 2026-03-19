<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationEntityRelationship;
use Platform\Organization\Models\OrganizationEntityRelationshipInterlink;
use Platform\Organization\Models\OrganizationInterlink;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class LinkInterlinkToRelationshipTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.entity_relationship_interlinks.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/entity-relationship-interlinks - Verknüpft einen Interlink mit einer Entity Relationship. Nutze organization.entity_relationships.GET und organization.interlinks.GET um IDs zu ermitteln.';
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
                'entity_relationship_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID der Entity Relationship. Nutze organization.entity_relationships.GET.',
                ],
                'interlink_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID des Interlinks. Nutze organization.interlinks.GET.',
                ],
                'note' => [
                    'type' => 'string',
                    'description' => 'Optional: Notiz zur Zuordnung.',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: aktiv/inaktiv. Default: true.',
                    'default' => true,
                ],
                'metadata' => [
                    'type' => 'object',
                    'description' => 'Optional: Freies JSON-Metadatenobjekt.',
                ],
            ],
            'required' => ['entity_relationship_id', 'interlink_id'],
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

            $relationshipId = $arguments['entity_relationship_id'] ?? null;
            $interlinkId = $arguments['interlink_id'] ?? null;

            if (!$relationshipId) {
                return ToolResult::error('VALIDATION_ERROR', 'entity_relationship_id ist erforderlich.');
            }
            if (!$interlinkId) {
                return ToolResult::error('VALIDATION_ERROR', 'interlink_id ist erforderlich.');
            }

            $relationshipId = (int) $relationshipId;
            $interlinkId = (int) $interlinkId;

            $relationship = OrganizationEntityRelationship::query()
                ->where('id', $relationshipId)
                ->where('team_id', $rootTeamId)
                ->first();
            if (!$relationship) {
                return ToolResult::error('NOT_FOUND', "Entity Relationship mit ID {$relationshipId} nicht gefunden im Team.");
            }

            $interlink = OrganizationInterlink::query()
                ->where('id', $interlinkId)
                ->where('team_id', $rootTeamId)
                ->first();
            if (!$interlink) {
                return ToolResult::error('NOT_FOUND', "Interlink mit ID {$interlinkId} nicht gefunden im Team.");
            }

            $exists = OrganizationEntityRelationshipInterlink::query()
                ->where('entity_relationship_id', $relationshipId)
                ->where('interlink_id', $interlinkId)
                ->exists();
            if ($exists) {
                return ToolResult::error('DUPLICATE', 'Diese Zuordnung existiert bereits.');
            }

            $pivot = OrganizationEntityRelationshipInterlink::create([
                'entity_relationship_id' => $relationshipId,
                'interlink_id' => $interlinkId,
                'note' => (array_key_exists('note', $arguments) && $arguments['note'] !== '') ? (string)$arguments['note'] : null,
                'is_active' => (bool)($arguments['is_active'] ?? true),
                'team_id' => $rootTeamId,
                'user_id' => $context->user?->id,
                'metadata' => (isset($arguments['metadata']) && is_array($arguments['metadata'])) ? $arguments['metadata'] : null,
            ]);

            return ToolResult::success([
                'id' => $pivot->id,
                'uuid' => $pivot->uuid,
                'entity_relationship_id' => $pivot->entity_relationship_id,
                'interlink_id' => $pivot->interlink_id,
                'interlink_name' => $interlink->name,
                'note' => $pivot->note,
                'is_active' => (bool) $pivot->is_active,
                'message' => 'Interlink erfolgreich mit Relationship verknüpft.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Verknüpfen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'entity_relationship_interlinks', 'link'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
