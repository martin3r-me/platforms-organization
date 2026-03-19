<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationEntityRelationshipInterlink;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class UnlinkInterlinkFromRelationshipTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.entity_relationship_interlinks.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/entity-relationship-interlinks - Entfernt die Verknüpfung eines Interlinks von einer Entity Relationship. Entweder per pivot_id oder per entity_relationship_id+interlink_id.';
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
                'pivot_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Direkte ID der Zuordnung (aus organization.entity_relationship_interlinks.GET).',
                ],
                'entity_relationship_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Entity Relationship ID (zusammen mit interlink_id).',
                ],
                'interlink_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Interlink ID (zusammen mit entity_relationship_id).',
                ],
            ],
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

            $pivot = null;

            if (!empty($arguments['pivot_id'])) {
                $pivot = OrganizationEntityRelationshipInterlink::query()
                    ->where('id', (int) $arguments['pivot_id'])
                    ->where('team_id', $rootTeamId)
                    ->first();
            } elseif (!empty($arguments['entity_relationship_id']) && !empty($arguments['interlink_id'])) {
                $pivot = OrganizationEntityRelationshipInterlink::query()
                    ->where('entity_relationship_id', (int) $arguments['entity_relationship_id'])
                    ->where('interlink_id', (int) $arguments['interlink_id'])
                    ->where('team_id', $rootTeamId)
                    ->first();
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'Entweder pivot_id oder entity_relationship_id+interlink_id angeben.');
            }

            if (!$pivot) {
                return ToolResult::error('NOT_FOUND', 'Zuordnung nicht gefunden.');
            }

            $pivot->delete();

            return ToolResult::success([
                'id' => $pivot->id,
                'entity_relationship_id' => $pivot->entity_relationship_id,
                'interlink_id' => $pivot->interlink_id,
                'message' => 'Interlink-Zuordnung erfolgreich entfernt (soft delete).',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Entfernen der Zuordnung: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'entity_relationship_interlinks', 'unlink'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
