<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\Team;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationCostCenter;

/**
 * Listet Kostenstellen (Organization) deterministisch, damit Agenten IDs nicht raten müssen.
 *
 * Wichtig: Kostenstellen werden oft im Root/Elterteam gepflegt. Daher wird standardmäßig das Root-Team
 * des angegebenen/aktuellen Teams verwendet.
 */
class ListCostCentersTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;

    public function getName(): string
    {
        return 'organization.cost_centers.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/cost-centers - Listet aktive Kostenstellen (code/name) aus dem Root/Elterteam des angegebenen Teams. Nutze dieses Tool bevor du cost_center_id an anderen Modulen setzt (IDs nie raten). Unterstützt filters/search/sort/limit/offset.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'is_active', 'code', 'root_entity_id']),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Default: Team aus Kontext. Es wird automatisch auf das Root/Elterteam aufgelöst.',
                    ],
                    'is_active' => [
                        'type' => 'boolean',
                        'description' => 'Optional: aktive/inaktive Kostenstellen. Default: true.',
                        'default' => true,
                    ],
                    'code' => [
                        'type' => 'string',
                        'description' => 'Optional: Exakter code-Filter.',
                    ],
                    'root_entity_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach root_entity_id (global = null; entity-spezifisch = id).',
                    ],
                ],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $arguments['team_id'] ?? $context->team?->id;
            if ($teamId === 0 || $teamId === '0') {
                $teamId = null;
            }
            if (!$teamId) {
                return ToolResult::error('MISSING_TEAM', 'Kein Team angegeben und kein Team im Kontext gefunden.');
            }

            $team = Team::find((int)$teamId);
            if (!$team) {
                return ToolResult::error('TEAM_NOT_FOUND', 'Team nicht gefunden.');
            }

            // Optional: Membership check (wie in HCM resolveTeam)
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }
            $userHasAccess = $context->user->teams()->where('teams.id', $team->id)->exists();
            if (!$userHasAccess) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf dieses Team.');
            }

            $rootTeamId = (int)$team->getRootTeam()->id;
            $activeOnly = (bool)($arguments['is_active'] ?? true);

            $q = OrganizationCostCenter::query()
                ->where('team_id', $rootTeamId);

            if ($activeOnly) {
                $q->where('is_active', true);
            } elseif (array_key_exists('is_active', $arguments)) {
                $q->where('is_active', false);
            }

            if (array_key_exists('code', $arguments) && $arguments['code'] !== null && $arguments['code'] !== '') {
                $q->where('code', trim((string)$arguments['code']));
            }
            if (array_key_exists('root_entity_id', $arguments)) {
                $rid = $arguments['root_entity_id'];
                if ($rid === null || $rid === '' || $rid === 'null' || $rid === 0 || $rid === '0') {
                    $q->whereNull('root_entity_id');
                } else {
                    $q->where('root_entity_id', (int)$rid);
                }
            }

            $this->applyStandardFilters($q, $arguments, ['team_id', 'is_active', 'code', 'root_entity_id', 'created_at']);
            $this->applyStandardSearch($q, $arguments, ['code', 'name', 'description']);
            $this->applyStandardSort($q, $arguments, ['name', 'code', 'id', 'created_at'], 'name', 'asc');

            $result = $this->applyStandardPaginationResult($q, $arguments);
            $items = $result['data']->map(fn ($cc) => [
                'id' => $cc->id,
                'code' => $cc->code,
                'name' => $cc->name,
                'team_id' => $cc->team_id,
                'root_entity_id' => $cc->root_entity_id,
                'is_active' => (bool)$cc->is_active,
            ])->values()->toArray();

            return ToolResult::success([
                'data' => $items,
                'pagination' => $result['pagination'] ?? null,
                'team_id' => $team->id,
                'root_team_id' => $rootTeamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Kostenstellen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'cost_centers', 'lookup'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}

