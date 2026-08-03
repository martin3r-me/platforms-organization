<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationStrategy;

class ListStrategiesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;

    public function getName(): string
    {
        return 'organization.strategies.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/strategies - Listet Strategie-Aggregate (1:1 pro Carrier-Entity). '
            . 'Filter: entity_id, status (draft|active|archived), team_id.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['entity_id', 'status', 'team_id']),
            [
                'properties' => [
                    'entity_id' => ['type' => 'integer', 'description' => 'Optional: Carrier-Entity.'],
                    'status'    => ['type' => 'string', 'description' => 'Optional: draft|active|archived.'],
                    'team_id'   => ['type' => 'integer', 'description' => 'Optional: Team-Scope.'],
                ],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $q = OrganizationStrategy::query()->whereNull('deleted_at')->with('entity:id,name', 'owner:id,name');

            if (!empty($arguments['entity_id'])) {
                $q->where('entity_id', (int) $arguments['entity_id']);
            }
            if (!empty($arguments['status'])) {
                $q->where('status', (string) $arguments['status']);
            }
            if (!empty($arguments['team_id'])) {
                $q->where('team_id', (int) $arguments['team_id']);
            }

            $this->applyStandardFilters($q, $arguments, ['entity_id', 'status', 'team_id', 'created_at']);
            $this->applyStandardSort($q, $arguments, ['id', 'status', 'version', 'created_at'], 'id', 'asc');

            $result = $this->applyStandardPaginationResult($q, $arguments);
            $items = $result['data']->map(fn ($s) => [
                'id'           => $s->id,
                'uuid'         => $s->uuid,
                'entity_id'    => $s->entity_id,
                'entity_name'  => $s->entity?->name,
                'status'       => $s->status,
                'version'      => $s->version,
                'published_at' => $s->published_at?->toIso8601String(),
                'owner'        => $s->owner?->name,
                'team_id'      => $s->team_id,
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
            'tags'          => ['organization', 'strategy', 'strategies', 'lookup'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
