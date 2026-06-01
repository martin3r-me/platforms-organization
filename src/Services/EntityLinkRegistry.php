<?php

namespace Platform\Organization\Services;

use Platform\Organization\Contracts\EntityLinkProvider;
use Platform\Organization\Contracts\HasCostDriverMetrics;
use Platform\Organization\Contracts\HasMetricDefinitions;
use Platform\Organization\Contracts\HasPersonMetrics;

class EntityLinkRegistry
{
    // 7½ Dimensionen
    public const DIMENSION_COMPLEXITY  = 'complexity';
    public const DIMENSION_ENERGY      = 'energy';
    public const DIMENSION_THROUGHPUT  = 'throughput';
    public const DIMENSION_ORG_CAPITAL = 'org_capital';
    public const DIMENSION_COSTS       = 'costs';
    public const DIMENSION_REVENUE     = 'revenue';
    public const DIMENSION_POTENTIAL   = 'potential';
    public const DIMENSION_QUALITY     = 'quality';

    // Metrik-Typen
    public const TYPE_STOCK     = 'stock';
    public const TYPE_FLOW      = 'flow';
    public const TYPE_MODULATOR = 'modulator';

    /** @var EntityLinkProvider[] */
    protected array $providers = [];

    /** @var array<string, EntityLinkProvider> morph alias => provider */
    protected array $aliasMap = [];

    protected ?array $cachedLinkTypeConfig = null;
    protected ?array $cachedDisplayRules = null;
    protected ?array $cachedTimeTrackableCascades = null;
    protected ?array $cachedMetricDefinitions = null;

    public function register(EntityLinkProvider $provider): void
    {
        $this->providers[] = $provider;

        foreach ($provider->morphAliases() as $alias) {
            $this->aliasMap[$alias] = $provider;
        }

        // Invalidate caches
        $this->cachedLinkTypeConfig = null;
        $this->cachedDisplayRules = null;
        $this->cachedTimeTrackableCascades = null;
        $this->cachedMetricDefinitions = null;
    }

    public function getProvider(string $morphAlias): ?EntityLinkProvider
    {
        return $this->aliasMap[$morphAlias] ?? null;
    }

    /**
     * Merged linkTypeConfig from all providers.
     * @return array<string, array{label: string, icon: string, route: string|null}>
     */
    public function allLinkTypeConfig(): array
    {
        if ($this->cachedLinkTypeConfig === null) {
            $this->cachedLinkTypeConfig = [];
            foreach ($this->providers as $provider) {
                foreach ($provider->linkTypeConfig() as $alias => $config) {
                    $this->cachedLinkTypeConfig[$alias] = $config;
                }
            }
        }

        return $this->cachedLinkTypeConfig;
    }

    /**
     * Merged metadataDisplayRules from all providers.
     * @return array<string, array>
     */
    public function allMetadataDisplayRules(): array
    {
        if ($this->cachedDisplayRules === null) {
            $this->cachedDisplayRules = [];
            foreach ($this->providers as $provider) {
                foreach ($provider->metadataDisplayRules() as $alias => $rules) {
                    $this->cachedDisplayRules[$alias] = $rules;
                }
            }
        }

        return $this->cachedDisplayRules;
    }

    /**
     * Merged timeTrackableCascades from all providers.
     * @return array<string, array{0: class-string, 1: string[]}>
     */
    public function allTimeTrackableCascades(): array
    {
        if ($this->cachedTimeTrackableCascades === null) {
            $this->cachedTimeTrackableCascades = [];
            foreach ($this->providers as $provider) {
                foreach ($provider->timeTrackableCascades() as $alias => $config) {
                    $this->cachedTimeTrackableCascades[$alias] = $config;
                }
            }
        }

        return $this->cachedTimeTrackableCascades;
    }

    /**
     * Resolve zusaetzliche Activity-relevante Child-Models ueber alle Provider.
     * Input:  [morphKey => [ids]] (direkt verlinkte Models, Keys sind Morph-Aliases oder FQCNs)
     * Output: [morphKey => [ids]] (zusaetzliche Child-Models, Keys wie in DB gespeichert)
     *
     * @param array<string, int[]> $directPairs
     * @return array<string, int[]>
     */
    public function resolveActivityChildren(array $directPairs): array
    {
        $morphMap = \Illuminate\Database\Eloquent\Relations\Relation::morphMap();
        $fqcnToAlias = array_flip($morphMap);
        $result = [];

        foreach ($directPairs as $morphKey => $ids) {
            if (empty($ids)) {
                continue;
            }

            // morphKey kann Alias ('project') oder FQCN sein
            $alias = isset($this->aliasMap[$morphKey]) ? $morphKey : ($fqcnToAlias[$morphKey] ?? null);
            if (!$alias) {
                continue;
            }

            $provider = $this->getProvider($alias);
            if (!$provider) {
                continue;
            }

            // Provider gibt [FQCN => [ids]] zurück, wir konvertieren zu Morph-Keys
            $children = $provider->activityChildren($alias, $ids);
            foreach ($children as $childFqcn => $childIds) {
                $childMorphKey = $fqcnToAlias[$childFqcn] ?? $childFqcn;
                $result[$childMorphKey] = array_merge($result[$childMorphKey] ?? [], $childIds);
            }
        }

        // Deduplizieren
        foreach ($result as $key => $ids) {
            $result[$key] = array_values(array_unique($ids));
        }

        return $result;
    }

    /**
     * All registered providers that implement HasPersonMetrics.
     *
     * @return HasPersonMetrics[]
     */
    public function personMetricsProviders(): array
    {
        return array_filter(
            $this->providers,
            fn ($p) => $p instanceof HasPersonMetrics
        );
    }

    /**
     * Label-Map fuer Activity Feed: morphKey => singular Label.
     * Keys sind Morph-Aliases (wie in der DB gespeichert).
     *
     * @return array<string, string>
     */
    public function activityTypeLabels(): array
    {
        $config = $this->allLinkTypeConfig();
        $labels = [];

        foreach ($config as $alias => $cfg) {
            if (isset($cfg['singular'])) {
                $labels[$alias] = $cfg['singular'];
            }
        }

        return $labels;
    }

    /**
     * All metric definitions from providers + built-in.
     *
     * @return array<string, array{label: string, group: string, direction: string, unit: string, pair?: string}>
     */
    public function allMetricDefinitions(): array
    {
        if ($this->cachedMetricDefinitions !== null) {
            return $this->cachedMetricDefinitions;
        }

        $defs = $this->builtInMetricDefinitions();

        foreach ($this->providers as $provider) {
            if ($provider instanceof HasMetricDefinitions) {
                foreach ($provider->metricDefinitions() as $key => $def) {
                    $defs[$key] = $def;
                }
            }
        }

        // Apply defaults for dimension/type/aggregation if not set by provider
        foreach ($defs as $key => &$def) {
            if (!isset($def['dimension'])) {
                $def['dimension'] = null;
            }
            if (!isset($def['type'])) {
                $def['type'] = self::TYPE_STOCK;
            }
            if (!isset($def['aggregation_mode'])) {
                $def['aggregation_mode'] = 'own';
            }
            if (!isset($def['roll_up_function'])) {
                $def['roll_up_function'] = 'sum';
            }
        }
        unset($def);

        return $this->cachedMetricDefinitions = $defs;
    }

    /**
     * Metric definitions filtered by group.
     */
    public function metricDefinitionsForGroup(string $group): array
    {
        return array_filter(
            $this->allMetricDefinitions(),
            fn (array $def) => $def['group'] === $group
        );
    }

    /**
     * Metric definitions filtered by dimension (7½ Dimensionen).
     */
    public function metricDefinitionsForDimension(string $dimension): array
    {
        return array_filter(
            $this->allMetricDefinitions(),
            fn (array $def) => ($def['dimension'] ?? null) === $dimension
        );
    }

    /**
     * All 7½ dimensions with labels.
     *
     * @return array<string, array{label: string, type: string}>
     */
    public static function allDimensions(): array
    {
        return [
            self::DIMENSION_COMPLEXITY  => ['label' => 'Komplexitaet',  'type' => self::TYPE_STOCK],
            self::DIMENSION_ENERGY      => ['label' => 'Energie',       'type' => self::TYPE_FLOW],
            self::DIMENSION_THROUGHPUT  => ['label' => 'Durchsatz',     'type' => self::TYPE_FLOW],
            self::DIMENSION_ORG_CAPITAL => ['label' => 'Org-Kapital',   'type' => self::TYPE_STOCK],
            self::DIMENSION_COSTS       => ['label' => 'Kosten',        'type' => self::TYPE_FLOW],
            self::DIMENSION_REVENUE     => ['label' => 'Umsatz',        'type' => self::TYPE_FLOW],
            self::DIMENSION_POTENTIAL   => ['label' => 'Potenziale',    'type' => self::TYPE_STOCK],
            self::DIMENSION_QUALITY     => ['label' => 'Qualitaet',     'type' => self::TYPE_MODULATOR],
        ];
    }

    /**
     * All available metric groups with labels.
     *
     * @return array<string, string>
     */
    public function allMetricGroups(): array
    {
        $labels = [
            'core' => 'Basis',
            'work' => 'Arbeitspakete',
            'dev' => 'Development',
            'okr' => 'OKR',
            'recruiting' => 'Recruiting',
            'crm' => 'CRM',
            'hcm' => 'HCM',
            'canvas' => 'Canvas',
            'finance' => 'Finanzen',
            'persons' => 'Personen',
            'process' => 'Prozesse',
            'change' => 'Change',
            'correspondence' => 'Korrespondenz',
            'whisper' => 'Gespräche',
        ];

        $groups = [];
        foreach ($this->allMetricDefinitions() as $def) {
            $group = $def['group'];
            $groups[$group] = $labels[$group] ?? ucfirst($group);
        }

        return $groups;
    }

    /**
     * Built-in metric definitions for core snapshot keys.
     */
    protected function builtInMetricDefinitions(): array
    {
        return [
            'links_count' => [
                'label' => 'Verknuepfungen',
                'group' => 'core',
                'direction' => 'neutral',
                'unit' => 'count',
                'dimension' => self::DIMENSION_ORG_CAPITAL,
                'type' => self::TYPE_STOCK,
            ],
            'time_planned_minutes' => [
                'label' => 'Soll-Zeit (geplant)',
                'group' => 'core',
                'direction' => 'neutral',
                'unit' => 'minutes',
                'dimension' => self::DIMENSION_ENERGY,
                'type' => self::TYPE_STOCK,
                'aggregation_mode' => 'rolled_up',
            ],
            'time_total_minutes' => [
                'label' => 'Zeiterfassung (gesamt)',
                'group' => 'core',
                'direction' => 'neutral',
                'unit' => 'minutes',
                'pair' => 'time_planned_minutes',
                'dimension' => self::DIMENSION_ENERGY,
                'type' => self::TYPE_FLOW,
                'aggregation_mode' => 'rolled_up',
            ],
            'time_billed_minutes' => [
                'label' => 'Zeiterfassung (abgerechnet)',
                'group' => 'core',
                'direction' => 'up',
                'unit' => 'minutes',
                'pair' => 'time_total_minutes',
                'dimension' => self::DIMENSION_ENERGY,
                'type' => self::TYPE_FLOW,
                'aggregation_mode' => 'rolled_up',
            ],

            'time_planned_days_remaining' => [
                'label' => 'Resttage bis Planende',
                'group' => 'core',
                'direction' => 'neutral',
                'unit' => 'days',
                'dimension' => self::DIMENSION_ENERGY,
                'type' => self::TYPE_MODULATOR,
            ],

            // Person-Entity Metriken
            'person_active_items' => [
                'label' => 'Aktive Items (Person)',
                'group' => 'persons',
                'direction' => 'neutral',
                'unit' => 'count',
                'dimension' => self::DIMENSION_ENERGY,
                'type' => self::TYPE_STOCK,
            ],
            'person_completed_items' => [
                'label' => 'Erledigte Items (Person)',
                'group' => 'persons',
                'direction' => 'up',
                'unit' => 'count',
                'dimension' => self::DIMENSION_THROUGHPUT,
                'type' => self::TYPE_FLOW,
            ],
            'person_story_points_total' => [
                'label' => 'Story Points gesamt (Person)',
                'group' => 'persons',
                'direction' => 'neutral',
                'unit' => 'points',
                'dimension' => self::DIMENSION_COMPLEXITY,
                'type' => self::TYPE_STOCK,
            ],
            'person_story_points_done' => [
                'label' => 'Story Points erledigt (Person)',
                'group' => 'persons',
                'direction' => 'up',
                'unit' => 'points',
                'pair' => 'person_story_points_total',
                'dimension' => self::DIMENSION_THROUGHPUT,
                'type' => self::TYPE_FLOW,
            ],
            'person_time_total_minutes' => [
                'label' => 'Zeiterfassung (Person)',
                'group' => 'persons',
                'direction' => 'neutral',
                'unit' => 'minutes',
                'dimension' => self::DIMENSION_ENERGY,
                'type' => self::TYPE_FLOW,
            ],

            // Org-Entity Person-Rollup Metriken
            'active_persons_count' => [
                'label' => 'Aktive Personen',
                'group' => 'persons',
                'direction' => 'neutral',
                'unit' => 'count',
                'dimension' => self::DIMENSION_ORG_CAPITAL,
                'type' => self::TYPE_STOCK,
                'aggregation_mode' => 'rolled_up',
            ],
            'persons_workload_total' => [
                'label' => 'Workload (Personen)',
                'group' => 'persons',
                'direction' => 'neutral',
                'unit' => 'count',
                'dimension' => self::DIMENSION_ENERGY,
                'type' => self::TYPE_STOCK,
                'aggregation_mode' => 'rolled_up',
            ],
            'persons_completed_total' => [
                'label' => 'Erledigte Items (Personen)',
                'group' => 'persons',
                'direction' => 'up',
                'unit' => 'count',
                'dimension' => self::DIMENSION_THROUGHPUT,
                'type' => self::TYPE_FLOW,
                'aggregation_mode' => 'rolled_up',
            ],
            'persons_story_points_total' => [
                'label' => 'Story Points gesamt (Personen)',
                'group' => 'persons',
                'direction' => 'neutral',
                'unit' => 'points',
                'dimension' => self::DIMENSION_COMPLEXITY,
                'type' => self::TYPE_STOCK,
                'aggregation_mode' => 'rolled_up',
            ],
            'persons_story_points_done' => [
                'label' => 'Story Points erledigt (Personen)',
                'group' => 'persons',
                'direction' => 'up',
                'unit' => 'points',
                'pair' => 'persons_story_points_total',
                'dimension' => self::DIMENSION_THROUGHPUT,
                'type' => self::TYPE_FLOW,
                'aggregation_mode' => 'rolled_up',
            ],
            'persons_time_total_minutes' => [
                'label' => 'Zeiterfassung (Personen)',
                'group' => 'persons',
                'direction' => 'neutral',
                'unit' => 'minutes',
                'dimension' => self::DIMENSION_ENERGY,
                'type' => self::TYPE_FLOW,
                'aggregation_mode' => 'rolled_up',
            ],

            // Terminal-Kommunikation
            'terminal_messages_7d' => [
                'label' => 'Nachrichten (7 Tage)',
                'group' => 'core',
                'direction' => 'neutral',
                'unit' => 'count',
                'dimension' => self::DIMENSION_ENERGY,
                'type' => self::TYPE_FLOW,
            ],
            'terminal_messages_30d' => [
                'label' => 'Nachrichten (30 Tage)',
                'group' => 'core',
                'direction' => 'neutral',
                'unit' => 'count',
                'dimension' => self::DIMENSION_ENERGY,
                'type' => self::TYPE_FLOW,
            ],
            'terminal_participants_7d' => [
                'label' => 'Aktive Teilnehmer (7 Tage)',
                'group' => 'core',
                'direction' => 'neutral',
                'unit' => 'count',
                'dimension' => self::DIMENSION_ORG_CAPITAL,
                'type' => self::TYPE_STOCK,
            ],
            'terminal_last_message_days' => [
                'label' => 'Tage seit letzter Nachricht',
                'group' => 'core',
                'direction' => 'neutral',
                'unit' => 'days',
                'dimension' => self::DIMENSION_ENERGY,
                'type' => self::TYPE_MODULATOR,
            ],
        ];
    }

    /**
     * Ruft metrics() aller Provider auf und merged Ergebnisse per Entity.
     *
     * @param array<int, array<string, int[]>> $linksByEntityAndType [entityId => [morphAlias => [linkable_ids]]]
     * @return array<int, array> [entityId => merged metrics]
     */
    public function computeMetricsBatch(array $linksByEntityAndType): array
    {
        // Regroup: [morphAlias => [entityId => [linkable_ids]]]
        $byAlias = [];
        foreach ($linksByEntityAndType as $entityId => $typeMap) {
            foreach ($typeMap as $morphAlias => $ids) {
                $byAlias[$morphAlias][$entityId] = $ids;
            }
        }

        $result = [];
        foreach ($byAlias as $morphAlias => $linksByEntity) {
            $provider = $this->getProvider($morphAlias);
            if (!$provider) {
                continue;
            }

            $metrics = $provider->metrics($morphAlias, $linksByEntity);

            foreach ($metrics as $entityId => $entityMetrics) {
                foreach ($entityMetrics as $key => $value) {
                    $result[$entityId][$key] = ($result[$entityId][$key] ?? 0) + $value;
                }
            }
        }

        return $result;
    }

    /**
     * Ruft costDriverAdjustments() aller Provider auf, die HasCostDriverMetrics implementieren.
     *
     * @param array<int, int[]> $groupLinksByEntity [entityId => [groupIds]]
     * @return array<int, array<string, float>> [entityId => ['cashflow_in' => x, ...]]
     */
    public function computeCostDriverAdjustments(array $groupLinksByEntity): array
    {
        $result = [];

        foreach ($this->providers as $provider) {
            if (!$provider instanceof HasCostDriverMetrics) {
                continue;
            }

            $adjustments = $provider->costDriverAdjustments($groupLinksByEntity);

            foreach ($adjustments as $entityId => $metrics) {
                foreach ($metrics as $key => $value) {
                    $result[$entityId][$key] = ($result[$entityId][$key] ?? 0) + $value;
                }
            }
        }

        return $result;
    }
}
