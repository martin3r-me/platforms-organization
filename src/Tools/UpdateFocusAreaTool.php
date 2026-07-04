<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationFocusArea;

class UpdateFocusAreaTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.focus_areas.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/focus-areas/{id} - Aktualisiert einen Fokusraum. entity_id-Aenderung ist erlaubt, wird aber vom Save-Hook auf Carrier validiert.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id'                             => ['type' => 'integer', 'description' => 'PFLICHT.'],
                'title'                          => ['type' => 'string'],
                'description'                    => ['type' => 'string'],
                'content'                        => ['type' => 'string'],
                'central_question_vision_images' => ['type' => 'string'],
                'central_question_obstacles'     => ['type' => 'string'],
                'central_question_milestones'    => ['type' => 'string'],
                'order'                          => ['type' => 'integer'],
                'forecast_id'                    => ['type' => 'integer'],
                'entity_id'                      => ['type' => 'integer'],
            ],
            'required' => ['id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $id = (int) ($arguments['id'] ?? 0);
            $fa = OrganizationFocusArea::find($id);
            if (!$fa) {
                return ToolResult::error('NOT_FOUND', "FocusArea #{$id} nicht gefunden.");
            }
            foreach ([
                'title', 'description', 'content',
                'central_question_vision_images', 'central_question_obstacles', 'central_question_milestones',
                'order', 'forecast_id', 'entity_id',
            ] as $col) {
                if (array_key_exists($col, $arguments)) {
                    $fa->{$col} = $arguments[$col];
                }
            }
            $fa->save();
            return ToolResult::success([
                'id' => $fa->id, 'title' => $fa->title, 'order' => $fa->order,
                'entity_id' => $fa->entity_id, 'forecast_id' => $fa->forecast_id,
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
            'tags'          => ['organization', 'strategy', 'focus_areas', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
