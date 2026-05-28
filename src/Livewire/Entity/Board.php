<?php

namespace Platform\Organization\Livewire\Entity;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Illuminate\Support\Str;
use Platform\Organization\Models\OrganizationDimensionDefinition;
use Platform\Organization\Models\OrganizationDimensionLink;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityRelationship;
use Platform\Organization\Models\OrganizationEntitySnapshot;
use Platform\Organization\Models\OrganizationSignal;
use Platform\Organization\Services\EntityHierarchyResolver;
use Platform\Organization\Services\PerspectiveService;
use Platform\Organization\Services\SnapshotMovementService;

class Board extends Component
{
    public OrganizationEntity $entity;

    public ?string $viewMode = 'system';

    #[Url]
    public ?string $band = null;

    #[Url]
    public ?int $focus = null;

    public function mount(OrganizationEntity $entity)
    {
        $this->entity = $entity->load(['type.group']);

        if ($this->focus) {
            $this->viewMode = 'entity';
        } elseif ($this->band) {
            $this->viewMode = 'band';
        }
    }

    public function showBand(string $code): void
    {
        $this->band = $code;
        $this->focus = null;
        $this->viewMode = 'band';
        unset($this->bandDetail);
    }

    public function showEntity(int $id): void
    {
        $this->focus = $id;
        $this->band = null;
        $this->viewMode = 'entity';
        unset($this->entityDetail);
    }

    public function showSystem(): void
    {
        $this->band = null;
        $this->focus = null;
        $this->viewMode = 'system';
    }

    #[On('perspective-switched')]
    public function onPerspectiveSwitched(): void
    {
        unset($this->boardData);
        $this->dispatch('board-data-updated', data: $this->boardData);
    }

    #[Computed]
    public function boardData(): array
    {
        $user = auth()->user();
        $teamId = $this->entity->team_id;
        $perspective = PerspectiveService::getActive($teamId, $user->id);
        $resolver = resolve(EntityHierarchyResolver::class);

        // 1. Load entities (perspective-aware)
        $query = OrganizationEntity::forTeam($teamId)
            ->active()
            ->with(['type.group']);

        if (!$resolver->isDefaultHierarchy($perspective)) {
            $perspectiveEntityIds = $resolver->entityIdsInPerspective($perspective, $teamId);
            $query->whereIn('id', $perspectiveEntityIds);
        }

        $entities = $query->get();
        $entityIds = $entities->pluck('id')->all();

        // 2. VSM Dimension Links
        $vsmMap = [];
        $vsmDef = OrganizationDimensionDefinition::findByKey('vsm-system');
        if ($vsmDef) {
            $vsmMap = OrganizationDimensionLink::where('dimension_definition_id', $vsmDef->id)
                ->where('linkable_type', 'organization_entity')
                ->whereIn('linkable_id', $entityIds)
                ->with('value')
                ->get()
                ->keyBy('linkable_id')
                ->map(fn ($link) => $link->value)
                ->filter()
                ->all();
        }

        // 3. Latest Snapshots
        $latestSnapshots = OrganizationEntitySnapshot::query()
            ->whereIn('entity_id', $entityIds)
            ->where('snapshot_date', '>=', now()->subDays(3))
            ->orderByDesc('snapshot_date')
            ->orderByDesc('snapshot_period')
            ->get()
            ->unique('entity_id')
            ->keyBy('entity_id');

        // 4. Movement data
        $movementService = resolve(SnapshotMovementService::class);
        $movements = $movementService->forEntitiesBatch($entityIds, 7);

        // 5. Relationships
        $relationships = OrganizationEntityRelationship::query()
            ->where(function ($q) use ($entityIds) {
                $q->whereIn('from_entity_id', $entityIds)
                  ->whereIn('to_entity_id', $entityIds);
            })
            ->with('relationType')
            ->get();

        // 6. Group entities into bands
        $vsmColors = [
            'S1' => '#10b981',
            'S2' => '#06b6d4',
            'S3' => '#3b82f6',
            'S4' => '#8b5cf6',
            'S5' => '#ec4899',
            'ENV' => '#64748b',
        ];

        $vsmLabels = [
            'S1' => 'Operations',
            'S2' => 'Coordination',
            'S3' => 'Control',
            'S4' => 'Intelligence',
            'S5' => 'Policy',
            'ENV' => 'Environment',
        ];

        $bands = [
            'S5' => ['label' => 'S5 · Policy', 'color' => $vsmColors['S5'], 'entities' => []],
            'S4' => ['label' => 'S4 · Intelligence', 'color' => $vsmColors['S4'], 'entities' => []],
            'S3' => ['label' => 'S3 · Control', 'color' => $vsmColors['S3'], 'entities' => []],
            'S2' => ['label' => 'S2 · Coordination', 'color' => $vsmColors['S2'], 'entities' => []],
            'S1' => ['label' => 'S1 · Operations', 'color' => $vsmColors['S1'], 'entities' => []],
        ];
        $envBand = ['label' => 'ENV · Environment', 'color' => $vsmColors['ENV'], 'entities' => []];
        $unassigned = [];

        foreach ($entities as $e) {
            $snap = $latestSnapshots[$e->id] ?? null;
            $metrics = $snap?->metrics ?? [];
            $movement = $movements[$e->id] ?? ['score' => 0, 'delta_count' => 0, 'positive' => 0, 'negative' => 0, 'top_delta' => null];

            $entityData = [
                'id' => $e->id,
                'name' => $e->name,
                'type' => $e->type?->name ?? 'Sonstige',
                'metrics' => [
                    'items_total' => $metrics['items_total'] ?? 0,
                    'items_done' => $metrics['items_done'] ?? 0,
                    'time_h' => round(($metrics['time_total_minutes'] ?? 0) / 60, 1),
                    'okr_perf' => ($metrics['okr_performance_count'] ?? 0) > 0
                        ? round(($metrics['okr_performance_sum'] / $metrics['okr_performance_count']) * 100)
                        : null,
                ],
                'movement' => $movement,
            ];

            $vsmValue = $vsmMap[$e->id] ?? null;
            if ($vsmValue && isset($bands[$vsmValue->code])) {
                $bands[$vsmValue->code]['entities'][] = $entityData;
            } elseif ($vsmValue && $vsmValue->code === 'ENV') {
                $envBand['entities'][] = $entityData;
            } else {
                $unassigned[] = $entityData;
            }
        }

        // 7. Balance + Diagnosis
        $balance = [];
        foreach ($bands as $code => $band) {
            $balance[$code] = count($band['entities']);
        }
        $balance['ENV'] = count($envBand['entities']);

        $systemCodes = ['S1', 'S2', 'S3', 'S4', 'S5'];
        $emptyCodes = array_filter($systemCodes, fn ($c) => $balance[$c] === 0);
        $diagnosis = '';
        if (count($emptyCodes) >= 3) {
            $diagnosis = count($emptyCodes) . ' leere Ebenen — fragil';
        } elseif ($balance['S3'] > 0 && $balance['S4'] === 0) {
            $diagnosis = 'S3 ohne S4 — operativ, aber ohne Zukunftsradar';
        } elseif ($balance['S4'] > 0 && $balance['S3'] === 0) {
            $diagnosis = 'S4 ohne S3 — Vision ohne Steuerung';
        } elseif ($balance['S3'] > 0 && $balance['S4'] > 0 && abs($balance['S3'] - $balance['S4']) <= 1) {
            $diagnosis = 'S3/S4 im Gleichgewicht';
        } elseif (count($emptyCodes) > 0) {
            $diagnosis = 'leere Ebenen: ' . implode(', ', $emptyCodes);
        } else {
            $diagnosis = 'alle Ebenen besetzt';
        }

        // 8. Relationship flows
        $flowCategories = [
            'provides_service_to' => 'service', 'receives_service_from' => 'service',
            'supports' => 'service', 'is_supported_by' => 'service',
            'informs' => 'info', 'is_informed_by' => 'info', 'communicates_with' => 'info',
            'supplies_to' => 'supply', 'purchases_from' => 'supply',
            'reports_to' => 'hierarchy', 'manages' => 'hierarchy',
            'is_part_of' => 'hierarchy', 'contains' => 'hierarchy',
            'collaborates_with' => 'collaboration', 'partners_with' => 'collaboration',
            'relates_to' => 'collaboration',
        ];

        $flowColors = [
            'service' => '#f97316',
            'info' => '#22d3ee',
            'supply' => '#10b981',
            'hierarchy' => '#6b7280',
            'collaboration' => '#a855f7',
        ];

        $relationshipFlows = [];
        foreach ($relationships as $rel) {
            $code = $rel->relationType?->code ?? '';
            $category = $flowCategories[$code] ?? 'collaboration';
            $relationshipFlows[] = [
                'from' => $rel->from_entity_id,
                'to' => $rel->to_entity_id,
                'label' => $rel->relationType?->name ?? $code,
                'category' => $category,
                'color' => $flowColors[$category],
            ];
        }

        // 9. System Load (per band, from snapshot metrics)
        $systemLoad = [];
        foreach ($systemCodes as $code) {
            $bandEntities = $bands[$code]['entities'];
            $totalItems = 0;
            $doneItems = 0;
            $totalTimeH = 0;
            $entityCount = count($bandEntities);
            foreach ($bandEntities as $ent) {
                $totalItems += $ent['metrics']['items_total'];
                $doneItems += $ent['metrics']['items_done'];
                $totalTimeH += $ent['metrics']['time_h'];
            }
            $openItems = $totalItems - $doneItems;
            $loadPerEntity = $entityCount > 0 ? round($openItems / $entityCount, 1) : 0;
            $congested = $loadPerEntity > 5;
            $systemLoad[$code] = [
                'items_total' => $totalItems,
                'items_done' => $doneItems,
                'open_items' => $openItems,
                'time_h' => $totalTimeH,
                'load_per_entity' => $loadPerEntity,
                'congested' => $congested,
                'entity_count' => $entityCount,
            ];
        }

        // 10. Regulation Loop Health (per band, from relationships + movements)
        $regulationHealth = [];
        // Build entity-to-band map
        $entityBandMap = [];
        foreach ($bands as $code => $band) {
            foreach ($band['entities'] as $ent) {
                $entityBandMap[$ent['id']] = $code;
            }
        }
        foreach ($envBand['entities'] as $ent) {
            $entityBandMap[$ent['id']] = 'ENV';
        }

        foreach ($systemCodes as $code) {
            $inbound = 0;
            $outbound = 0;
            foreach ($relationships as $rel) {
                $fromBand = $entityBandMap[$rel->from_entity_id] ?? null;
                $toBand = $entityBandMap[$rel->to_entity_id] ?? null;
                if ($toBand === $code) $inbound++;
                if ($fromBand === $code) $outbound++;
            }

            // Aggregate movement scores for entities in this band
            $bandMovementScores = [];
            foreach ($bands[$code]['entities'] as $ent) {
                $mv = $movements[$ent['id']] ?? null;
                if ($mv && $mv['delta_count'] > 0) {
                    $bandMovementScores[] = $mv['score'];
                }
            }
            $avgMovement = count($bandMovementScores) > 0
                ? round(array_sum($bandMovementScores) / count($bandMovementScores), 1)
                : 0;

            // Classify
            $totalConnections = $inbound + $outbound;
            if ($totalConnections === 0) {
                $status = 'disconnected';
            } elseif ($avgMovement < -3 || ($inbound > 0 && $outbound === 0)) {
                $status = 'stressed';
            } else {
                $status = 'healthy';
            }

            $regulationHealth[$code] = [
                'inbound' => $inbound,
                'outbound' => $outbound,
                'avg_movement' => $avgMovement,
                'status' => $status,
            ];
        }

        // 11. Autonomy Index (S1 entities only)
        $autonomyIndex = [];
        foreach ($bands['S1']['entities'] as $ent) {
            $selfRegulated = 0;
            $s3Regulated = 0;
            foreach ($relationships as $rel) {
                if ($rel->to_entity_id == $ent['id']) {
                    $fromBand = $entityBandMap[$rel->from_entity_id] ?? null;
                    if ($fromBand === 'S3') {
                        $s3Regulated++;
                    } elseif ($fromBand === 'S1' || $fromBand === 'S2') {
                        $selfRegulated++;
                    }
                }
                if ($rel->from_entity_id == $ent['id']) {
                    $toBand = $entityBandMap[$rel->to_entity_id] ?? null;
                    if ($toBand === 'S1' || $toBand === 'S2') {
                        $selfRegulated++;
                    }
                }
            }
            $total = $selfRegulated + $s3Regulated;
            $autonomyPct = $total > 0 ? round(($selfRegulated / $total) * 100) : 50;
            $autonomyIndex[$ent['id']] = [
                'name' => $ent['name'],
                'autonomy_pct' => $autonomyPct,
                'self_regulated' => $selfRegulated,
                's3_regulated' => $s3Regulated,
            ];
        }

        // 12. Stability Indicator (entities with movement)
        $stabilityIndicator = [];
        foreach ($entities as $e) {
            $mv = $movements[$e->id] ?? null;
            if (!$mv || $mv['delta_count'] === 0) continue;
            $pos = $mv['positive'] ?? 0;
            $neg = $mv['negative'] ?? 0;
            $maxVal = max($pos, $neg);
            $minVal = min($pos, $neg);
            $oscillation = $maxVal > 0 ? round($minVal / $maxVal, 2) : 0;
            if ($oscillation >= 0.7) {
                $status = 'oscillating';
            } elseif ($oscillation >= 0.3) {
                $status = 'mixed';
            } else {
                $status = 'stable';
            }
            $stabilityIndicator[$e->id] = [
                'name' => $e->name,
                'oscillation' => $oscillation,
                'status' => $status,
                'positive' => $pos,
                'negative' => $neg,
            ];
        }

        // 13. Variety Metrics (real: required based on regulation relationships targeting band)
        $varietyMetrics = [];
        foreach ($systemCodes as $code) {
            $entityCount = count($bands[$code]['entities']);
            // Required = number of regulation relationships targeting this band (inbound connections imply variety demand)
            $inboundCount = $regulationHealth[$code]['inbound'] ?? 0;
            $required = max(2, $inboundCount + 1);
            $available = $entityCount;
            $gap = $available >= $required ? 'balanced' : ($required - $available <= 1 ? 'marginal' : 'deficit');
            $varietyMetrics[$code] = [
                'required' => $required,
                'available' => $available,
                'gap' => $gap,
            ];
        }

        // 14. Algedonic Alerts (from persisted signals)
        $algedonicAlerts = [];
        $openSignals = OrganizationSignal::forTeam($teamId)
            ->open()
            ->whereIn('entity_id', $entityIds)
            ->with('entity')
            ->orderByDesc('severity')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        foreach ($openSignals as $signal) {
            $signalEntityId = $signal->entity_id;
            $fromBand = $entityBandMap[$signalEntityId] ?? 'S1';
            $algedonicAlerts[] = [
                'from' => $fromBand,
                'to' => 'S5',
                'message' => $signal->message,
                'severity' => $signal->severity,
                'timestamp' => $signal->created_at->format('H:i'),
            ];
        }

        // 15. Recursive entities (entities with children)
        $recursiveEntityIds = OrganizationEntityRelationship::query()
            ->whereIn('from_entity_id', $entityIds)
            ->whereHas('relationType', fn ($q) => $q->whereIn('code', ['contains', 'manages']))
            ->pluck('from_entity_id')
            ->unique()
            ->all();

        // Mark entities as recursive
        foreach (['bands', 'envBand', 'unassigned'] as $group) {
            if ($group === 'bands') {
                foreach ($bands as $code => &$band) {
                    foreach ($band['entities'] as &$entityItem) {
                        $entityItem['is_recursive'] = in_array($entityItem['id'], $recursiveEntityIds);
                    }
                    unset($entityItem);
                }
                unset($band);
            } elseif ($group === 'envBand') {
                foreach ($envBand['entities'] as &$entityItem) {
                    $entityItem['is_recursive'] = in_array($entityItem['id'], $recursiveEntityIds);
                }
                unset($entityItem);
            } else {
                foreach ($unassigned as &$entityItem) {
                    $entityItem['is_recursive'] = in_array($entityItem['id'], $recursiveEntityIds);
                }
                unset($entityItem);
            }
        }

        // 16. Ticker (from persisted signals, last 24h)
        $ticker = [];
        $recentSignals = OrganizationSignal::forTeam($teamId)
            ->recent(24)
            ->whereIn('entity_id', $entityIds)
            ->with(['entity', 'definition'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $tickerIcons = [
            'critical' => 'exclamation-triangle',
            'warning' => 'shield-exclamation',
            'info' => 'information-circle',
        ];
        $tickerTypes = [
            'threshold' => 'alert',
            'trend' => 'regulation',
            'cross_dimension' => 'emergence',
            'ratio' => 'intervention',
        ];

        foreach ($recentSignals as $sig) {
            $patternType = $sig->definition?->pattern_type ?? 'threshold';
            $sigBand = $entityBandMap[$sig->entity_id] ?? 'S1';
            $ticker[] = [
                'type' => $tickerTypes[$patternType] ?? 'alert',
                'level' => $sigBand,
                'icon' => $tickerIcons[$sig->severity] ?? 'information-circle',
                'message' => Str::limit($sig->message, 60),
                'detail' => $sig->message,
                'timestamp' => $sig->created_at->format('H:i'),
            ];
        }

        // 17. Regulation Loops
        $regulationLoops = [
            ['from' => 'S5', 'to' => 'S3', 'label' => 'Policy → Control', 'color' => $vsmColors['S5']],
            ['from' => 'S4', 'to' => 'S3', 'label' => 'Intelligence → Control', 'color' => $vsmColors['S4']],
            ['from' => 'S3', 'to' => 'S1', 'label' => 'Control → Operations', 'color' => $vsmColors['S3']],
            ['from' => 'S2', 'to' => 'S1', 'label' => 'Coordination → Operations', 'color' => $vsmColors['S2']],
            ['from' => 'ENV', 'to' => 'S4', 'label' => 'Environment → Intelligence', 'color' => $vsmColors['ENV']],
            ['from' => 'S1', 'to' => 'ENV', 'label' => 'Operations → Environment', 'color' => $vsmColors['S1']],
        ];

        return [
            'bands' => $bands,
            'envBand' => $envBand,
            'relationships' => $relationshipFlows,
            'unassigned' => $unassigned,
            'balance' => $balance,
            'diagnosis' => $diagnosis,
            'ticker' => $ticker,
            'regulationLoops' => $regulationLoops,
            'vsmColors' => $vsmColors,
            'flowColors' => $flowColors,
            'varietyMetrics' => $varietyMetrics,
            'algedonicAlerts' => $algedonicAlerts,
            'recursiveEntityIds' => $recursiveEntityIds,
            'systemLoad' => $systemLoad,
            'regulationHealth' => $regulationHealth,
            'autonomyIndex' => $autonomyIndex,
            'stabilityIndicator' => $stabilityIndicator,
        ];
    }

    #[Computed]
    public function bandDetail(): ?array
    {
        if ($this->viewMode !== 'band' || !$this->band) {
            return null;
        }

        $data = $this->boardData;
        $code = $this->band;

        // Find band entities
        $bandEntities = [];
        $bandColor = '#64748b';
        $bandLabel = $code;

        if ($code === 'ENV') {
            $bandEntities = $data['envBand']['entities'];
            $bandColor = $data['vsmColors']['ENV'];
            $bandLabel = $data['envBand']['label'];
        } elseif (isset($data['bands'][$code])) {
            $bandEntities = $data['bands'][$code]['entities'];
            $bandColor = $data['bands'][$code]['color'];
            $bandLabel = $data['bands'][$code]['label'];
        }

        $bandEntityIds = array_column($bandEntities, 'id');

        // Internal relationships (both endpoints in this band)
        $internalRelationships = [];
        $crossBandRelationships = [];
        foreach ($data['relationships'] as $rel) {
            $fromIn = in_array($rel['from'], $bandEntityIds);
            $toIn = in_array($rel['to'], $bandEntityIds);
            if ($fromIn && $toIn) {
                $internalRelationships[] = $rel;
            } elseif ($fromIn || $toIn) {
                $crossBandRelationships[] = $rel;
            }
        }

        // Band-specific metrics
        $load = $data['systemLoad'][$code] ?? null;
        $regulation = $data['regulationHealth'][$code] ?? null;
        $variety = $data['varietyMetrics'][$code] ?? null;

        // Stability for entities in this band
        $bandStability = [];
        foreach ($bandEntityIds as $eid) {
            if (isset($data['stabilityIndicator'][$eid])) {
                $bandStability[$eid] = $data['stabilityIndicator'][$eid];
            }
        }

        // Autonomy (only for S1)
        $bandAutonomy = [];
        if ($code === 'S1') {
            foreach ($bandEntityIds as $eid) {
                if (isset($data['autonomyIndex'][$eid])) {
                    $bandAutonomy[$eid] = $data['autonomyIndex'][$eid];
                }
            }
        }

        return [
            'code' => $code,
            'label' => $bandLabel,
            'color' => $bandColor,
            'entities' => $bandEntities,
            'internalRelationships' => $internalRelationships,
            'crossBandRelationships' => $crossBandRelationships,
            'load' => $load,
            'regulation' => $regulation,
            'variety' => $variety,
            'stability' => $bandStability,
            'autonomy' => $bandAutonomy,
        ];
    }

    #[Computed]
    public function entityDetail(): ?array
    {
        if ($this->viewMode !== 'entity' || !$this->focus) {
            return null;
        }

        $data = $this->boardData;
        $entityId = $this->focus;

        // Find entity in boardData
        $entityData = null;
        $bandCode = null;
        $bandColor = '#374151';
        foreach ($data['bands'] as $code => $band) {
            foreach ($band['entities'] as $ent) {
                if ($ent['id'] === $entityId) {
                    $entityData = $ent;
                    $bandCode = $code;
                    $bandColor = $band['color'];
                    break 2;
                }
            }
        }
        if (!$entityData) {
            foreach ($data['envBand']['entities'] as $ent) {
                if ($ent['id'] === $entityId) {
                    $entityData = $ent;
                    $bandCode = 'ENV';
                    $bandColor = $data['vsmColors']['ENV'];
                    break;
                }
            }
        }
        if (!$entityData) {
            foreach ($data['unassigned'] as $ent) {
                if ($ent['id'] === $entityId) {
                    $entityData = $ent;
                    $bandCode = null;
                    break;
                }
            }
        }

        if (!$entityData) {
            return null;
        }

        // Full movement details
        $movementService = resolve(SnapshotMovementService::class);
        $movementResult = $movementService->forEntity($entityId, 7);
        $movementArray = $movementResult->toArray();

        // 14-day time series
        $timeSeries = $movementService->timeSeries($entityId, 14);

        // Relationships in/out
        $incomingRelationships = [];
        $outgoingRelationships = [];
        foreach ($data['relationships'] as $rel) {
            if ($rel['to'] === $entityId) {
                // Find source entity name
                $rel['entity_name'] = $this->findEntityName($data, $rel['from']);
                $incomingRelationships[] = $rel;
            }
            if ($rel['from'] === $entityId) {
                $rel['entity_name'] = $this->findEntityName($data, $rel['to']);
                $outgoingRelationships[] = $rel;
            }
        }

        // Stability & Autonomy
        $stability = $data['stabilityIndicator'][$entityId] ?? null;
        $autonomy = ($bandCode === 'S1' && isset($data['autonomyIndex'][$entityId]))
            ? $data['autonomyIndex'][$entityId]
            : null;

        // Children (recursive entities)
        $children = [];
        $isRecursive = !empty($entityData['is_recursive']);
        if ($isRecursive) {
            $childEntities = OrganizationEntity::where('parent_entity_id', $entityId)
                ->active()
                ->with(['type.group'])
                ->get();

            if ($childEntities->isNotEmpty()) {
                $childIds = $childEntities->pluck('id')->all();
                $childMovements = $movementService->forEntitiesBatch($childIds, 7);

                $childSnapshots = OrganizationEntitySnapshot::query()
                    ->whereIn('entity_id', $childIds)
                    ->where('snapshot_date', '>=', now()->subDays(3))
                    ->orderByDesc('snapshot_date')
                    ->orderByDesc('snapshot_period')
                    ->get()
                    ->unique('entity_id')
                    ->keyBy('entity_id');

                foreach ($childEntities as $child) {
                    $snap = $childSnapshots[$child->id] ?? null;
                    $metrics = $snap?->metrics ?? [];
                    $mv = $childMovements[$child->id] ?? ['score' => 0, 'delta_count' => 0, 'positive' => 0, 'negative' => 0, 'top_delta' => null];
                    $children[] = [
                        'id' => $child->id,
                        'name' => $child->name,
                        'type' => $child->type?->name ?? 'Sonstige',
                        'metrics' => [
                            'items_total' => $metrics['items_total'] ?? 0,
                            'items_done' => $metrics['items_done'] ?? 0,
                            'time_h' => round(($metrics['time_total_minutes'] ?? 0) / 60, 1),
                            'okr_perf' => ($metrics['okr_performance_count'] ?? 0) > 0
                                ? round(($metrics['okr_performance_sum'] / $metrics['okr_performance_count']) * 100)
                                : null,
                        ],
                        'movement' => $mv,
                        'is_recursive' => false,
                    ];
                }
            }
        }

        // Determine type group
        $entity = OrganizationEntity::with('type.group')->find($entityId);
        $typeGroup = $entity?->type?->group?->code ?? null;

        return [
            'entity' => $entityData,
            'bandCode' => $bandCode,
            'bandColor' => $bandColor,
            'movement' => $movementArray,
            'timeSeries' => $timeSeries,
            'incomingRelationships' => $incomingRelationships,
            'outgoingRelationships' => $outgoingRelationships,
            'stability' => $stability,
            'autonomy' => $autonomy,
            'children' => $children,
            'isRecursive' => $isRecursive,
            'typeGroup' => $typeGroup,
        ];
    }

    protected function findEntityName(array $data, int $entityId): string
    {
        foreach ($data['bands'] as $band) {
            foreach ($band['entities'] as $ent) {
                if ($ent['id'] === $entityId) return $ent['name'];
            }
        }
        foreach ($data['envBand']['entities'] as $ent) {
            if ($ent['id'] === $entityId) return $ent['name'];
        }
        foreach ($data['unassigned'] as $ent) {
            if ($ent['id'] === $entityId) return $ent['name'];
        }
        return 'Unknown';
    }

    public function render()
    {
        return view('organization::livewire.entity.board')
            ->layout('platform::layouts.app');
    }
}
