<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationVisionImage;

class CreateVisionImageTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.vision_images.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/vision-images - Erstellt ein Zukunftsbild in einem Fokusraum. '
            . 'entity_id wird automatisch aus focus_area gezogen (Save-Hook), nicht separat angeben.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'focus_area_id'    => ['type' => 'integer', 'description' => 'Fokusraum-ID (PFLICHT).'],
                'title'            => ['type' => 'string', 'description' => 'Titel (PFLICHT).'],
                'description'      => ['type' => 'string', 'description' => 'Optional.'],
                'central_question' => ['type' => 'string', 'description' => 'Optional.'],
                'order'            => ['type' => 'integer', 'description' => 'Optional (default 0).'],
                'team_id'          => ['type' => 'integer', 'description' => 'Optional: Team-Scope.'],
                'user_id'          => ['type' => 'integer', 'description' => 'Optional: Ersteller.'],
                'created_at'       => ['type' => 'string', 'description' => 'Optional: Erstellzeit.'],
                'updated_at'       => ['type' => 'string', 'description' => 'Optional: Aenderungszeit.'],
                'deleted_at'       => ['type' => 'string', 'description' => 'Optional: Soft-Delete-Zeit.'],
            ],
            'required' => ['focus_area_id', 'title'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $focusAreaId = (int) ($arguments['focus_area_id'] ?? 0);
            if ($focusAreaId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'focus_area_id ist erforderlich.');
            }
            $teamId = (int) ($arguments['team_id'] ?? $context->team?->id ?? 0);
            if ($teamId <= 0) {
                return ToolResult::error('MISSING_TEAM', 'team_id konnte nicht aufgeloest werden.');
            }

            $data = [
                'focus_area_id'    => $focusAreaId,
                'title'            => trim((string) $arguments['title']),
                'description'      => $arguments['description'] ?? null,
                'central_question' => $arguments['central_question'] ?? null,
                'order'            => (int) ($arguments['order'] ?? 0),
                'team_id'          => $teamId,
                'user_id'          => isset($arguments['user_id']) ? (int) $arguments['user_id'] : ($context->user?->id ?? null),
            ];
            foreach (['created_at', 'updated_at', 'deleted_at'] as $ts) {
                if (!empty($arguments[$ts])) {
                    $data[$ts] = $arguments[$ts];
                }
            }

            $vi = OrganizationVisionImage::create($data);

            return ToolResult::success([
                'id'            => $vi->id,
                'uuid'          => $vi->uuid,
                'entity_id'     => $vi->entity_id,
                'focus_area_id' => $vi->focus_area_id,
                'title'         => $vi->title,
                'order'         => $vi->order,
                'team_id'       => $vi->team_id,
                'message'       => 'Zukunftsbild erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'strategy', 'vision_images', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => false,
        ];
    }
}
