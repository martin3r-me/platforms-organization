<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationInterlinkType;
use Platform\Organization\Models\OrganizationInterlink;

class DeleteInterlinkTypeTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;

    public function getName(): string
    {
        return 'organization.interlink_types.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/interlink-types/{id} - Löscht einen Interlink-Typ. Verweigert wenn noch Interlinks diesen Typ verwenden.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'interlink_type_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Interlink-Typs (ERFORDERLICH). Nutze organization.interlink_types.GET.',
                ],
            ],
            'required' => ['interlink_type_id'],
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
                'interlink_type_id',
                OrganizationInterlinkType::class,
                'NOT_FOUND',
                'Interlink-Typ nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationInterlinkType $type */
            $type = $found['model'];

            $hasInterlinks = OrganizationInterlink::query()
                ->where('type_id', $type->id)
                ->exists();
            if ($hasInterlinks) {
                return ToolResult::error('VALIDATION_ERROR', 'Interlink-Typ wird noch von Interlinks verwendet und kann nicht gelöscht werden. Setze stattdessen is_active=false.');
            }

            $type->delete();

            return ToolResult::success([
                'id' => $type->id,
                'message' => 'Interlink-Typ erfolgreich gelöscht.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen des Interlink-Typs: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'interlink_types', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
