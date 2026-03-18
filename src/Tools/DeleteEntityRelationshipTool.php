<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationEntityRelationship;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class DeleteEntityRelationshipTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.entity_relationships.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/entity-relationships/{id} - Löscht eine Entity Relationship (soft delete).';
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
            ],
            'required' => ['relationship_id'],
        ]);
    }

    protected function getAccessAction(): string
    {
        return 'delete';
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

            $rel->delete();

            return ToolResult::success([
                'id' => $rel->id,
                'message' => 'Entity Relationship gelöscht (soft delete).',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen des Entity Relationships: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'entity_relationships', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
