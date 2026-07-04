<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationFocusArea;

class DeleteFocusAreaTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.focus_areas.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/focus-areas/{id} - Soft-Delete. Kinder (Zielbilder, Hindernisse, Meilensteine) muessen ggf. separat geloescht werden.';
    }

    public function getSchema(): array
    {
        return ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $id = (int) ($arguments['id'] ?? 0);
            $fa = OrganizationFocusArea::find($id);
            if (!$fa) {
                return ToolResult::error('NOT_FOUND', "FocusArea #{$id} nicht gefunden.");
            }
            $fa->delete();
            return ToolResult::success(['id' => $id, 'message' => 'Soft-deleted.']);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'strategy', 'focus_areas', 'delete'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'destructive',
            'idempotent'    => true,
        ];
    }
}
