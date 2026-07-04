<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationVisionImage;

class UpdateVisionImageTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.vision_images.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/vision-images/{id} - Aktualisiert ein Zukunftsbild. entity_id wird bei jedem Save aus focus_area.entity_id abgeleitet (nicht manuell setzbar).';
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
                'focus_area_id'    => ['type' => 'integer', 'description' => 'Wechsel zu anderem Fokusraum (entity_id folgt).'],
            ],
            'required' => ['id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $id = (int) ($arguments['id'] ?? 0);
            $vi = OrganizationVisionImage::find($id);
            if (!$vi) {
                return ToolResult::error('NOT_FOUND', "VisionImage #{$id} nicht gefunden.");
            }
            foreach (['title', 'description', 'central_question', 'order', 'focus_area_id'] as $col) {
                if (array_key_exists($col, $arguments)) {
                    $vi->{$col} = $arguments[$col];
                }
            }
            $vi->save();
            return ToolResult::success([
                'id' => $vi->id, 'title' => $vi->title, 'order' => $vi->order,
                'focus_area_id' => $vi->focus_area_id, 'entity_id' => $vi->entity_id,
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
            'tags'          => ['organization', 'strategy', 'vision_images', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
