<?php

namespace Platform\Organization\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntitySnapshot;
use Platform\Organization\Services\EntityDimensionBridge;
use Platform\Organization\Models\OrganizationSignal;
use Platform\Organization\Models\OrganizationTimeEntry;
use Platform\Organization\Services\EntityLinkRegistry;
use Platform\Organization\Services\PersonActivityRegistry;
use Platform\Organization\Services\SnapshotMovementService;
use Platform\Organization\Services\EnvironmentMovementService;
use Platform\Organization\Models\OrganizationEnvironmentSource;
use Platform\Organization\Models\OrganizationEntityStory;
use Platform\Organization\Services\PerspectiveService;
use Platform\Organization\Services\EntityHierarchyService;
use Platform\Organization\Services\Story\EntityFactsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Dashboard extends Component
{
    public ?string $dashboardStream = null;
    public string $lagePeriod = 'week';

    #[On('perspective-switched')]
    public function onPerspectiveSwitched(): void
    {
        unset(
            $this->teamSnapshotSummary,
            $this->teamMovement,
            $this->entityHealthOverview,
            $this->teamTimeAnalytics,
            $this->completionVelocity,
            $this->teamSnapshotTrend,
            $this->linkTypeDistribution,
            $this->topEntitiesByActivity,
            $this->personOverview,
            $this->insightStatements,
            $this->signalOverview,
            $this->focusedSignals,
            $this->environmentRadar,
            $this->orgLage,
            $this->attention,
        );
    }

    public function setLagePeriod(string $period): void
    {
        if (in_array($period, EntityFactsService::PERIODS, true)) {
            $this->lagePeriod = $period;
            unset($this->orgLage, $this->attention);
        }
    }

    /**
     * Lage der Organisation aus Sicht der aktiven Perspektive: ihr Satz (täglicher Lauf
     * organization:entity-stories), die Einheiten direkt darunter nach Art gruppiert mit ihren
     * Sätzen, und die stärksten Bewegungen quer über diese Einheiten. Nur Gespeichertes —
     * live über 60+ Untereinheiten zu rechnen wäre für eine Startseite zu teuer.
     */
    #[Computed]
    public function orgLage(): array
    {
        $teamId = $this->getTeamId();
        $root = $teamId ? PerspectiveService::getActiveEntity($teamId) : null;
        if (! $root) {
            return ['root' => null];
        }

        $story = OrganizationEntityStory::latestFor($root->id, $this->lagePeriod);
        if (! $story) {
            return ['root' => $root, 'story' => null];
        }

        $children = collect($story->facts['children'] ?? []);
        $entities = OrganizationEntity::with('type:id,name,vsm_class')->whereIn('id', $children->pluck('id'))
            ->get(['id', 'entity_type_id'])->keyBy('id');
        $childStories = OrganizationEntityStory::whereIn('entity_id', $children->pluck('id'))
            ->where('period', $this->lagePeriod)->orderByDesc('as_of')->get(['entity_id', 'story', 'facts', 'as_of'])
            ->unique('entity_id')->keyBy('entity_id');

        $children = $children->map(fn ($c) => $c + [
            'type' => $entities[$c['id']]->type->name ?? 'Sonstige',
            'class' => $entities[$c['id']]->type->vsm_class ?? null,
            'story' => $childStories[$c['id']]->story ?? null,
        ]);

        // Karte: die Struktur (alles außer Akteuren und Umwelt) nach Art. Menschen und Agenten
        // stehen bei „Wer trägt die Woche", Externe und Software zählen nur als Umwelt.
        $map = $children->reject(fn ($c) => in_array($c['class'], ['actor', 'observed'], true))
            ->groupBy('type')
            ->map(fn ($items, $type) => [
                'type' => $type,
                'active' => $items->reject(fn ($c) => $c['quiet'] || $c['mute'])->sortByDesc('weight')->values()->all(),
                'quiet' => $items->filter(fn ($c) => $c['quiet'] && ! $c['mute'])->pluck('name')->all(),
                'mute' => $items->filter(fn ($c) => $c['mute'])->count(),
                'minutes' => $items->sum('minutes'),
                'weight' => $items->sum('weight'),
            ])
            ->filter(fn ($g) => $g['active'] !== [] || $g['quiet'] !== [])
            ->sortByDesc('weight')->values()->all();
        $map = $this->mapLayout($map);

        // Bewegungen quer über die Einheiten darunter, mit Herkunft
        $moves = ['good' => [], 'bad' => []];
        foreach ($childStories as $id => $cs) {
            $name = $children->firstWhere('id', $id)['name'] ?? '';
            foreach (['good', 'bad'] as $g) {
                foreach ($cs->facts['movers'][$g] ?? [] as $m) {
                    $moves[$g][] = $m + ['entity_id' => $id, 'entity' => $name];
                }
            }
        }
        foreach ($moves as $g => $list) {
            $moves[$g] = collect($list)->sortByDesc('score')->take(5)->values()->all();
        }

        return [
            'root' => $root,
            'story' => $story->story,
            'source' => $story->source,
            'as_of' => $story->as_of?->toDateString(),
            'facts' => $story->facts,
            'map' => $map,
            'observed' => $children->where('class', 'observed')->count(),
            'moves' => $moves,
        ];
    }

    /**
     * Flächen für die Karte: erst die Arten, darin die Einheiten (Squarified Treemap).
     * Fläche = gebuchte Zeit; Stilles und Kleines bekommen eine Mindestfläche, damit es sichtbar bleibt.
     */
    protected function mapLayout(array $groups): array
    {
        $total = collect($groups)->sum('minutes');
        $floor = max(30, $total * 0.03);

        foreach ($groups as &$g) {
            $cells = array_map(fn ($c) => ['value' => max($floor, $c['minutes']), 'kind' => 'unit', 'unit' => $c], $g['active']);
            if ($g['quiet'] !== []) {
                $cells[] = ['value' => $floor, 'kind' => 'quiet', 'names' => $g['quiet']];
            }
            $g['cells'] = $cells;
            $g['value'] = array_sum(array_column($cells, 'value'));
        }
        unset($g);

        $aspect = 3.2; // Karte ist etwa dreimal so breit wie hoch
        return array_map(function ($g) use ($aspect) {
            [, , $gw, $gh] = $g['rect'];
            $g['cells'] = \Platform\Organization\Services\Story\Treemap::layout($g['cells'], max(0.2, $aspect * $gw / max($gh, 1)));

            return $g;
        }, \Platform\Organization\Services\Story\Treemap::layout($groups, $aspect));
    }

    /**
     * „Braucht Aufmerksamkeit": was heute angefasst werden muss, im Teilbaum der Perspektive.
     * Reihenfolge: eigene Fokus-Markierungen, algedonische und kritische Signale, Einheiten mit
     * überwiegend kritischer Bewegung (aus der Lage), dann Warnungen. Höchstens fünf.
     */
    #[Computed]
    public function attention(): array
    {
        $teamId = $this->getTeamId();
        $root = $teamId ? PerspectiveService::getActiveEntity($teamId) : null;
        if (! $root) {
            return ['items' => [], 'open_total' => 0, 'older' => 0];
        }

        $ids = array_merge([$root->id], resolve(EntityHierarchyService::class)->getAllDescendantMap([$root->id])[$root->id] ?? []);
        $open = fn () => OrganizationSignal::whereIn('entity_id', $ids)->actionable();
        $userId = auth()->id();

        $items = [];
        $seen = [];
        $push = function ($signal, string $why) use (&$items, &$seen) {
            if (isset($seen[$signal->id])) {
                return;
            }
            $seen[$signal->id] = true;
            $items[] = [
                'kind' => 'signal',
                'severity' => $signal->severity,
                'title' => \Illuminate\Support\Str::limit((string) $signal->message, 120),
                'why' => $why,
                'entity' => $signal->entity?->name,
                'href' => route('organization.signals.show', $signal),
                'focused' => $why === 'von dir markiert',
                'signal_id' => $signal->id,
            ];
        };

        if ($userId) {
            $open()->focusedBy($userId)->with('entity:id,name')
                ->orderByRaw("FIELD(severity, 'algedonic', 'critical', 'warning', 'info')")->latest()->limit(5)->get()
                ->each(fn ($s) => $push($s, 'von dir markiert'));
        }

        // Nur Frisches drängt; was seit Wochen offen liegt, wird gezählt statt gezeigt
        $fresh = now()->subDays(self::ATTENTION_FRESH_DAYS);
        $since = fn ($s) => 'gemeldet ' . $s->created_at->locale('de')->diffForHumans();

        $open()->whereIn('severity', ['algedonic', 'critical'])->where('created_at', '>=', $fresh)->with('entity:id,name')
            ->orderByRaw("FIELD(severity, 'algedonic', 'critical')")->latest()->limit(5)->get()
            ->each(fn ($s) => $push($s, $since($s)));

        // Einheiten, in denen Kritisches überwiegt — aus den Fakten der Lage, nicht neu gerechnet
        $lage = $this->orgLage;
        foreach ($lage['map'] ?? [] as $g) {
            foreach ($g['active'] as $c) {
                if ($c['bad'] >= 2 && $c['bad'] > $c['good'] && count($items) < 5) {
                    $top = $c['top'] ?? null;
                    $items[] = [
                        'kind' => 'unit',
                        'severity' => 'warning',
                        'title' => $c['name'] . ': ' . $c['bad'] . ' kritische Bewegungen',
                        'why' => $top ? resolve(\Platform\Organization\Services\Story\EntityStoryWriter::class)->plain($top['label']) . ' ' . (($top['delta'] ?? 0) > 0 ? '+' : '') . ($top['delta'] ?? '') : null,
                        'entity' => $g['type'],
                        'href' => route('organization.entities.show', $c['id']),
                        'focused' => false,
                        'signal_id' => null,
                    ];
                }
            }
        }

        if (count($items) < 5) {
            $open()->where('severity', 'warning')->where('created_at', '>=', $fresh)->with('entity:id,name')->latest()->limit(5 - count($items))->get()
                ->each(fn ($s) => $push($s, 'Warnung, ' . $since($s)));
        }

        return [
            'items' => array_slice($items, 0, 5),
            'open_total' => $open()->count(),
            'older' => $open()->where('created_at', '<', $fresh)->count(),
        ];
    }

    /** Live-Kacheln (Zeiten, Links, Insights): 10 min je Team/Stream. */
    private const PANEL_CACHE_TTL = 600;

    /** „Braucht Aufmerksamkeit" zeigt nur Signale aus diesem Zeitraum; ältere werden gezählt. */
    private const ATTENTION_FRESH_DAYS = 14;

    /**
     * Rein snapshot-basierte Kacheln: gültig bis zum nächsten Snapshot-Lauf —
     * SnapshotEntitiesCommand setzt danach SNAPSHOT_VERSION_KEY neu.
     */
    private const SNAPSHOT_PANELS = ['teamSnapshotSummary', 'teamMovement', 'entityHealthOverview', 'completionVelocity', 'teamSnapshotTrend', 'topEntitiesByActivity', 'personOverview'];
    private const SNAPSHOT_CACHE_TTL = 43200;
    public const SNAPSHOT_VERSION_KEY = 'organization:snapshots:version';

    /** Messmodus: $wire.set('profile', true) → Zeiten je Kachel im HTML (#org-dashboard-timings). */
    public bool $profile = false;
    protected array $timings = [];

    protected function panel(string $name, \Closure $compute): array
    {
        $start = microtime(true);
        $snapshotBased = in_array($name, self::SNAPSHOT_PANELS, true);
        $ttl = $snapshotBased ? self::SNAPSHOT_CACHE_TTL : self::PANEL_CACHE_TTL;
        $key = 'organization:dashboard:' . $this->getTeamId() . ':' . ($this->dashboardStream ?? 'all') . ':' . $name
            . ($snapshotBased ? ':v' . Cache::get(self::SNAPSHOT_VERSION_KEY, 0) : '');
        $result = $this->profile ? $compute() : Cache::remember($key, $ttl, $compute);
        if ($this->profile) {
            Cache::put($key, $result, $ttl);
            $this->timings[$name] = round((microtime(true) - $start) * 1000);
        }

        return $result;
    }

    public function getTeamId(): ?int
    {
        $user = auth()->user();
        return $user && $user->currentTeam ? $user->currentTeam->id : null;
    }

    public function getTotalEntitiesProperty(): int
    {
        $teamId = $this->getTeamId();
        if (!$teamId) {
            return 0;
        }
        return (int) OrganizationEntity::forTeam($teamId)->count();
    }

    public function getActiveEntitiesProperty(): int
    {
        $teamId = $this->getTeamId();
        if (!$teamId) {
            return 0;
        }
        return (int) OrganizationEntity::forTeam($teamId)->active()->count();
    }

    #[Computed]
    public function teamSnapshotSummary(): array
    {
        return $this->panel('teamSnapshotSummary', fn () => $this->computeTeamSnapshotSummary());
    }

    protected function computeTeamSnapshotSummary(): array
    {
        $teamId = $this->getTeamId();
        if (!$teamId) {
            return $this->emptySnapshotSummary();
        }

        $entityIds = OrganizationEntity::forTeam($teamId)->pluck('id');
        if ($entityIds->isEmpty()) {
            return $this->emptySnapshotSummary();
        }

        // Latest snapshot per entity via subquery
        $latestSnapshots = $this->getLatestSnapshotsForEntities($entityIds);
        $agoSnapshots = $this->getSnapshotsNearDate($entityIds, now()->subDays(7));

        $current = $this->aggregateSnapshotMetrics($latestSnapshots);
        $ago = $this->aggregateSnapshotMetrics($agoSnapshots);

        $completionRate = $current['items_total'] > 0
            ? round(($current['items_done'] / $current['items_total']) * 100, 1)
            : 0;
        $billingRate = $current['time_total_minutes'] > 0
            ? round(($current['time_billed_minutes'] / $current['time_total_minutes']) * 100, 1)
            : 0;

        $agoCompletionRate = $ago['items_total'] > 0
            ? round(($ago['items_done'] / $ago['items_total']) * 100, 1)
            : 0;
        $agoBillingRate = $ago['time_total_minutes'] > 0
            ? round(($ago['time_billed_minutes'] / $ago['time_total_minutes']) * 100, 1)
            : 0;

        return [
            'items_total' => $current['items_total'],
            'items_done' => $current['items_done'],
            'completion_rate' => $completionRate,
            'time_total_minutes' => $current['time_total_minutes'],
            'time_billed_minutes' => $current['time_billed_minutes'],
            'billing_rate' => $billingRate,
            'trend_completion' => round($completionRate - $agoCompletionRate, 1),
            'trend_billing' => round($billingRate - $agoBillingRate, 1),
            'has_data' => $current['items_total'] > 0 || $current['time_total_minutes'] > 0,
        ];
    }

    #[Computed]
    public function teamMovement(): array
    {
        return $this->panel('teamMovement', fn () => $this->computeTeamMovement());
    }

    protected function computeTeamMovement(): array
    {
        $teamId = $this->getTeamId();
        if (!$teamId) {
            return [];
        }

        $entityIds = OrganizationEntity::forTeam($teamId)->pluck('id')->toArray();
        if (empty($entityIds)) {
            return [];
        }

        $service = resolve(SnapshotMovementService::class);

        return $service->forEntities($entityIds, 7, $this->dashboardStream)->toArray();
    }

    #[Computed]
    public function entityHealthOverview(): array
    {
        return $this->panel('entityHealthOverview', fn () => $this->computeEntityHealthOverview());
    }

    protected function computeEntityHealthOverview(): array
    {
        $teamId = $this->getTeamId();
        if (!$teamId) {
            return ['counts' => ['progressing' => 0, 'completed' => 0, 'stalled' => 0, 'at_risk' => 0], 'problems' => []];
        }

        $entities = OrganizationEntity::forTeam($teamId)->with('type')->get();
        if ($entities->isEmpty()) {
            return ['counts' => ['progressing' => 0, 'completed' => 0, 'stalled' => 0, 'at_risk' => 0], 'problems' => []];
        }

        $entityIds = $entities->pluck('id');
        $latestSnapshots = $this->getLatestSnapshotsForEntities($entityIds);
        $agoSnapshots = $this->getSnapshotsNearDate($entityIds, now()->subDays(7));

        $counts = ['progressing' => 0, 'completed' => 0, 'stalled' => 0, 'at_risk' => 0];
        $problems = [];

        foreach ($entities as $entity) {
            $current = $latestSnapshots[$entity->id] ?? null;
            $ago = $agoSnapshots[$entity->id] ?? null;

            if (!$current) {
                continue;
            }

            $currentMetrics = $current->metrics;
            $agoMetrics = $ago ? $ago->metrics : null;

            $itemsTotal = $currentMetrics['items_total_cascaded'] ?? $currentMetrics['items_total'] ?? 0;
            $itemsDone = $currentMetrics['items_done_cascaded'] ?? $currentMetrics['items_done'] ?? 0;
            $agoItemsDone = $agoMetrics ? ($agoMetrics['items_done_cascaded'] ?? $agoMetrics['items_done'] ?? 0) : 0;
            $agoItemsTotal = $agoMetrics ? ($agoMetrics['items_total_cascaded'] ?? $agoMetrics['items_total'] ?? 0) : 0;

            $status = $this->classifyEntityHealth($itemsTotal, $itemsDone, $agoItemsTotal, $agoItemsDone);
            $counts[$status]++;

            if (in_array($status, ['stalled', 'at_risk'])) {
                $completionPct = $itemsTotal > 0 ? round(($itemsDone / $itemsTotal) * 100) : 0;
                $problems[] = [
                    'id' => $entity->id,
                    'name' => $entity->name,
                    'type_name' => $entity->type->name ?? '',
                    'status' => $status,
                    'items_total' => $itemsTotal,
                    'items_done' => $itemsDone,
                    'completion_pct' => $completionPct,
                    'open_items' => $itemsTotal - $itemsDone,
                ];
            }
        }

        // Sort problems: at_risk first, then stalled, limit to 5
        usort($problems, fn($a, $b) => ($a['status'] === 'at_risk' ? 0 : 1) <=> ($b['status'] === 'at_risk' ? 0 : 1));
        $problems = array_slice($problems, 0, 5);

        return ['counts' => $counts, 'problems' => $problems];
    }

    #[Computed]
    public function teamTimeAnalytics(): array
    {
        return $this->panel('teamTimeAnalytics', fn () => $this->computeTeamTimeAnalytics());
    }

    protected function computeTeamTimeAnalytics(): array
    {
        $teamId = $this->getTeamId();
        if (!$teamId) {
            return $this->emptyTimeAnalytics();
        }

        $now = Carbon::now();
        $thisMonthStart = $now->copy()->startOfMonth();
        $lastMonthStart = $now->copy()->subMonth()->startOfMonth();
        $lastMonthEnd = $now->copy()->subMonth()->endOfMonth();

        $thisMonth = OrganizationTimeEntry::where('team_id', $teamId)
            ->where('work_date', '>=', $thisMonthStart)
            ->selectRaw('COALESCE(SUM(minutes), 0) as total_minutes')
            ->selectRaw('COALESCE(SUM(CASE WHEN is_billed = 1 THEN minutes ELSE 0 END), 0) as billed_minutes')
            ->first();

        $lastMonth = OrganizationTimeEntry::where('team_id', $teamId)
            ->whereBetween('work_date', [$lastMonthStart, $lastMonthEnd])
            ->selectRaw('COALESCE(SUM(minutes), 0) as total_minutes')
            ->first();

        $thisTotal = (int) ($thisMonth->total_minutes ?? 0);
        $thisBilled = (int) ($thisMonth->billed_minutes ?? 0);
        $lastTotal = (int) ($lastMonth->total_minutes ?? 0);

        $thisHours = round($thisTotal / 60, 1);
        $lastHours = round($lastTotal / 60, 1);
        $billingRate = $thisTotal > 0 ? round(($thisBilled / $thisTotal) * 100, 1) : 0;

        // Last month billing rate for trend
        $lastMonthBilled = OrganizationTimeEntry::where('team_id', $teamId)
            ->whereBetween('work_date', [$lastMonthStart, $lastMonthEnd])
            ->selectRaw('COALESCE(SUM(CASE WHEN is_billed = 1 THEN minutes ELSE 0 END), 0) as billed_minutes')
            ->value('billed_minutes') ?? 0;
        $lastBillingRate = $lastTotal > 0 ? round(($lastMonthBilled / $lastTotal) * 100, 1) : 0;

        return [
            'hours_this_month' => $thisHours,
            'hours_last_month' => $lastHours,
            'trend_hours' => $lastHours > 0 ? round($thisHours - $lastHours, 1) : 0,
            'billed_minutes' => $thisBilled,
            'open_minutes' => $thisTotal - $thisBilled,
            'billing_rate' => $billingRate,
            'trend_billing' => round($billingRate - $lastBillingRate, 1),
            'has_data' => $thisTotal > 0 || $lastTotal > 0,
        ];
    }

    #[Computed]
    public function completionVelocity(): array
    {
        return $this->panel('completionVelocity', fn () => $this->computeCompletionVelocity());
    }

    protected function computeCompletionVelocity(): array
    {
        $teamId = $this->getTeamId();
        if (!$teamId) {
            return ['weekly_rates' => [], 'avg_per_week' => 0, 'trend' => 'stable'];
        }

        $entityIds = OrganizationEntity::forTeam($teamId)->pluck('id');
        if ($entityIds->isEmpty()) {
            return ['weekly_rates' => [], 'avg_per_week' => 0, 'trend' => 'stable'];
        }

        // Get weekly aggregated snapshots for 4 weeks
        $weeklyRates = [];
        for ($w = 4; $w >= 1; $w--) {
            $weekEnd = now()->subWeeks($w - 1);
            $weekStart = now()->subWeeks($w);

            $endSnapshots = $this->getSnapshotsNearDate($entityIds, $weekEnd);
            $startSnapshots = $this->getSnapshotsNearDate($entityIds, $weekStart);

            $endDone = 0;
            $startDone = 0;
            foreach ($entityIds as $id) {
                $endDone += ($endSnapshots[$id] ?? null) ? ($endSnapshots[$id]->metrics['items_done'] ?? 0) : 0;
                $startDone += ($startSnapshots[$id] ?? null) ? ($startSnapshots[$id]->metrics['items_done'] ?? 0) : 0;
            }

            $weeklyRates[] = max(0, $endDone - $startDone);
        }

        $avg = count($weeklyRates) > 0 ? round(array_sum($weeklyRates) / count($weeklyRates), 1) : 0;

        // Trend: compare last 2 weeks vs. first 2 weeks
        $recent = ($weeklyRates[2] ?? 0) + ($weeklyRates[3] ?? 0);
        $earlier = ($weeklyRates[0] ?? 0) + ($weeklyRates[1] ?? 0);
        $trend = 'stable';
        if ($recent > $earlier * 1.2) {
            $trend = 'accelerating';
        } elseif ($recent < $earlier * 0.8) {
            $trend = 'decelerating';
        }

        return [
            'weekly_rates' => $weeklyRates,
            'avg_per_week' => $avg,
            'trend' => $trend,
        ];
    }

    #[Computed]
    public function teamSnapshotTrend(): array
    {
        return $this->panel('teamSnapshotTrend', fn () => $this->computeTeamSnapshotTrend());
    }

    protected function computeTeamSnapshotTrend(): array
    {
        $teamId = $this->getTeamId();
        if (!$teamId) {
            return [];
        }

        $entityIds = OrganizationEntity::forTeam($teamId)->pluck('id');
        if ($entityIds->isEmpty()) {
            return [];
        }

        // Aggregate snapshots per day across all entities
        $rows = OrganizationEntitySnapshot::whereIn('entity_id', $entityIds)
            ->forDateRange(now()->subDays(14), now())
            ->select('snapshot_date')
            ->selectRaw('SUM(JSON_EXTRACT(metrics, "$.items_total")) as items_total')
            ->selectRaw('SUM(JSON_EXTRACT(metrics, "$.items_done")) as items_done')
            ->selectRaw('SUM(JSON_EXTRACT(metrics, "$.time_total_minutes")) as time_total_minutes')
            ->groupBy('snapshot_date')
            ->orderBy('snapshot_date')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $maxItemsTotal = 0;
        $maxMinutes = 0;
        $data = [];

        foreach ($rows as $row) {
            $itemsTotal = (int) ($row->items_total ?? 0);
            $itemsDone = (int) ($row->items_done ?? 0);
            $totalMin = (int) ($row->time_total_minutes ?? 0);

            if ($itemsTotal > $maxItemsTotal) $maxItemsTotal = $itemsTotal;
            if ($totalMin > $maxMinutes) $maxMinutes = $totalMin;

            $data[] = [
                'date' => Carbon::parse($row->snapshot_date)->format('d.m.'),
                'items_total' => $itemsTotal,
                'items_done' => $itemsDone,
                'time_total_minutes' => $totalMin,
            ];
        }

        return [
            'snapshots' => $data,
            'max_items_total' => $maxItemsTotal,
            'max_minutes' => $maxMinutes,
        ];
    }

    #[Computed]
    public function linkTypeDistribution(): array
    {
        return $this->panel('linkTypeDistribution', fn () => $this->computeLinkTypeDistribution());
    }

    protected function computeLinkTypeDistribution(): array
    {
        $teamId = $this->getTeamId();
        if (!$teamId) {
            return [];
        }

        $entityIds = OrganizationEntity::forTeam($teamId)->pluck('id');
        if ($entityIds->isEmpty()) {
            return [];
        }

        $countsByType = EntityDimensionBridge::linkCountsByEntityAndType($entityIds->toArray());

        // Flatten: aggregate across all entities
        $typeTotals = [];
        foreach ($countsByType as $entityCounts) {
            foreach ($entityCounts as $type => $count) {
                $typeTotals[$type] = ($typeTotals[$type] ?? 0) + $count;
            }
        }

        arsort($typeTotals);

        $labels = resolve(EntityLinkRegistry::class)->activityTypeLabels();
        $config = resolve(EntityLinkRegistry::class)->allLinkTypeConfig();
        $totalLinks = array_sum($typeTotals);

        $result = [];
        foreach ($typeTotals as $type => $count) {
            $typeConfig = $config[$type] ?? null;

            $result[] = [
                'type' => $type,
                'label' => $typeConfig['label'] ?? $labels[$type] ?? $type,
                'icon' => $this->resolveHeroicon($typeConfig['icon'] ?? 'link'),
                'count' => $count,
                'percentage' => $totalLinks > 0 ? round(($count / $totalLinks) * 100, 1) : 0,
            ];
        }

        return $result;
    }

    /**
     * Normalisiert einen (ggf. lucide-artigen) Icon-Namen auf einen gültigen
     * heroicon-Namen und garantiert, dass das Icon existiert – sonst Fallback,
     * damit @svg('heroicon-o-...') im Blade nie mit SvgNotFound crasht.
     */
    protected function resolveHeroicon(string $icon): string
    {
        $iconMap = [
            'user-check' => 'user',
            'user-voice' => 'user',
            'folder-kanban' => 'folder',
            'briefcase-globe' => 'briefcase',
            'server-cog' => 'server',
            'package-check' => 'archive-box',
            'badge-check' => 'check-badge',
            'target' => 'viewfinder-circle',
            'arrow-right-left' => 'arrows-right-left',
        ];

        $icon = $iconMap[$icon] ?? $icon;

        try {
            app(\BladeUI\Icons\Factory::class)->svg('heroicon-o-' . $icon);
            return $icon;
        } catch (\Throwable $e) {
            return 'link';
        }
    }

    #[Computed]
    public function topEntitiesByActivity(): array
    {
        return $this->panel('topEntitiesByActivity', fn () => $this->computeTopEntitiesByActivity());
    }

    protected function computeTopEntitiesByActivity(): array
    {
        $teamId = $this->getTeamId();
        if (!$teamId) {
            return [];
        }

        $entityIds = OrganizationEntity::forTeam($teamId)->pluck('id');
        if ($entityIds->isEmpty()) {
            return [];
        }

        $latestSnapshots = $this->getLatestSnapshotsForEntities($entityIds);
        $agoSnapshots = $this->getSnapshotsNearDate($entityIds, now()->subDays(7));

        $entities = OrganizationEntity::forTeam($teamId)->with('type')->get()->keyBy('id');
        $activities = [];

        foreach ($entityIds as $id) {
            $current = $latestSnapshots[$id] ?? null;
            $ago = $agoSnapshots[$id] ?? null;

            if (!$current) continue;

            $cm = $current->metrics;
            $am = $ago ? $ago->metrics : [];

            $itemsCompleted7d = max(0,
                ($cm['items_done_cascaded'] ?? $cm['items_done'] ?? 0)
                - ($am['items_done_cascaded'] ?? $am['items_done'] ?? 0)
            );
            $timeLogged7d = max(0,
                ($cm['time_total_minutes_cascaded'] ?? $cm['time_total_minutes'] ?? 0)
                - ($am['time_total_minutes_cascaded'] ?? $am['time_total_minutes'] ?? 0)
            );
            $activityScore = $itemsCompleted7d + ($timeLogged7d / 60); // weight hours equally to items

            if ($activityScore <= 0) continue;

            $entity = $entities[$id] ?? null;
            if (!$entity) continue;

            $activities[] = [
                'id' => $id,
                'name' => $entity->name,
                'type_name' => $entity->type->name ?? '',
                'items_completed_7d' => $itemsCompleted7d,
                'hours_7d' => round($timeLogged7d / 60, 1),
            ];
        }

        // Sort by activity score (items + hours) descending
        usort($activities, fn($a, $b) => ($b['items_completed_7d'] + $b['hours_7d']) <=> ($a['items_completed_7d'] + $a['hours_7d']));

        return array_slice($activities, 0, 5);
    }

    #[Computed]
    public function personOverview(): array
    {
        return $this->panel('personOverview', fn () => $this->computePersonOverview());
    }

    protected function computePersonOverview(): array
    {
        $empty = ['persons' => [], 'totals' => [], 'metric_configs' => [], 'person_count' => 0];

        $teamId = $this->getTeamId();
        if (!$teamId) {
            return $empty;
        }

        $registry = resolve(PersonActivityRegistry::class);
        if (!$registry->hasProviders()) {
            return $empty;
        }

        $metricConfigs = $registry->allMetricConfigs();
        if (empty($metricConfigs)) {
            return $empty;
        }

        $personEntities = OrganizationEntity::forTeam($teamId)
            ->whereNotNull('linked_user_id')
            ->with('type')
            ->get();

        if ($personEntities->isEmpty()) {
            return $empty;
        }

        $entityIds = $personEntities->pluck('id');
        $latestSnapshots = $this->getLatestSnapshotsForEntities($entityIds);

        // Only consider metrics with type warning or danger for filtering/sorting
        $relevantKeys = array_keys(array_filter($metricConfigs, fn($c) => in_array($c['type'], ['warning', 'danger'])));

        $persons = [];
        $totals = array_fill_keys(array_keys($metricConfigs), 0);

        foreach ($personEntities as $entity) {
            $snap = $latestSnapshots[$entity->id] ?? null;
            if (!$snap) continue;

            $snapshotMetrics = $snap->metrics;

            // Extract all person_* metrics from snapshot
            $personMetrics = [];
            $hasRelevant = false;
            foreach ($metricConfigs as $snapshotKey => $config) {
                $value = $snapshotMetrics[$snapshotKey] ?? 0;
                $personMetrics[$snapshotKey] = $value;
                $totals[$snapshotKey] += $value;
                if ($value > 0 && in_array($snapshotKey, $relevantKeys)) {
                    $hasRelevant = true;
                }
            }

            if (!$hasRelevant) continue;

            // Compute sort score: weighted sum of relevant metrics
            $sortScore = 0;
            foreach ($metricConfigs as $snapshotKey => $config) {
                $sortScore += ($personMetrics[$snapshotKey] ?? 0) * ($config['sort_weight'] ?? 0);
            }

            $persons[] = [
                'id' => $entity->id,
                'name' => $entity->name,
                'type_name' => $entity->type->name ?? '',
                'metrics' => $personMetrics,
                'sort_score' => $sortScore,
            ];
        }

        usort($persons, fn($a, $b) => $b['sort_score'] <=> $a['sort_score']);

        return [
            'persons' => array_slice($persons, 0, 8),
            'totals' => $totals,
            'metric_configs' => $metricConfigs,
            'person_count' => count($persons),
        ];
    }

    #[Computed]
    public function insightStatements(): array
    {
        return $this->panel('insightStatements', fn () => $this->computeInsightStatements());
    }

    protected function computeInsightStatements(): array
    {
        $statements = [];
        $summary = $this->teamSnapshotSummary;
        $health = $this->entityHealthOverview;
        $time = $this->teamTimeAnalytics;
        $velocity = $this->completionVelocity;

        // Completion insight
        if ($summary['has_data'] && $summary['items_total'] > 0) {
            $text = "{$summary['completion_rate']}% aller Items abgeschlossen";
            if ($summary['trend_completion'] > 0) {
                $text .= " — {$summary['trend_completion']}% mehr als vor einer Woche.";
                $statements[] = ['text' => $text, 'type' => 'success'];
            } elseif ($summary['trend_completion'] < 0) {
                $text .= " — " . abs($summary['trend_completion']) . "% weniger als vor einer Woche.";
                $statements[] = ['text' => $text, 'type' => 'warning'];
            } else {
                $text .= ".";
                $statements[] = ['text' => $text, 'type' => 'info'];
            }
        }

        // Stalled entities
        $stalledCount = $health['counts']['stalled'] + $health['counts']['at_risk'];
        if ($stalledCount > 0) {
            $statements[] = [
                'text' => "{$stalledCount} " . ($stalledCount === 1 ? 'Einheit' : 'Einheiten') . " ohne Fortschritt seit 7 Tagen.",
                'type' => 'warning',
            ];
        }

        // Velocity
        if ($velocity['avg_per_week'] > 0) {
            $trendLabel = match ($velocity['trend']) {
                'accelerating' => ' (beschleunigend)',
                'decelerating' => ' (verlangsamend)',
                default => '',
            };
            $statements[] = [
                'text' => "Wöchentliche Erledigungsrate: Ø {$velocity['avg_per_week']} Items{$trendLabel}.",
                'type' => $velocity['trend'] === 'decelerating' ? 'warning' : 'info',
            ];
        }

        // Billing
        if ($time['has_data'] && $time['billing_rate'] > 0) {
            $text = "Abrechnungsquote bei {$time['billing_rate']}%";
            if ($time['trend_billing'] < 0) {
                $text .= " — " . abs($time['trend_billing']) . "% unter Vormonat.";
                $statements[] = ['text' => $text, 'type' => 'warning'];
            } elseif ($time['trend_billing'] > 0) {
                $text .= " — {$time['trend_billing']}% über Vormonat.";
                $statements[] = ['text' => $text, 'type' => 'success'];
            } else {
                $text .= ".";
                $statements[] = ['text' => $text, 'type' => 'info'];
            }
        }

        // Movement insights (domain-specific)
        $movement = $this->teamMovement;
        if (!empty($movement['summary'])) {
            foreach ($movement['summary'] as $insight) {
                $statements[] = $insight;
            }
        }

        // Signal alerts
        $signalData = $this->signalOverview;
        if ($signalData['total_open'] > 0) {
            $criticalCount = $signalData['signals']->where('severity', 'critical')->count();
            if ($criticalCount > 0) {
                $statements[] = [
                    'text' => "{$criticalCount} kritische" . ($criticalCount === 1 ? 's Signal' : ' Signale') . " offen — Handlung erforderlich.",
                    'type' => 'warning',
                ];
            } elseif ($signalData['total_open'] > 0) {
                $statements[] = [
                    'text' => "{$signalData['total_open']} offene" . ($signalData['total_open'] === 1 ? 's Signal' : ' Signale') . ".",
                    'type' => 'info',
                ];
            }
        }

        // Person danger metrics (generic)
        $personData = $this->personOverview;
        if ($personData['person_count'] > 0) {
            foreach ($personData['metric_configs'] as $snapshotKey => $config) {
                if ($config['type'] !== 'danger') continue;
                $total = $personData['totals'][$snapshotKey] ?? 0;
                if ($total <= 0) continue;
                $personCount = $personData['person_count'];
                $statements[] = [
                    'text' => "{$total}x {$config['label']} bei {$personCount} " . ($personCount === 1 ? 'Person' : 'Personen') . ".",
                    'type' => 'warning',
                ];
            }
        }

        return array_slice($statements, 0, 5);
    }

    public function unfocusSignal(int $signalId): void
    {
        $userId = auth()->id();
        if (! $userId) {
            return;
        }

        \Platform\Organization\Models\OrganizationSignalFocus::where('signal_id', $signalId)
            ->where('user_id', $userId)
            ->delete();

        unset($this->focusedSignals, $this->attention);
    }

    #[Computed]
    public function focusedSignals()
    {
        $teamId = $this->getTeamId();
        $userId = auth()->id();

        if (! $teamId || ! $userId) {
            return collect();
        }

        // Only show actively-relevant focused signals — resolved/dismissed shouldn't surface here
        return OrganizationSignal::forTeam($teamId)
            ->focusedBy($userId)
            ->whereIn('status', ['open', 'acknowledged'])
            ->with(['entity:id,name', 'definition:id,name'])
            ->orderByRaw("FIELD(severity, 'algedonic', 'critical', 'warning', 'info')")
            ->orderByDesc('created_at')
            ->get();
    }

    #[Computed]
    public function signalOverview(): array
    {
        $teamId = $this->getTeamId();
        if (!$teamId) {
            return ['counts' => [], 'signals' => [], 'total_open' => 0];
        }

        $counts = OrganizationSignal::where('team_id', $teamId)
            ->selectRaw("status, COUNT(*) as cnt")
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        $totalOpen = ($counts['open'] ?? 0) + ($counts['acknowledged'] ?? 0);

        $signals = OrganizationSignal::where('team_id', $teamId)
            ->whereIn('status', ['open', 'acknowledged'])
            ->with(['definition:id,name,pattern_type', 'entity:id,name'])
            ->orderByRaw("FIELD(severity, 'critical', 'warning', 'info')")
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        return [
            'counts' => $counts,
            'signals' => $signals,
            'total_open' => $totalOpen,
        ];
    }

    // --- Helper Methods ---

    protected function emptySnapshotSummary(): array
    {
        return [
            'items_total' => 0,
            'items_done' => 0,
            'completion_rate' => 0,
            'time_total_minutes' => 0,
            'time_billed_minutes' => 0,
            'billing_rate' => 0,
            'trend_completion' => 0,
            'trend_billing' => 0,
            'has_data' => false,
        ];
    }

    protected function emptyTimeAnalytics(): array
    {
        return [
            'hours_this_month' => 0,
            'hours_last_month' => 0,
            'trend_hours' => 0,
            'billed_minutes' => 0,
            'open_minutes' => 0,
            'billing_rate' => 0,
            'trend_billing' => 0,
            'has_data' => false,
        ];
    }

    /** Request-Memo für Snapshot-Abfragen (mehrere Kacheln fragen dieselben Stichtage ab). */
    protected array $snapshotMemo = [];


    /**
     * Get the latest snapshot for each entity.
     * Returns: [entity_id => OrganizationEntitySnapshot]
     */
    protected function getLatestSnapshotsForEntities($entityIds): array
    {
        return $this->snapshotsAtOrBefore($entityIds, null);
    }

    /**
     * Get the snapshot closest to a given date for each entity.
     * Returns: [entity_id => OrganizationEntitySnapshot]
     */
    protected function getSnapshotsNearDate($entityIds, Carbon $date): array
    {
        return $this->snapshotsAtOrBefore($entityIds, $date->toDateString());
    }

    /**
     * Jüngster Snapshot je Entity am/vor $date (null = überhaupt). Gemerkt pro
     * Request und gecacht über Requests (vorher ~16 identische Scans je Render).
     */
    protected function snapshotsAtOrBefore($entityIds, ?string $date): array
    {
        $ids = collect($entityIds)->map(fn ($id) => (int) $id)->sort()->values()->all();
        if ($ids === []) {
            return [];
        }
        $key = 'organization:dashboard-snapshots:' . md5(implode(',', $ids)) . ':' . ($date ?? 'latest')
            . ':v' . Cache::get(self::SNAPSHOT_VERSION_KEY, 0);

        return $this->snapshotMemo[$key] ??= Cache::remember($key, self::SNAPSHOT_CACHE_TTL, function () use ($ids, $date) {
            $maxDates = OrganizationEntitySnapshot::whereIn('entity_id', $ids)
                ->when($date, fn ($q) => $q->where('snapshot_date', '<=', $date))
                ->select('entity_id', DB::raw('MAX(snapshot_date) as max_date'))
                ->groupBy('entity_id');

            return OrganizationEntitySnapshot::joinSub($maxDates, 'near', function ($join) {
                $join->on('organization_entity_snapshots.entity_id', '=', 'near.entity_id')
                     ->on('organization_entity_snapshots.snapshot_date', '=', 'near.max_date');
            })->get()->keyBy('entity_id')->all();
        });
    }

    protected function aggregateSnapshotMetrics(array $snapshots): array
    {
        $totals = ['items_total' => 0, 'items_done' => 0, 'time_total_minutes' => 0, 'time_billed_minutes' => 0];

        foreach ($snapshots as $snap) {
            $m = $snap->metrics;
            $totals['items_total'] += $m['items_total'] ?? 0;
            $totals['items_done'] += $m['items_done'] ?? 0;
            $totals['time_total_minutes'] += $m['time_total_minutes'] ?? 0;
            $totals['time_billed_minutes'] += $m['time_billed_minutes'] ?? 0;
        }

        return $totals;
    }

    protected function classifyEntityHealth(int $itemsTotal, int $itemsDone, int $agoItemsTotal, int $agoItemsDone): string
    {
        // Completed: all done and has items
        if ($itemsDone >= $itemsTotal && $itemsTotal > 0) {
            return 'completed';
        }

        // At risk: total grew but done did not
        if ($itemsTotal > $agoItemsTotal && $itemsDone <= $agoItemsDone && $itemsTotal > 0) {
            return 'at_risk';
        }

        // Stalled: done unchanged, open items exist
        if ($itemsDone <= $agoItemsDone && ($itemsTotal - $itemsDone) > 0) {
            return 'stalled';
        }

        // Progressing: done increased
        if ($itemsDone > $agoItemsDone) {
            return 'progressing';
        }

        return 'progressing'; // default for entities without prior data
    }

    #[Computed]
    public function environmentRadar(): array
    {
        return $this->panel('environmentRadar', fn () => $this->computeEnvironmentRadar());
    }

    protected function computeEnvironmentRadar(): array
    {
        $teamId = $this->getTeamId();
        if (!$teamId) {
            return ['sources' => [], 'has_data' => false];
        }

        $sources = OrganizationEnvironmentSource::forTeam($teamId)
            ->active()
            ->get();

        if ($sources->isEmpty()) {
            return ['sources' => [], 'has_data' => false];
        }

        $service = resolve(EnvironmentMovementService::class);
        $categoryColors = [
            'branche' => 'indigo',
            'technologie' => 'cyan',
            'wirtschaft' => 'amber',
            'arbeitsmarkt' => 'rose',
            'regulatorik' => 'red',
            'wetter' => 'sky',
            'wettbewerb' => 'orange',
        ];

        $grouped = [];
        foreach ($sources as $source) {
            $movement = $service->forSource($source->id);
            $category = $source->category ?? 'sonstige';

            $entry = [
                'id' => $source->id,
                'name' => $source->name,
                'category' => $category,
                'category_color' => $categoryColors[$category] ?? 'gray',
                'last_pulled_at' => $source->last_pulled_at?->format('d.m. H:i'),
                'has_snapshot' => $movement['has_data'],
                'sentiment_score' => null,
                'relevance_score' => null,
                'summary' => null,
                'topics' => [],
                'delta' => null,
            ];

            if ($movement['has_data']) {
                $metrics = $movement['latest']['metrics'] ?? [];
                $entry['sentiment_score'] = $metrics['sentiment_score'] ?? null;
                $entry['relevance_score'] = $metrics['relevance_score'] ?? null;
                $entry['summary'] = $movement['latest']['summary'] ?? null;
                $entry['topics'] = $metrics['topics'] ?? [];
                $entry['delta'] = $movement['delta'];
            }

            $grouped[$category][] = $entry;
        }

        $allSources = [];
        foreach ($grouped as $entries) {
            foreach ($entries as $entry) {
                $allSources[] = $entry;
            }
        }

        return [
            'sources' => $allSources,
            'has_data' => count($allSources) > 0,
        ];
    }

    public function timings(): array
    {
        return $this->timings;
    }

    public function render()
    {
        return view('organization::livewire.dashboard')
            ->layout('platform::layouts.app');
    }
}
