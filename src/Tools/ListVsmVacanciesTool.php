<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityType;
use Platform\Organization\Models\OrganizationEntityVsmAssignment;

/**
 * Liefert pro Carrier-Entity die leeren VSM-Zellen (S1..S5).
 * Vollstaendigkeits-Check fuer die VSM-Besetzung.
 */
class ListVsmVacanciesTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.vsm_vacancies.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/vsm-vacancies - Listet pro Carrier-Entity, welche VSM-Zellen (S1/S2/S3/S3*/S4/S5) noch unbesetzt sind. Vollstaendigkeits-Check: leere Zellen sollten zu permanenten system_health-Signalen werden.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'perspective_entity_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Nur fuer diese Carrier-Entity pruefen. Default: alle Carriers des Teams.',
                ],
                'active_only' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Nur heute gueltige Zuordnungen als "besetzt" werten. Default: true.',
                ],
                'only_with_gaps' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Nur Carrier mit mindestens einer leeren Zelle. Default: true.',
                ],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $context->getTeamId();
            if (!$teamId) {
                return ToolResult::error('AUTH_ERROR', 'Kein Team im Kontext.');
            }

            $activeOnly = (bool) ($arguments['active_only'] ?? true);
            $onlyWithGaps = (bool) ($arguments['only_with_gaps'] ?? true);

            $carriersQuery = OrganizationEntity::query()
                ->where('team_id', $teamId)
                ->active()
                ->whereHas('type', fn ($q) => $q->where('vsm_class', OrganizationEntityType::VSM_CLASS_CARRIER))
                ->with('type:id,name,code,vsm_class');

            if (!empty($arguments['perspective_entity_id'])) {
                $carriersQuery->where('id', (int) $arguments['perspective_entity_id']);
            }

            $carriers = $carriersQuery->get();
            if ($carriers->isEmpty()) {
                return ToolResult::success([
                    'data' => [],
                    'count' => 0,
                    'message' => 'Keine Carrier-Entities im Team.',
                ]);
            }

            // Belegung pro Carrier ermitteln: [carrierId => [vsmSystem => count]]
            $assignmentsQuery = OrganizationEntityVsmAssignment::query()
                ->where('team_id', $teamId)
                ->whereIn('perspective_entity_id', $carriers->pluck('id'));

            if ($activeOnly) {
                $assignmentsQuery->activeAt();
            }

            $assignments = $assignmentsQuery
                ->select('perspective_entity_id', 'vsm_system')
                ->get()
                ->groupBy('perspective_entity_id')
                ->map(fn ($rows) => $rows->groupBy('vsm_system')->map->count()->toArray());

            $results = [];
            foreach ($carriers as $carrier) {
                $occupied = $assignments[$carrier->id] ?? [];
                $cells = [];
                $vacancies = [];
                foreach (OrganizationEntityVsmAssignment::VSM_SYSTEMS as $sys) {
                    $count = (int) ($occupied[$sys] ?? 0);
                    $cells[$sys] = $count;
                    if ($count === 0) {
                        $vacancies[] = $sys;
                    }
                }

                if ($onlyWithGaps && empty($vacancies)) {
                    continue;
                }

                $results[] = [
                    'perspective_entity_id' => $carrier->id,
                    'perspective_name' => $carrier->name,
                    'perspective_type' => $carrier->type?->name,
                    'cells_occupied' => $cells,
                    'vacancies' => $vacancies,
                    'vacancy_count' => count($vacancies),
                    'is_complete' => empty($vacancies),
                ];
            }

            return ToolResult::success([
                'data' => $results,
                'count' => count($results),
                'carriers_checked' => $carriers->count(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Vakanz-Check: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'vsm', 'vacancies', 'health'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
