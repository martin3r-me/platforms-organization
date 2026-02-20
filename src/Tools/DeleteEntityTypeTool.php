<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityType;

class DeleteEntityTypeTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;

    public function getName(): string
    {
        return 'organization.entity_types.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/entity-types/{id} - Löscht einen Entity Type. Verweigert wenn der Entity Type noch von Entities verwendet wird.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'entity_type_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Entity Types (ERFORDERLICH). Nutze organization.entity_types.GET.',
                ],
            ],
            'required' => ['entity_type_id'],
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
                'entity_type_id',
                OrganizationEntityType::class,
                'NOT_FOUND',
                'Entity Type nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationEntityType $et */
            $et = $found['model'];

            // Safety: don't delete if entity type is still used by entities
            $hasEntities = OrganizationEntity::query()
                ->where('entity_type_id', $et->id)
                ->exists();
            if ($hasEntities) {
                return ToolResult::error('VALIDATION_ERROR', 'Entity Type wird noch von Entities verwendet und kann nicht gelöscht werden. Setze stattdessen is_active=false.');
            }

            $et->delete();

            return ToolResult::success([
                'id' => $et->id,
                'message' => 'Entity Type erfolgreich gelöscht.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen des Entity Types: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'entity_types', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
