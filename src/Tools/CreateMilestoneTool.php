<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationMilestone;

class CreateMilestoneTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.milestones.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/milestones - Erstellt einen Meilenstein (Waypoint auf der Transformations-Map) in einem Fokusraum. '
            . 'entity_id wird automatisch aus focus_area gezogen. target_year+target_quarter sind die primaeren Map-Achsen.';
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
                'target_date'      => ['type' => 'string', 'description' => 'Optional: exaktes Datum YYYY-MM-DD.'],
                'target_year'      => ['type' => 'integer', 'description' => 'Optional: Zieljahr fuer Map-Positionierung.'],
                'target_quarter'   => ['type' => 'integer', 'description' => 'Optional: 1-4.'],
                'order'            => ['type' => 'integer', 'description' => 'Optional (default 0).'],
                'team_id'          => ['type' => 'integer', 'description' => 'Optional.'],
                'user_id'          => ['type' => 'integer', 'description' => 'Optional.'],
                'created_at'       => ['type' => 'string', 'description' => 'Optional.'],
                'updated_at'       => ['type' => 'string', 'description' => 'Optional.'],
                'deleted_at'       => ['type' => 'string', 'description' => 'Optional.'],
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
                'target_date'      => $arguments['target_date'] ?? null,
                'target_year'      => isset($arguments['target_year'])    ? (int) $arguments['target_year']    : null,
                'target_quarter'   => isset($arguments['target_quarter']) ? (int) $arguments['target_quarter'] : null,
                'order'            => (int) ($arguments['order'] ?? 0),
                'team_id'          => $teamId,
                'user_id'          => isset($arguments['user_id']) ? (int) $arguments['user_id'] : ($context->user?->id ?? null),
            ];
            foreach (['created_at', 'updated_at', 'deleted_at'] as $ts) {
                if (!empty($arguments[$ts])) {
                    $data[$ts] = $arguments[$ts];
                }
            }

            $m = OrganizationMilestone::create($data);

            return ToolResult::success([
                'id'             => $m->id,
                'uuid'           => $m->uuid,
                'entity_id'      => $m->entity_id,
                'focus_area_id'  => $m->focus_area_id,
                'title'          => $m->title,
                'target_date'    => $m->target_date?->toDateString(),
                'target_year'    => $m->target_year,
                'target_quarter' => $m->target_quarter,
                'team_id'        => $m->team_id,
                'message'        => 'Meilenstein erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'strategy', 'milestones', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => false,
        ];
    }
}
