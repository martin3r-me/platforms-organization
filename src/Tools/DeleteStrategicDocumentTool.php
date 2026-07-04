<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationStrategicDocument;

class DeleteStrategicDocumentTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.strategic_documents.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/strategic-documents/{id} - Soft-Delete eines Mission/Vision-Dokuments.';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => ['id' => ['type' => 'integer', 'description' => 'PFLICHT.']],
            'required'   => ['id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $id = (int) ($arguments['id'] ?? 0);
            $doc = OrganizationStrategicDocument::find($id);
            if (!$doc) {
                return ToolResult::error('NOT_FOUND', "StrategicDocument #{$id} nicht gefunden.");
            }
            $doc->delete();
            return ToolResult::success(['id' => $id, 'message' => 'Soft-deleted.']);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'strategy', 'strategic_documents', 'delete'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'destructive',
            'idempotent'    => true,
        ];
    }
}
