<?php

namespace Platform\Organization\Livewire\Entity;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Platform\Organization\Models\OrganizationDimensionDefinition;
use Platform\Organization\Models\OrganizationDimensionLink;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityRelationship;
use Platform\Organization\Models\OrganizationEntitySnapshot;
use Platform\Organization\Services\EntityHierarchyResolver;
use Platform\Organization\Services\PerspectiveService;
use Platform\Organization\Services\SnapshotMovementService;

class Board extends Component
{
    public OrganizationEntity $entity;

    public function mount(OrganizationEntity $entity)
    {
        $this->entity = $entity->load(['type.group']);
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

        // 9. Variety Metrics (Dummy per band)
        $varietyMetrics = [];
        foreach ($systemCodes as $code) {
            $entityCount = count($bands[$code]['entities']);
            $required = max(2, $entityCount + rand(0, 3));
            $available = $entityCount;
            $gap = $available >= $required ? 'balanced' : ($required - $available <= 1 ? 'marginal' : 'deficit');
            $varietyMetrics[$code] = [
                'required' => $required,
                'available' => $available,
                'gap' => $gap,
            ];
        }

        // 10. Algedonic Alerts (Dummy)
        $algedonicAlerts = [
            [
                'from' => 'S1',
                'to' => 'S5',
                'message' => 'Kritische Überlast in Operations — Eskalation an Policy',
                'severity' => 'critical',
                'timestamp' => now()->subMinutes(12)->format('H:i'),
            ],
        ];

        // 11. Recursive entities (entities with children)
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

        // 12. Dummy Ticker
        $ticker = $this->generateDummyTicker();

        // 13. Regulation Loops
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
        ];
    }

    protected function generateDummyTicker(): array
    {
        $events = [
            ['type' => 'regulation', 'level' => 'S3', 'icon' => 'adjustments-vertical', 'message' => 'Kapazitätsausgleich S1 ausgelöst', 'detail' => 'Ressourcen-Reallokation zwischen Operations-Einheiten'],
            ['type' => 'intervention', 'level' => 'S5', 'icon' => 'shield-exclamation', 'message' => 'Policy-Review initiiert', 'detail' => 'Quartals-Überprüfung der Steuerungsmechanismen'],
            ['type' => 'emergence', 'level' => 'S1', 'icon' => 'sparkles', 'message' => 'Neue Kooperation emergiert', 'detail' => 'Selbstorganisierte Zusammenarbeit zwischen zwei S1-Einheiten'],
            ['type' => 'alert', 'level' => 'S4', 'icon' => 'exclamation-triangle', 'message' => 'Umwelt-Signal: Marktveränderung', 'detail' => 'Intelligence erkennt signifikante Veränderung im Wettbewerbsumfeld'],
            ['type' => 'regulation', 'level' => 'S2', 'icon' => 'arrows-right-left', 'message' => 'Anti-oszillatorische Dämpfung', 'detail' => 'Koordination stabilisiert schwankende Auslastung'],
            ['type' => 'intervention', 'level' => 'S3', 'icon' => 'cog-6-tooth', 'message' => 'Audit-Zyklus gestartet', 'detail' => 'Kontrollsystem überprüft Performance-Metriken'],
            ['type' => 'emergence', 'level' => 'S2', 'icon' => 'light-bulb', 'message' => 'Synergieeffekt erkannt', 'detail' => 'Koordination identifiziert Effizienzpotenzial'],
            ['type' => 'alert', 'level' => 'ENV', 'icon' => 'globe-alt', 'message' => 'Externe Disruption möglich', 'detail' => 'Umwelt-Monitoring detektiert regulatorische Veränderung'],
            ['type' => 'regulation', 'level' => 'S4', 'icon' => 'eye', 'message' => 'Zukunftsradar-Update', 'detail' => 'Intelligence aktualisiert strategische Prognosen'],
            ['type' => 'intervention', 'level' => 'S5', 'icon' => 'flag', 'message' => 'Identitäts-Alignment', 'detail' => 'Policy stellt Kohärenz zwischen Strategie und Werten sicher'],
        ];

        $result = [];
        $now = now();
        foreach ($events as $i => $event) {
            $event['timestamp'] = $now->copy()->subMinutes(rand(5, 120))->format('H:i');
            $result[] = $event;
        }

        usort($result, fn ($a, $b) => strcmp($b['timestamp'], $a['timestamp']));

        return $result;
    }

    public function render()
    {
        return view('organization::livewire.entity.board')
            ->layout('platform::layouts.app');
    }
}
