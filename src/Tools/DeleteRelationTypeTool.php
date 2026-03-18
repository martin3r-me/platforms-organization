<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationEntityRelationType;
use Platform\Organization\Models\OrganizationEntityRelationship;

class DeleteRelationTypeTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;

    public function getName(): string
    {
        return 'organization.relation_types.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/relation-types/{id} - Löscht einen Relation Type. Verweigert wenn der Relation Type noch von Relationships verwendet wird.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'relation_type_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Relation Types (ERFORDERLICH). Nutze organization.relation_types.GET.',
                ],
            ],
            'required' => ['relation_type_id'],
        ]);
    }

    protected function getAccessAction(): string
    {
        return 'delete';
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $found = $this->validateAndFindModel(
                $arguments,
                $context,
                'relation_type_id',
                OrganizationEntityRelationType::class,
                'NOT_FOUND',
                'Relation Type nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationEntityRelationType $rt */
            $rt = $found['model'];

            // Safety: don't delete if relation type is still used by relationships
            $hasRelationships = OrganizationEntityRelationship::query()
                ->where('relation_type_id', $rt->id)
                ->exists();
            if ($hasRelationships) {
                return ToolResult::error('VALIDATION_ERROR', 'Relation Type wird noch von Relationships verwendet und kann nicht gelöscht werden. Setze stattdessen is_active=false.');
            }

            $rt->delete();

            return ToolResult::success([
                'id' => $rt->id,
                'message' => 'Relation Type erfolgreich gelöscht.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen des Relation Types: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'relation_types', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
