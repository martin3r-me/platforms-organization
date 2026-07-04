<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationObstacle;

class DeleteObstacleTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.obstacles.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/obstacles/{id} - Soft-Delete eines Hindernisses.';
    }

    public function getSchema(): array
    {
        return ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $id = (int) ($arguments['id'] ?? 0);
            $ob = OrganizationObstacle::find($id);
            if (!$ob) {
                return ToolResult::error('NOT_FOUND', "Obstacle #{$id} nicht gefunden.");
            }
            $ob->delete();
            return ToolResult::success(['id' => $id, 'message' => 'Soft-deleted.']);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'strategy', 'obstacles', 'delete'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'destructive',
            'idempotent'    => true,
        ];
    }
}
