<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationFocusArea;

class CreateFocusAreaTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.focus_areas.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/focus-areas - Erstellt einen Fokusraum an einer Carrier-Entity, gebunden an einen Forecast. '
            . 'entity_id (Pflicht) muss Carrier sein und passt inhaltlich zum Forecast.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'entity_id'                      => ['type' => 'integer', 'description' => 'Carrier-Entity (PFLICHT).'],
                'forecast_id'                    => ['type' => 'integer', 'description' => 'Forecast-ID (PFLICHT).'],
                'title'                          => ['type' => 'string', 'description' => 'Titel (PFLICHT).'],
                'description'                    => ['type' => 'string', 'description' => 'Optional.'],
                'content'                        => ['type' => 'string', 'description' => 'Optional: Markdown-Content.'],
                'central_question_vision_images' => ['type' => 'string', 'description' => 'Optional: Leitfrage fuer Zukunftsbilder.'],
                'central_question_obstacles'     => ['type' => 'string', 'description' => 'Optional: Leitfrage fuer Hindernisse.'],
                'central_question_milestones'    => ['type' => 'string', 'description' => 'Optional: Leitfrage fuer Meilensteine.'],
                'order'                          => ['type' => 'integer', 'description' => 'Optional: Reihenfolge (default 0).'],
                'team_id'                        => ['type' => 'integer', 'description' => 'Optional: Team-Scope.'],
                'user_id'                        => ['type' => 'integer', 'description' => 'Optional: Ersteller.'],
                'created_at'                     => ['type' => 'string', 'description' => 'Optional: Erstellzeit.'],
                'updated_at'                     => ['type' => 'string', 'description' => 'Optional: Aenderungszeit.'],
                'deleted_at'                     => ['type' => 'string', 'description' => 'Optional: Soft-Delete-Zeit.'],
            ],
            'required' => ['entity_id', 'forecast_id', 'title'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $entityId = (int) ($arguments['entity_id'] ?? 0);
            if ($entityId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'entity_id ist erforderlich.');
            }
            $forecastId = (int) ($arguments['forecast_id'] ?? 0);
            if ($forecastId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'forecast_id ist erforderlich.');
            }
            $teamId = (int) ($arguments['team_id'] ?? $context->team?->id ?? 0);
            if ($teamId <= 0) {
                return ToolResult::error('MISSING_TEAM', 'team_id konnte nicht aufgeloest werden.');
            }

            $data = [
                'entity_id'                      => $entityId,
                'forecast_id'                    => $forecastId,
                'title'                          => trim((string) $arguments['title']),
                'description'                    => $arguments['description'] ?? null,
                'content'                        => $arguments['content'] ?? null,
                'central_question_vision_images' => $arguments['central_question_vision_images'] ?? null,
                'central_question_obstacles'     => $arguments['central_question_obstacles'] ?? null,
                'central_question_milestones'    => $arguments['central_question_milestones'] ?? null,
                'order'                          => (int) ($arguments['order'] ?? 0),
                'team_id'                        => $teamId,
                'user_id'                        => isset($arguments['user_id']) ? (int) $arguments['user_id'] : ($context->user?->id ?? null),
            ];
            foreach (['created_at', 'updated_at', 'deleted_at'] as $ts) {
                if (!empty($arguments[$ts])) {
                    $data[$ts] = $arguments[$ts];
                }
            }

            $fa = OrganizationFocusArea::create($data);

            return ToolResult::success([
                'id'          => $fa->id,
                'uuid'        => $fa->uuid,
                'entity_id'   => $fa->entity_id,
                'forecast_id' => $fa->forecast_id,
                'title'       => $fa->title,
                'order'       => $fa->order,
                'team_id'     => $fa->team_id,
                'message'     => 'Fokusraum erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'strategy', 'focus_areas', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => false,
        ];
    }
}
