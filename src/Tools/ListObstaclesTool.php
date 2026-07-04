<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationObstacle;

class ListObstaclesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;

    public function getName(): string
    {
        return 'organization.obstacles.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/obstacles - Listet Hindernisse. Filter: focus_area_id, entity_id, team_id.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['focus_area_id', 'entity_id', 'team_id']),
            [
                'properties' => [
                    'focus_area_id' => ['type' => 'integer', 'description' => 'Optional.'],
                    'entity_id'     => ['type' => 'integer', 'description' => 'Optional.'],
                    'team_id'       => ['type' => 'integer', 'description' => 'Optional.'],
                ],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $q = OrganizationObstacle::query()->whereNull('deleted_at');

            foreach (['focus_area_id', 'entity_id', 'team_id'] as $col) {
                if (!empty($arguments[$col])) {
                    $q->where($col, (int) $arguments[$col]);
                }
            }

            $this->applyStandardFilters($q, $arguments, ['focus_area_id', 'entity_id', 'team_id', 'created_at']);
            $this->applyStandardSearch($q, $arguments, ['title', 'description']);
            $this->applyStandardSort($q, $arguments, ['order', 'id', 'created_at'], 'order', 'asc');

            $result = $this->applyStandardPaginationResult($q, $arguments);
            $items = $result['data']->map(fn ($ob) => [
                'id'            => $ob->id,
                'uuid'          => $ob->uuid,
                'entity_id'     => $ob->entity_id,
                'focus_area_id' => $ob->focus_area_id,
                'title'         => $ob->title,
                'order'         => $ob->order,
                'team_id'       => $ob->team_id,
            ])->values()->toArray();

            return ToolResult::success([
                'data'       => $items,
                'pagination' => $result['pagination'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'read',
            'tags'          => ['organization', 'strategy', 'obstacles', 'lookup'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
