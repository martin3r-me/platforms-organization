<?php

namespace Platform\Organization\Verbalization;

use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Platform\Core\Verbalization\Enums\DataSource;
use Platform\Core\Verbalization\Enums\FactNature;
use Platform\Core\Verbalization\Enums\FactPriority;
use Platform\Core\Verbalization\Enums\SubjectKind;
use Platform\Core\Verbalization\Fact;
use Platform\Core\Verbalization\Freshness;
use Platform\Core\Verbalization\Identity;
use Platform\Core\Verbalization\Recipe\CollectionRecipe;
use Platform\Core\Verbalization\Subject;
use Platform\Core\Verbalization\SubjectCollector\SubjectCollectorInterface;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationSignal;

/**
 * Sammler fuer Signal-Berichte pro Entity (VSM-Signale nach Stafford Beer).
 *
 * Input: entity_id oder OrganizationEntity-Instanz.
 * Output: Subject mit Facts ueber offene Signale, Bewegung seit $since und
 *         Ableitungen (Algedonic aktiv, S4/S5-Uebergewicht, Aggregation nach oben).
 *
 * Recipes koennen ueber `sources` einzelne Fact-Gruppen an- und abschalten und
 * ueber `include_natures` das inhaltliche Profil bestimmen (state / movement /
 * derivation). Damit sind reine Alarm-Feeds, tageszyklische Ticker und
 * Executive-Zustandsberichte alle mit denselben Facts machbar.
 */
class OrganizationSignalsSubjectCollector implements SubjectCollectorInterface
{
    private const DEFAULT_SOURCES = [
        'signal_load' => true,          // STATE: N offen, Severity-Verteilung
        'vsm_distribution' => true,     // STATE: Verteilung ueber S1-S5
        'signal_headlines' => true,     // STATE: Top-Message-Vorschau
        'new_signals' => true,          // MOVEMENT: seit $since neu
        'resolved_signals' => true,     // MOVEMENT: seit $since resolved
        'aggregation_flow' => true,     // MOVEMENT: seit $since eskaliert
        'algedonic_alert' => true,      // DERIVATION: Algedonic-Kanal aktiv
        'vsm_focus' => true,            // DERIVATION: S4/S5-Uebergewicht
    ];

    public function handles(): string
    {
        return 'organization_signals';
    }

    public function collectState(
        mixed $subject,
        ?CollectionRecipe $recipe = null,
        ?\DateTimeInterface $since = null,
    ): Subject {
        // $subject akzeptiert: OrganizationEntity, ID (int/string) oder Array von IDs
        // (fuer rekursive Aufrufe aus entity_pulse ueber den Sub-Baum).
        if (is_array($subject)) {
            $entityIds = array_values(array_map('intval', $subject));
            $rootId = $entityIds[0] ?? 0;
            $rootEntity = OrganizationEntity::find($rootId);
            $primaryName = $rootEntity?->name ?? ('Entity #' . $rootId);
            $subjectId = (string) $rootId;
            $slug = $rootEntity?->uuid ?? $subjectId;
        } else {
            if (! $subject instanceof OrganizationEntity) {
                $subject = OrganizationEntity::findOrFail($subject);
            }
            $entityIds = [(int) $subject->id];
            $primaryName = $subject->name ?? ('Entity #' . $subject->id);
            $subjectId = (string) $subject->id;
            $slug = $subject->uuid ?? $subjectId;

            // Recipe-Rekursion: sources.descend erweitert den Scope auf Descendants.
            // Nur bei nicht-Array-Input relevant — Array-Input kommt schon vor-
            // aufgeloest vom Pulse-Aggregator.
            $descend = $recipe && is_array($recipe->sources)
                ? ($recipe->sources['descend'] ?? false)
                : false;
            if ($descend !== false && $descend !== null) {
                $entityIds = $this->collectDescendantScope((int) $subject->id, $descend);
                // Suffix wird unten in $scopeLabel einmal gesetzt — hier nicht doppeln.
            }
        }

        $isOn = $recipe
            ? fn (string $key) => $recipe->hasSource($key)
            : fn (string $key) => (bool) (self::DEFAULT_SOURCES[$key] ?? false);

        // Live-Basis: alle nicht-terminalen Signale ueber alle Scope-Entities.
        $live = OrganizationSignal::whereIn('entity_id', $entityIds)
            ->whereIn('status', ['open', 'acknowledged'])
            ->get();

        $facts = array_merge(
            $isOn('signal_load') ? $this->factsSignalLoad($live) : [],
            $isOn('vsm_distribution') ? $this->factsVsmDistribution($live) : [],
            $isOn('signal_headlines') ? $this->factsHeadlines($live) : [],
            $isOn('new_signals') && $since ? $this->factsNewSignals($entityIds, $since) : [],
            $isOn('resolved_signals') && $since ? $this->factsResolvedSignals($entityIds, $since) : [],
            $isOn('aggregation_flow') && $since ? $this->factsAggregationFlow($entityIds, $since) : [],
            $isOn('algedonic_alert') ? $this->factsAlgedonicAlert($live) : [],
            $isOn('vsm_focus') ? $this->factsVsmFocus($live) : [],
        );

        $now = new DateTimeImmutable();
        $scopeLabel = count($entityIds) > 1
            ? 'Signale zu ' . $primaryName . ' (inkl. ' . (count($entityIds) - 1) . ' Sub-Ebenen)'
            : 'Signale zu ' . $primaryName;
        return new Subject(
            kind: SubjectKind::STATE,
            type: 'organization_signals',
            id: $subjectId,
            identity: new Identity(
                primaryName: $scopeLabel,
                shortLabel: $primaryName,
                slug: $slug,
            ),
            facts: $facts,
            edges: [],
            freshness: new Freshness(source: DataSource::LIVE, asOf: $now),
        );
    }

    /** @return Fact[] */
    protected function factsSignalLoad($live): array
    {
        $total = $live->count();
        if ($total === 0) {
            return [new Fact(
                FactPriority::CORE,
                'Keine offenen Signale an dieser Entity.',
                'signals.load.zero',
                FactNature::STATE,
            )];
        }
        $bySeverity = $live->groupBy('severity')->map->count();
        $critical = (int) ($bySeverity['critical'] ?? 0);
        $warning = (int) ($bySeverity['warning'] ?? 0);
        $info = (int) ($bySeverity['info'] ?? 0);

        $parts = [];
        if ($critical > 0) $parts[] = "{$critical} kritisch";
        if ($warning > 0) $parts[] = "{$warning} Warnung" . ($warning > 1 ? 'en' : '');
        if ($info > 0) $parts[] = "{$info} Info";

        $tail = $parts ? ' (' . implode(', ', $parts) . ')' : '';
        return [new Fact(
            FactPriority::CORE,
            "Aktuell {$total} offene Signale{$tail}.",
            'signals.load',
            FactNature::STATE,
        )];
    }

    /** @return Fact[] */
    protected function factsVsmDistribution($live): array
    {
        if ($live->isEmpty()) {
            return [];
        }
        $byLevel = $live->groupBy('vsm_level')->map->count();
        $order = OrganizationSignal::VSM_LEVELS;
        $parts = [];
        foreach ($order as $level) {
            $count = (int) ($byLevel[$level] ?? 0);
            if ($count > 0) {
                $parts[] = strtoupper(str_replace('_', '*', $level)) . ": {$count}";
            }
        }
        if (empty($parts)) {
            return [];
        }
        return [new Fact(
            FactPriority::QUALIFYING,
            'Verteilung nach VSM-Ebene: ' . implode(', ', $parts) . '.',
            'signals.vsm_distribution',
            FactNature::STATE,
        )];
    }

    /** @return Fact[] */
    protected function factsHeadlines($live, int $topN = 3): array
    {
        if ($live->isEmpty()) {
            return [];
        }
        $order = ['critical' => 0, 'warning' => 1, 'info' => 2];
        $top = $live
            ->sortBy(fn ($s) => $order[$s->severity] ?? 9)
            ->take($topN);
        $lines = $top->map(function ($s) {
            $msg = trim((string) $s->message);
            if ($msg === '') $msg = 'Signal ohne Text';
            if (mb_strlen($msg) > 100) $msg = mb_substr($msg, 0, 97) . '...';
            $level = strtoupper(str_replace('_', '*', (string) $s->vsm_level));
            return "[{$level}/{$s->severity}] {$msg}";
        })->values()->implode(' | ');
        return [new Fact(
            FactPriority::QUALIFYING,
            'Wichtigste offene Signale: ' . $lines,
            'signals.headlines',
            FactNature::STATE,
        )];
    }

    /** @param int[] $entityIds  @return Fact[] */
    protected function factsNewSignals(array $entityIds, \DateTimeInterface $since): array
    {
        $new = OrganizationSignal::whereIn('entity_id', $entityIds)
            ->where('created_at', '>=', $since)
            ->get();
        if ($new->isEmpty()) {
            return [new Fact(
                FactPriority::QUALIFYING,
                'Keine neuen Signale ' . $this->humanizeSince($since) . '.',
                'signals.new.none',
                FactNature::MOVEMENT,
            )];
        }
        $count = $new->count();
        $critical = $new->where('severity', 'critical')->count();
        $tail = $critical > 0 ? " (davon {$critical} kritisch)" : '';
        return [new Fact(
            FactPriority::CORE,
            "{$count} neue Signale " . $this->humanizeSince($since) . "{$tail}.",
            'signals.new.count',
            FactNature::MOVEMENT,
        )];
    }

    /** @param int[] $entityIds  @return Fact[] */
    protected function factsResolvedSignals(array $entityIds, \DateTimeInterface $since): array
    {
        $resolved = OrganizationSignal::whereIn('entity_id', $entityIds)
            ->whereIn('status', ['resolved', 'dismissed'])
            ->where('resolved_at', '>=', $since)
            ->get();
        if ($resolved->isEmpty()) {
            return [];
        }
        $count = $resolved->count();
        return [new Fact(
            FactPriority::QUALIFYING,
            "{$count} Signale " . $this->humanizeSince($since) . ' abgeschlossen.',
            'signals.resolved.count',
            FactNature::MOVEMENT,
        )];
    }

    /** @param int[] $entityIds  @return Fact[] */
    protected function factsAggregationFlow(array $entityIds, \DateTimeInterface $since): array
    {
        $aggregatedOut = OrganizationSignal::whereIn('entity_id', $entityIds)
            ->whereNotNull('aggregated_at')
            ->where('aggregated_at', '>=', $since)
            ->count();
        if ($aggregatedOut === 0) {
            return [];
        }
        return [new Fact(
            FactPriority::QUALIFYING,
            "{$aggregatedOut} Signale " . $this->humanizeSince($since) . ' nach oben eskaliert (Aggregation).',
            'signals.aggregation.escalated',
            FactNature::MOVEMENT,
        )];
    }

    /** @return Fact[] */
    protected function factsAlgedonicAlert($live): array
    {
        $algedonic = $live->filter(fn ($s) => $s->source_type === OrganizationSignal::SOURCE_TYPE_HUMAN_ALGEDONIC
            || $s->severity === 'algedonic');
        if ($algedonic->isEmpty()) {
            return [];
        }
        $count = $algedonic->count();
        $singular = $count === 1 ? 'Algedonic-Signal aktiv' : "{$count} Algedonic-Signale aktiv";
        return [new Fact(
            FactPriority::CORE,
            "{$singular} — sofortige Aufmerksamkeit erforderlich.",
            'signals.algedonic',
            FactNature::DERIVATION,
        )];
    }

    /** @return Fact[] */
    protected function factsVsmFocus($live): array
    {
        $total = $live->count();
        if ($total < 3) {
            return [];
        }
        $strategic = $live->whereIn('vsm_level', ['s4', 's5'])->count();
        if ($strategic / $total < 0.4) {
            return [];
        }
        return [new Fact(
            FactPriority::QUALIFYING,
            "Strategische Ebenen dominieren: {$strategic} von {$total} offenen Signalen liegen auf S4 oder S5.",
            'signals.vsm_focus.strategic',
            FactNature::DERIVATION,
        )];
    }

    protected function humanizeSince(\DateTimeInterface $since): string
    {
        $sinceCarbon = Carbon::parse($since->format('c'));
        $days = (int) $sinceCarbon->diffInDays(now());
        if ($days <= 1) return 'seit gestern';
        if ($days <= 3) return 'in den letzten Tagen';
        if ($days <= 7) return 'in dieser Woche';
        if ($days <= 14) return 'in den letzten zwei Wochen';
        return 'seit dem ' . $sinceCarbon->format('d.m.');
    }

    /**
     * Root + alle Descendants (parent_entity_id, breadth-first).
     * $descend: true = alle Ebenen; int = maximale Tiefe.
     *
     * @return int[]
     */
    protected function collectDescendantScope(int $rootId, mixed $descend): array
    {
        $maxDepth = ($descend === true) ? null : max(0, (int) $descend);
        $visited = [$rootId => true];
        $result = [$rootId];
        $queue = [[$rootId, 0]];

        while (! empty($queue)) {
            [$id, $depth] = array_shift($queue);
            if ($maxDepth !== null && $depth >= $maxDepth) {
                continue;
            }
            $children = \DB::table('organization_entities')
                ->where('parent_entity_id', $id)
                ->pluck('id')
                ->all();
            foreach ($children as $cid) {
                $cid = (int) $cid;
                if (isset($visited[$cid])) {
                    continue;
                }
                $visited[$cid] = true;
                $result[] = $cid;
                $queue[] = [$cid, $depth + 1];
            }
        }
        return $result;
    }
}
