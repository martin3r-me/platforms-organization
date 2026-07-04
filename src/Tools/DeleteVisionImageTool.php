<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationVisionImage;

class DeleteVisionImageTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.vision_images.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/vision-images/{id} - Soft-Delete eines Zukunftsbildes.';
    }

    public function getSchema(): array
    {
        return ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $id = (int) ($arguments['id'] ?? 0);
            $vi = OrganizationVisionImage::find($id);
            if (!$vi) {
                return ToolResult::error('NOT_FOUND', "VisionImage #{$id} nicht gefunden.");
            }
            $vi->delete();
            return ToolResult::success(['id' => $id, 'message' => 'Soft-deleted.']);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'strategy', 'vision_images', 'delete'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'destructive',
            'idempotent'    => true,
        ];
    }
}
