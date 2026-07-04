<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationMilestone;

class DeleteMilestoneTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.milestones.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/milestones/{id} - Soft-Delete eines Meilensteins.';
    }

    public function getSchema(): array
    {
        return ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $id = (int) ($arguments['id'] ?? 0);
            $m = OrganizationMilestone::find($id);
            if (!$m) {
                return ToolResult::error('NOT_FOUND', "Milestone #{$id} nicht gefunden.");
            }
            $m->delete();
            return ToolResult::success(['id' => $id, 'message' => 'Soft-deleted.']);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'strategy', 'milestones', 'delete'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'destructive',
            'idempotent'    => true,
        ];
    }
}
