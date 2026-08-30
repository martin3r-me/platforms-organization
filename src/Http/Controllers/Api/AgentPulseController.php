<?php

namespace Platform\Organization\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Platform\Core\Models\Team;
use Platform\Organization\Models\OrganizationEntity;

/**
 * PULSE als Agent-Vertrag: die operative INNEN-Lage für S3/S2 (Steward) — die jüngsten Health-Snapshots
 * über alle Module (Planner-Projekte, Helpdesk-Boards, Dev-Packages), verdichtet zu einem Steward-Digest:
 * was BRENNT (rot, schlechtester Score zuerst) und was KIPPT (negativer Health-Delta). Pendant zur Umwelt
 * (S4). Rollen-Gating im Daemon (nur hasVsm("S3")/("S2") fetcht). Spiegelt die Pulse-Livewire-Aggregation.
 */
class AgentPulseController extends Controller
{
    /** Health-Snapshot-Quellen je Modul (weiche Kopplung: fehlt ein Modul, wird es übersprungen). */
    private array $sources = [
        'planner'  => ['class' => \Platform\Planner\Models\PlannerProjectSnapshot::class, 'table' => 'planner_project_snapshots', 'fk' => 'project_id',        'relation' => 'project', 'label' => 'Projekte', 'live_column' => 'lifecycle_state', 'live_value' => 'aktiv'],
        'helpdesk' => ['class' => \Platform\Helpdesk\Models\HelpdeskBoardSnapshot::class, 'table' => 'helpdesk_board_snapshots', 'fk' => 'helpdesk_board_id', 'relation' => 'board',   'label' => 'Boards',   'live_column' => null,              'live_value' => null],
        'dev'      => ['class' => \Platform\Dev\Models\DevPackageSnapshot::class,       'table' => 'dev_package_snapshots',     'fk' => 'dev_package_id',     'relation' => 'package', 'label' => 'Packages', 'live_column' => null,              'live_value' => null],
    ];

    /**
     * GET /api/org/agent/pulse — verdichtete operative Live-Lage (was brennt / was kippt).
     */
    public function pulse(Request $request): JsonResponse
    {
        $userId = (int) $request->user()?->id;
        if ($userId < 1) {
            return response()->json(['message' => 'No user for this token'], 404);
        }
        $agent = OrganizationEntity::query()->agents()->where('linked_user_id', $userId)->first();
        if (! $agent) {
            return response()->json(['message' => 'No agent for this token'], 404);
        }

        try {
            $teamIds = $this->relevantTeamIds((int) $agent->team_id);
            $modules = [];
            $burning = [];
            $falling = [];
            $snapshotStand = null;

            foreach ($this->sources as $key => $source) {
                $snaps = $this->loadModuleSnapshots($teamIds, $source);
                if (empty($snaps)) {
                    continue;
                }
                $counts = ['red' => 0, 'yellow' => 0, 'green' => 0, 'gray' => 0];
                $scoreSum = 0;
                $scoreN = 0;
                foreach ($snaps as $s) {
                    $color = $s->health_color ?: 'gray';
                    $counts[$color] = ($counts[$color] ?? 0) + 1;
                    if ($s->health_score !== null) {
                        $scoreSum += (int) $s->health_score;
                        $scoreN++;
                    }
                    if (! empty($s->taken_on) && (string) $s->taken_on > (string) $snapshotStand) {
                        $snapshotStand = (string) $s->taken_on;
                    }
                    if ($color === 'red') {
                        $burning[] = ['module' => $source['label'], 'container' => $s->container_name, 'score' => $s->health_score !== null ? (int) $s->health_score : null];
                    }
                    if ($s->delta_health_score !== null && (int) $s->delta_health_score < 0) {
                        $falling[] = ['module' => $source['label'], 'container' => $s->container_name, 'delta' => (int) $s->delta_health_score];
                    }
                }
                $modules[] = [
                    'module'    => $source['label'],
                    'total'     => count($snaps),
                    'red'       => $counts['red'], 'yellow' => $counts['yellow'], 'green' => $counts['green'], 'gray' => $counts['gray'],
                    'avg_score' => $scoreN > 0 ? (int) round($scoreSum / $scoreN) : null,
                ];
            }

            usort($burning, fn ($a, $b) => ($a['score'] ?? 999) <=> ($b['score'] ?? 999));
            usort($falling, fn ($a, $b) => $a['delta'] <=> $b['delta']);

            return response()->json(['data' => [
                'snapshot_stand' => $snapshotStand,
                'modules'        => $modules,
                'burning'        => array_slice($burning, 0, 10),
                'falling'        => array_slice($falling, 0, 10),
            ]]);
        } catch (\Throwable $e) {
            return response()->json(['data' => ['snapshot_stand' => null, 'modules' => [], 'burning' => [], 'falling' => []]]);
        }
    }

    /** Root-Team + alle Kind-Teams (organization-Standard-Scope). */
    private function relevantTeamIds(int $teamId): array
    {
        $team = Team::find($teamId);
        $root = $team ? ($team->getRootTeam() ?? $team) : null;
        if (! $root) {
            return $teamId > 0 ? [$teamId] : [];
        }
        $ids = [$root->id];
        $this->collectChildTeamIds($root, $ids);
        return array_values(array_unique($ids));
    }

    private function collectChildTeamIds(Team $team, array &$ids): void
    {
        foreach ($team->childTeams()->get() as $child) {
            $ids[] = $child->id;
            $this->collectChildTeamIds($child, $ids);
        }
    }

    /** Jüngster Snapshot je Container (live gefiltert) — spiegelt Pulse::loadModuleSnapshots. */
    private function loadModuleSnapshots(array $teamIds, array $source): array
    {
        if (! class_exists($source['class']) || empty($teamIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($teamIds), '?'));
        $latestIds = DB::table($source['table'] . ' as a')
            ->whereIn('a.team_id', $teamIds)
            ->whereRaw("a.taken_on = (
                SELECT MAX(b.taken_on) FROM {$source['table']} b
                WHERE b.{$source['fk']} = a.{$source['fk']}
                  AND b.team_id IN ({$placeholders})
            )", $teamIds)
            ->pluck('a.id');

        $liveColumn = $source['live_column'] ?? null;
        $liveValue = $source['live_value'] ?? null;

        return $source['class']::with([$source['relation'] . ':id,name'])
            ->whereIn('id', $latestIds)
            ->whereHas($source['relation'], function ($q) use ($liveColumn, $liveValue) {
                if ($liveColumn && $liveValue !== null) {
                    $q->where($liveColumn, $liveValue);
                }
            })
            ->get()
            ->map(function ($s) use ($source) {
                $container = $s->{$source['relation']};
                $s->container_name = $container?->name ?? '—';
                return $s;
            })
            ->all();
    }
}
