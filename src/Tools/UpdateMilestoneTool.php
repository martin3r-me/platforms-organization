<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationMilestone;

class UpdateMilestoneTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.milestones.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/milestones/{id} - Aktualisiert einen Meilenstein. entity_id wird aus focus_area.entity_id abgeleitet.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id'               => ['type' => 'integer', 'description' => 'PFLICHT.'],
                'title'            => ['type' => 'string'],
                'description'      => ['type' => 'string'],
                'central_question' => ['type' => 'string'],
                'target_date'      => ['type' => 'string'],
                'target_year'      => ['type' => 'integer'],
                'target_quarter'   => ['type' => 'integer', 'description' => '1-4'],
                'order'            => ['type' => 'integer'],
                'focus_area_id'    => ['type' => 'integer'],
            ],
            'required' => ['id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $id = (int) ($arguments['id'] ?? 0);
            $m = OrganizationMilestone::find($id);
            if (!$m) {
                return ToolResult::error('NOT_FOUND', "Milestone #{$id} nicht gefunden.");
            }
            foreach ([
                'title', 'description', 'central_question',
                'target_date', 'target_year', 'target_quarter',
                'order', 'focus_area_id',
            ] as $col) {
                if (array_key_exists($col, $arguments)) {
                    $m->{$col} = $arguments[$col];
                }
            }
            $m->save();
            return ToolResult::success([
                'id' => $m->id, 'title' => $m->title,
                'target_year' => $m->target_year, 'target_quarter' => $m->target_quarter,
                'order' => $m->order, 'focus_area_id' => $m->focus_area_id, 'entity_id' => $m->entity_id,
                'message' => 'Aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'strategy', 'milestones', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
