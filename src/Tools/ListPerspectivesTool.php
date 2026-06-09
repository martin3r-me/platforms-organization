<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Services\PerspectiveService;

/**
 * Perspektive = aus Sicht welcher Carrier-Entity wir lesen.
 * Listet alle Carrier-Entities des Teams als waehlbare Perspektiven.
 */
class ListPerspectivesTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.perspectives.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/perspectives - Listet Carrier-Entities des Teams als waehlbare Perspektiven. Eine Perspektive ist die Carrier-Entity, aus deren Sicht VSM-Daten gelesen werden. Aktive Perspektive beeinflusst nachfolgende Organization-Tool-Aufrufe.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $context->team?->id;
            $userId = $context->user?->id;
            if (!$teamId) {
                return ToolResult::error('MISSING_TEAM', 'Kein Team im Kontext.');
            }

            $carriers = PerspectiveService::getCarriersForTeam($teamId);
            $active = PerspectiveService::getActiveEntity($teamId, $userId);

            $items = $carriers->map(fn ($e) => [
                'entity_id' => $e->id,
                'uuid' => $e->uuid,
                'name' => $e->name,
                'code' => $e->code,
                'type' => $e->type?->name,
                'is_root' => $e->parent_entity_id === null,
                'is_active' => $active && $e->id === $active->id,
            ])->values()->toArray();

            return ToolResult::success([
                'data' => $items,
                'count' => count($items),
                'active_perspective_entity_id' => $active?->id,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Perspektiven: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'perspectives', 'vsm', 'carrier'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
