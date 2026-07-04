<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationFocusArea;

class ListFocusAreasTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;

    public function getName(): string
    {
        return 'organization.focus_areas.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/focus-areas - Listet Fokusraeume. Filter: entity_id, forecast_id, team_id.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['entity_id', 'forecast_id', 'team_id']),
            [
                'properties' => [
                    'entity_id'   => ['type' => 'integer', 'description' => 'Optional: Carrier-Entity.'],
                    'forecast_id' => ['type' => 'integer', 'description' => 'Optional: Forecast.'],
                    'team_id'     => ['type' => 'integer', 'description' => 'Optional: Team-Scope.'],
                ],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $q = OrganizationFocusArea::query()->whereNull('deleted_at');

            foreach (['entity_id', 'forecast_id', 'team_id'] as $col) {
                if (!empty($arguments[$col])) {
                    $q->where($col, (int) $arguments[$col]);
                }
            }

            $this->applyStandardFilters($q, $arguments, ['entity_id', 'forecast_id', 'team_id', 'created_at']);
            $this->applyStandardSearch($q, $arguments, ['title', 'description', 'content']);
            $this->applyStandardSort($q, $arguments, ['order', 'id', 'created_at'], 'order', 'asc');

            $result = $this->applyStandardPaginationResult($q, $arguments);
            $items = $result['data']->map(fn ($fa) => [
                'id'          => $fa->id,
                'uuid'        => $fa->uuid,
                'entity_id'   => $fa->entity_id,
                'forecast_id' => $fa->forecast_id,
                'title'       => $fa->title,
                'description' => $fa->description,
                'order'       => $fa->order,
                'team_id'     => $fa->team_id,
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
            'tags'          => ['organization', 'strategy', 'focus_areas', 'lookup'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
