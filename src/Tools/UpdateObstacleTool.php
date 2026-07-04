<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationObstacle;

class UpdateObstacleTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.obstacles.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/obstacles/{id} - Aktualisiert ein Hindernis. entity_id wird aus focus_area.entity_id abgeleitet.';
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
            $ob = OrganizationObstacle::find($id);
            if (!$ob) {
                return ToolResult::error('NOT_FOUND', "Obstacle #{$id} nicht gefunden.");
            }
            foreach (['title', 'description', 'central_question', 'order', 'focus_area_id'] as $col) {
                if (array_key_exists($col, $arguments)) {
                    $ob->{$col} = $arguments[$col];
                }
            }
            $ob->save();
            return ToolResult::success([
                'id' => $ob->id, 'title' => $ob->title, 'order' => $ob->order,
                'focus_area_id' => $ob->focus_area_id, 'entity_id' => $ob->entity_id,
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
            'tags'          => ['organization', 'strategy', 'obstacles', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
