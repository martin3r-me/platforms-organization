<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationDimensionLink;
use Platform\Organization\Models\OrganizationPerspective;
use Platform\Organization\Services\PerspectiveService;

class ListPerspectivesTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.perspectives.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/perspectives - Listet Perspektiven des Teams. Eine Perspektive ist ein benannter Blickwinkel auf die Organisation — bestimmt, welche VSM-Rollen Entities haben. Die aktive Perspektive beeinflusst VSM-Zuweisungen in allen Organization-Tools.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'include_entity_count' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Anzahl der Entities pro Perspektive mitzählen. Default: false.',
                ],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $context->getTeamId();
            $userId = $context->getUserId();

            $perspectives = PerspectiveService::getForTeam($teamId);
            $activePerspective = PerspectiveService::getActive($teamId, $userId);
            $includeCount = (bool) ($arguments['include_entity_count'] ?? false);

            $items = $perspectives->map(function ($p) use ($activePerspective, $includeCount) {
                $item = [
                    'id' => $p->id,
                    'uuid' => $p->uuid,
                    'name' => $p->name,
                    'description' => $p->description,
                    'is_default' => (bool) $p->is_default,
                    'is_active' => $p->id === $activePerspective->id,
                    'created_at' => $p->created_at?->toIso8601String(),
                ];

                if ($includeCount) {
                    $item['entities_in_view'] = OrganizationDimensionLink::where('perspective_id', $p->id)
                        ->where('linkable_type', 'organization_entity')
                        ->distinct('linkable_id')
                        ->count('linkable_id');
                }

                return $item;
            })->values()->toArray();

            return ToolResult::success([
                'data' => $items,
                'count' => count($items),
                'active_perspective_id' => $activePerspective->id,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Perspektiven: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'perspectives', 'vsm', 'dimensions'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
