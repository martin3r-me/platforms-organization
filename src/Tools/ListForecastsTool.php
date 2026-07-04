<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationForecast;

class ListForecastsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;

    public function getName(): string
    {
        return 'organization.forecasts.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/forecasts - Listet Forecasts. Filter: entity_id, team_id.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['entity_id', 'team_id']),
            [
                'properties' => [
                    'entity_id' => ['type' => 'integer', 'description' => 'Optional: Carrier-Entity filtern.'],
                    'team_id'   => ['type' => 'integer', 'description' => 'Optional: Team-Scope.'],
                ],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $q = OrganizationForecast::query()->whereNull('deleted_at');

            if (!empty($arguments['entity_id'])) {
                $q->where('entity_id', (int) $arguments['entity_id']);
            }
            if (!empty($arguments['team_id'])) {
                $q->where('team_id', (int) $arguments['team_id']);
            }

            $this->applyStandardFilters($q, $arguments, ['entity_id', 'team_id', 'created_at']);
            $this->applyStandardSearch($q, $arguments, ['title']);
            $this->applyStandardSort($q, $arguments, ['id', 'target_date', 'created_at'], 'target_date', 'desc');

            $result = $this->applyStandardPaginationResult($q, $arguments);
            $items = $result['data']->map(fn ($f) => [
                'id'                 => $f->id,
                'uuid'               => $f->uuid,
                'entity_id'          => $f->entity_id,
                'title'              => $f->title,
                'target_date'        => $f->target_date?->toDateString(),
                'current_version_id' => $f->current_version_id,
                'team_id'            => $f->team_id,
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
            'tags'          => ['organization', 'strategy', 'forecasts', 'lookup'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
