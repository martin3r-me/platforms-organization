<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationMilestone;

class ListMilestonesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;

    public function getName(): string
    {
        return 'organization.milestones.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/milestones - Listet Meilensteine der Transformations-Map. Filter: focus_area_id, entity_id, target_year, target_quarter, team_id.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['focus_area_id', 'entity_id', 'target_year', 'target_quarter', 'team_id']),
            [
                'properties' => [
                    'focus_area_id'  => ['type' => 'integer', 'description' => 'Optional.'],
                    'entity_id'      => ['type' => 'integer', 'description' => 'Optional.'],
                    'target_year'    => ['type' => 'integer', 'description' => 'Optional: Jahr filtern.'],
                    'target_quarter' => ['type' => 'integer', 'description' => 'Optional: Quartal (1-4).'],
                    'team_id'        => ['type' => 'integer', 'description' => 'Optional.'],
                ],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $q = OrganizationMilestone::query()->whereNull('deleted_at');

            foreach (['focus_area_id', 'entity_id', 'target_year', 'target_quarter', 'team_id'] as $col) {
                if (!empty($arguments[$col])) {
                    $q->where($col, (int) $arguments[$col]);
                }
            }

            $this->applyStandardFilters($q, $arguments, ['focus_area_id', 'entity_id', 'target_year', 'target_quarter', 'team_id', 'created_at']);
            $this->applyStandardSearch($q, $arguments, ['title', 'description']);
            $this->applyStandardSort($q, $arguments, ['target_date', 'target_year', 'order', 'id', 'created_at'], 'target_date', 'asc');

            $result = $this->applyStandardPaginationResult($q, $arguments);
            $items = $result['data']->map(fn ($m) => [
                'id'             => $m->id,
                'uuid'           => $m->uuid,
                'entity_id'      => $m->entity_id,
                'focus_area_id'  => $m->focus_area_id,
                'title'          => $m->title,
                'target_date'    => $m->target_date?->toDateString(),
                'target_year'    => $m->target_year,
                'target_quarter' => $m->target_quarter,
                'order'          => $m->order,
                'team_id'        => $m->team_id,
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
            'tags'          => ['organization', 'strategy', 'milestones', 'lookup'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
