<?php

namespace Platform\Organization\Livewire;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Platform\Core\Models\Team;

/**
 * Pulse — operative cross-modul Live-Sicht.
 *
 * Aggregiert juengste Snapshots aus allen Modulen, die das Health-Snapshot-Pattern
 * implementieren (Planner Projects, Helpdesk Boards, Dev Packages). Bewusst eine
 * andere Buehne als der OpsRoom (VSM-Steuerung) — hier zaehlt Live-Lage, nicht
 * Verantwortlichkeitsmodell.
 *
 * Scope: Root-Team + alle Child-Teams (organization-Standard).
 *
 * Lockere Kopplung: jedes Modul wird via class_exists abgefragt. Fehlt ein Modul,
 * wird sein Bereich uebersprungen statt einer Exception.
 */
class Pulse extends Component
{
    /** @var array<string, array{class: string, table: string, fk: string, relation: string, label: string, route: ?string}> */
    protected array $sources = [
        'planner' => [
            'class' => \Platform\Planner\Models\PlannerProjectSnapshot::class,
            'table' => 'planner_project_snapshots',
            'fk' => 'project_id',
            'relation' => 'project',
            'label' => 'Projekte',
            'route' => 'planner.projects.health',
        ],
        'helpdesk' => [
            'class' => \Platform\Helpdesk\Models\HelpdeskBoardSnapshot::class,
            'table' => 'helpdesk_board_snapshots',
            'fk' => 'helpdesk_board_id',
            'relation' => 'board',
            'label' => 'Boards',
            'route' => 'helpdesk.boards.health',
        ],
        'dev' => [
            'class' => \Platform\Dev\Models\DevPackageSnapshot::class,
            'table' => 'dev_package_snapshots',
            'fk' => 'dev_package_id',
            'relation' => 'package',
            'label' => 'Packages',
            'route' => 'dev.packages.health',
        ],
    ];

    #[Computed]
    public function rootTeam(): ?Team
    {
        $base = Auth::user()->currentTeamRelation;
        return $base ? $base->getRootTeam() : null;
    }

    #[Computed]
    public function relevantTeamIds(): array
    {
        $root = $this->rootTeam;
        if (! $root) {
            return [];
        }
        $ids = [$root->id];
        $this->collectChildTeamIds($root, $ids);
        return $ids;
    }

    protected function collectChildTeamIds(Team $team, array &$ids): void
    {
        foreach ($team->childTeams()->get() as $child) {
            $ids[] = $child->id;
            $this->collectChildTeamIds($child, $ids);
        }
    }

    protected function loadModuleSnapshots(array $teamIds, string $key): Collection
    {
        $source = $this->sources[$key];
        if (! class_exists($source['class']) || empty($teamIds)) {
            return collect();
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

        return $source['class']::with([$source['relation'] . ':id,name', 'team:id,name'])
            ->whereIn('id', $latestIds)
            ->get()
            ->map(function ($s) use ($key, $source) {
                $container = $s->{$source['relation']};
                $s->module = $key;
                $s->module_label = $source['label'];
                $s->container_id = $container?->id;
                $s->container_name = $container?->name ?? '—';
                $s->container_route = $source['route'];
                $s->team_name = $s->team?->name;
                return $s;
            });
    }

    #[Layout('platform::layouts.app')]
    public function render()
    {
        $root = $this->rootTeam;
        $teamIds = $this->relevantTeamIds;

        $allByModule = [];
        foreach (array_keys($this->sources) as $key) {
            $allByModule[$key] = $this->loadModuleSnapshots($teamIds, $key);
        }
        $all = collect($allByModule)->flatten(1);

        // Per-Module Ampel-Verteilung
        $perModule = [];
        foreach ($allByModule as $key => $snapshots) {
            if ($snapshots->isEmpty()) {
                continue;
            }
            $byColor = ['red' => 0, 'yellow' => 0, 'green' => 0, 'gray' => 0];
            foreach ($snapshots as $s) {
                $byColor[$s->health_color ?: 'gray']++;
            }
            $perModule[$key] = [
                'label' => $this->sources[$key]['label'],
                'total' => $snapshots->count(),
                'byColor' => $byColor,
                'avgScore' => $snapshots->filter(fn ($s) => $s->health_score !== null)->avg('health_score'),
            ];
        }

        // Globale Summen
        $totalAll = $all->count();
        $totalByColor = ['red' => 0, 'yellow' => 0, 'green' => 0, 'gray' => 0];
        foreach ($all as $s) {
            $totalByColor[$s->health_color ?: 'gray']++;
        }

        // Was brennt jetzt? — alle roten, sortiert nach worst score
        $brennt = $all
            ->filter(fn ($s) => $s->health_color === 'red')
            ->sortBy(fn ($s) => (int) ($s->health_score ?? 999))
            ->take(12)
            ->values();

        // Karteileichen (Confidence <= 25 ueber alle Module)
        $karteileichen = $all
            ->filter(fn ($s) => (int) $s->confidence_score <= 25)
            ->sortBy('confidence_score')
            ->take(8)
            ->values();

        // Bewegung — top Gewinner / Verlierer cross-modul
        $withDelta = $all->filter(fn ($s) => $s->delta_health_score !== null && $s->delta_health_score !== 0);
        $gewinner = $withDelta->filter(fn ($s) => $s->delta_health_score > 0)->sortByDesc('delta_health_score')->take(5)->values();
        $verlierer = $withDelta->filter(fn ($s) => $s->delta_health_score < 0)->sortBy('delta_health_score')->take(5)->values();

        // Confidence-Verteilung
        $byConfidence = ['high_75_100' => 0, 'medium_50_74' => 0, 'low_25_49' => 0, 'none_0_24' => 0];
        foreach ($all as $s) {
            $c = (int) $s->confidence_score;
            if ($c >= 75) $byConfidence['high_75_100']++;
            elseif ($c >= 50) $byConfidence['medium_50_74']++;
            elseif ($c >= 25) $byConfidence['low_25_49']++;
            else $byConfidence['none_0_24']++;
        }

        $snapshotStand = $all->max('taken_at');

        return view('organization::livewire.pulse', [
            'rootTeam' => $root,
            'teamCount' => count($teamIds),
            'perModule' => $perModule,
            'totalAll' => $totalAll,
            'totalByColor' => $totalByColor,
            'brennt' => $brennt,
            'karteileichen' => $karteileichen,
            'gewinner' => $gewinner,
            'verlierer' => $verlierer,
            'byConfidence' => $byConfidence,
            'snapshotStand' => $snapshotStand,
        ]);
    }
}
