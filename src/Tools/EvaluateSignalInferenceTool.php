<?php

namespace Platform\Organization\Tools;

use Illuminate\Database\Eloquent\Relations\Relation;
use Platform\ActivityLog\Models\ActivityLogActivity;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationMemoryEntry;
use Platform\Organization\Models\OrganizationPerspective;
use Platform\Organization\Models\OrganizationSignal;
use Platform\Organization\Models\OrganizationSignalInferencePrompt;
use Platform\Organization\Models\OrganizationVsmFunction;
use Platform\Organization\Services\EntityDimensionBridge;
use Platform\Organization\Services\EntityHierarchyResolver;
use Platform\Organization\Services\SnapshotMovementService;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class EvaluateSignalInferenceTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.signal_inference.evaluate';
    }

    public function getDescription(): string
    {
        return 'POST /organization/signal_inference/evaluate - Lädt den vollständigen Kontext für Inference-Prompts (Entities, Snapshots, Movement, Kommunikation, Recordings). Claude reasoned dann selbst über die diagnostische Frage und nutzt organization.signal_inference.create_signal um Signale zu erzeugen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: Team aus Kontext.',
                ],
                'inference_prompt_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID eines einzelnen Inference-Prompts zum Evaluieren.',
                ],
                'vsm_system' => [
                    'type' => 'string',
                    'description' => 'Optional: Alle aktiven Prompts eines VSM-Systems evaluieren (s1, s2, s3, s3_star, s4, s5).',
                ],
                'perspective_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Spezifische Perspektive für Entity-Hierarchie. Default: Team-Default-Perspektive.',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            // Resolve prompts to evaluate
            $prompts = $this->resolvePrompts($arguments, $rootTeamId);
            if ($prompts->isEmpty()) {
                return ToolResult::success([
                    'evaluations' => [],
                    'message' => 'Keine Inference-Prompts zum Evaluieren gefunden.',
                ]);
            }

            // Resolve perspective
            $perspective = $this->resolvePerspective($arguments, $rootTeamId, $context->user?->id);

            $hierarchyResolver = resolve(EntityHierarchyResolver::class);
            $movementService = resolve(SnapshotMovementService::class);

            $evaluations = [];

            foreach ($prompts as $prompt) {
                // 1. Scope resolution: get entity IDs
                $entityIds = $this->resolveScope($prompt, $rootTeamId);
                if (empty($entityIds)) {
                    continue;
                }

                // 2. Load entities with type and parent
                $entities = OrganizationEntity::whereIn('id', $entityIds)
                    ->with(['type:id,name,code', 'parent:id,name'])
                    ->where('is_active', true)
                    ->get();

                if ($entities->isEmpty()) {
                    continue;
                }

                $entityIdList = $entities->pluck('id')->all();

                // 3. Load VSM functions per entity (batch)
                $vsmFunctionsByEntity = [];
                foreach ($entityIdList as $eid) {
                    $vsmFunctions = OrganizationVsmFunction::getForEntityWithHierarchy($rootTeamId, $eid);
                    $vsmFunctionsByEntity[$eid] = $vsmFunctions->pluck('code')->all();
                }

                // 4. Load latest snapshots (batch)
                $latestSnapshots = $this->getLatestSnapshots($entityIdList);

                // 5. Movement data (batch)
                $movementBatch = $movementService->forEntitiesBatch($entityIdList, 7);

                // 6. Build entity data
                $entityData = [];
                foreach ($entities as $entity) {
                    $entry = [
                        'id' => $entity->id,
                        'name' => $entity->name,
                        'type' => $entity->type?->code,
                        'vsm_functions' => $vsmFunctionsByEntity[$entity->id] ?? [],
                        'parent' => $entity->parent?->name,
                        'snapshot_latest' => $latestSnapshots[$entity->id] ?? null,
                        'movement_7d' => $movementBatch[$entity->id] ?? null,
                    ];
                    $entityData[] = $entry;
                }

                // 7. Communication summary (if data_sources includes correspondence/recordings)
                $communicationSummary = $this->buildCommunicationSummary(
                    $prompt->data_sources ?? [],
                    $entityIdList,
                    $rootTeamId
                );

                // 8. Activity log (if data_sources includes activity_log)
                if (in_array('activity_log', $prompt->data_sources ?? [])) {
                    $communicationSummary['recent_activities'] = $this->getRecentActivities($entityIdList);
                }

                // 8b. Zukunftsbild (if data_sources includes zukunftsbild)
                if (in_array('zukunftsbild', $prompt->data_sources ?? [])) {
                    $zukunftsbild = $this->getZukunftsbildContext($rootTeamId);
                    if (!empty($zukunftsbild)) {
                        $communicationSummary['zukunftsbild'] = $zukunftsbild;
                    }
                }

                // 9. Existing open signals for these entities (dedup)
                $existingSignals = OrganizationSignal::whereIn('entity_id', $entityIdList)
                    ->where('team_id', $rootTeamId)
                    ->open()
                    ->select(['id', 'entity_id', 'severity', 'message', 'status', 'source', 'inference_prompt_id'])
                    ->latest()
                    ->limit(50)
                    ->get()
                    ->map(fn ($s) => [
                        'id' => $s->id,
                        'entity_id' => $s->entity_id,
                        'severity' => $s->severity,
                        'message' => $s->message,
                        'status' => $s->status,
                        'source' => $s->source ?? 'rule',
                    ])
                    ->toArray();

                // 10. Load Memory context (Layer 3)
                $memoryContext = $this->loadMemoryContext($rootTeamId, $entityIdList, $prompt->id);

                // 11. Update last_evaluated_at
                $prompt->update(['last_evaluated_at' => now()]);

                $evaluations[] = [
                    'prompt' => [
                        'id' => $prompt->id,
                        'name' => $prompt->name,
                        'vsm_system' => $prompt->vsm_system,
                        'prompt_template' => $prompt->prompt_template,
                        'dimension' => $prompt->dimension,
                        'default_severity' => $prompt->default_severity,
                    ],
                    'perspective' => [
                        'id' => $perspective->id,
                        'name' => $perspective->name,
                    ],
                    'entities' => $entityData,
                    'communication_summary' => $communicationSummary,
                    'existing_signals' => $existingSignals,
                    'memory_context' => $memoryContext,
                ];
            }

            return ToolResult::success([
                'evaluations' => $evaluations,
                'count' => count($evaluations),
                'message' => count($evaluations) . ' Inference-Prompt(s) evaluiert. Analysiere die Daten anhand der prompt_template-Frage und nutze organization.signal_inference.create_signal für erkannte Probleme.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Evaluieren: ' . $e->getMessage());
        }
    }

    protected function resolvePrompts(array $arguments, int $rootTeamId)
    {
        $query = OrganizationSignalInferencePrompt::query()
            ->where('team_id', $rootTeamId)
            ->active();

        if (! empty($arguments['inference_prompt_id'])) {
            // Single prompt by ID (bypass active filter)
            return OrganizationSignalInferencePrompt::where('team_id', $rootTeamId)
                ->where('id', (int) $arguments['inference_prompt_id'])
                ->get();
        }

        if (! empty($arguments['vsm_system'])) {
            $query->forVsmSystem($arguments['vsm_system']);
        } else {
            // No specific filter: evaluate all due prompts
            $query->due();
        }

        return $query->orderBy('created_at')->limit(10)->get();
    }

    protected function resolvePerspective(array $arguments, int $rootTeamId, ?int $userId): OrganizationPerspective
    {
        if (! empty($arguments['perspective_id'])) {
            $perspective = OrganizationPerspective::where('team_id', $rootTeamId)
                ->where('id', (int) $arguments['perspective_id'])
                ->first();
            if ($perspective) {
                return $perspective;
            }
        }

        return OrganizationPerspective::getOrCreateDefault($rootTeamId, $userId);
    }

    protected function resolveScope(OrganizationSignalInferencePrompt $prompt, int $teamId): array
    {
        return match ($prompt->scope_type) {
            'all' => OrganizationEntity::forTeam($teamId)->active()->pluck('id')->all(),

            'entity_type' => OrganizationEntity::forTeam($teamId)
                ->active()
                ->whereHas('type', function ($q) use ($prompt) {
                    $codes = $prompt->scope_value ?? [];
                    $q->whereIn('code', (array) $codes);
                })
                ->pluck('id')
                ->all(),

            'subtree' => $this->resolveSubtree($teamId, $prompt->scope_value),

            default => OrganizationEntity::forTeam($teamId)->active()->pluck('id')->all(),
        };
    }

    protected function resolveSubtree(int $teamId, ?array $scopeValue): array
    {
        $rootId = $scopeValue[0] ?? null;
        if (! $rootId) {
            return [];
        }

        $ids = [$rootId];
        $queue = [$rootId];

        while (! empty($queue)) {
            $childIds = OrganizationEntity::forTeam($teamId)
                ->active()
                ->whereIn('parent_entity_id', $queue)
                ->pluck('id')
                ->all();

            $ids = array_merge($ids, $childIds);
            $queue = $childIds;
        }

        return array_unique($ids);
    }

    protected function getLatestSnapshots(array $entityIds): array
    {
        if (empty($entityIds)) {
            return [];
        }

        $snapshots = \Platform\Organization\Models\OrganizationEntitySnapshot::whereIn('entity_id', $entityIds)
            ->whereIn('id', function ($q) use ($entityIds) {
                $q->selectRaw('MAX(id)')
                    ->from('organization_entity_snapshots')
                    ->whereIn('entity_id', $entityIds)
                    ->groupBy('entity_id');
            })
            ->get();

        $result = [];
        foreach ($snapshots as $snap) {
            $result[$snap->entity_id] = $snap->metrics;
        }

        return $result;
    }

    protected function buildCommunicationSummary(array $dataSources, array $entityIds, int $rootTeamId): array
    {
        $summary = [];

        // Correspondence
        if (in_array('correspondence', $dataSources)) {
            $summary = array_merge($summary, $this->getCorrespondenceSummary($entityIds, $rootTeamId));
        }

        // Recordings (Whisper)
        if (in_array('recordings', $dataSources)) {
            $summary = array_merge($summary, $this->getRecordingsSummary($entityIds, $rootTeamId));
        }

        return $summary;
    }

    protected function getCorrespondenceSummary(array $entityIds, int $rootTeamId): array
    {
        try {
            // Find correspondence threads linked to these entities via dimension links
            $links = EntityDimensionBridge::linksForEntities($entityIds);
            $threadIds = $links
                ->where('linkable_type', 'correspondence_thread')
                ->pluck('linkable_id')
                ->unique()
                ->all();

            if (empty($threadIds)) {
                return ['correspondence_threads' => 0, 'recent_items' => []];
            }

            $threadClass = Relation::getMorphedModel('correspondence_thread');
            if (! $threadClass || ! class_exists($threadClass)) {
                return ['correspondence_threads' => 0, 'recent_items' => []];
            }

            $threads = $threadClass::whereIn('id', $threadIds)
                ->where('latest_item_at', '>=', now()->subDays(30))
                ->get();

            $recentItems = [];
            $itemClass = Relation::getMorphedModel('correspondence_item');
            if ($itemClass && class_exists($itemClass)) {
                $recentItems = $itemClass::whereIn('thread_id', $threads->pluck('id'))
                    ->where('correspondence_date', '>=', now()->subDays(30))
                    ->select(['id', 'thread_id', 'direction', 'correspondence_date'])
                    ->latest('correspondence_date')
                    ->limit(10)
                    ->get()
                    ->map(fn ($item) => [
                        'direction' => $item->direction,
                        'date' => $item->correspondence_date?->format('Y-m-d'),
                    ])
                    ->toArray();
            }

            return [
                'correspondence_threads' => $threads->count(),
                'recent_items' => $recentItems,
            ];
        } catch (\Throwable) {
            return ['correspondence_threads' => 0, 'recent_items' => []];
        }
    }

    protected function getRecordingsSummary(array $entityIds, int $rootTeamId): array
    {
        try {
            // Find recordings linked to these entities via dimension links
            $links = EntityDimensionBridge::linksForEntities($entityIds);
            $recordingIds = $links
                ->where('linkable_type', 'whisper_recording')
                ->pluck('linkable_id')
                ->unique()
                ->all();

            if (empty($recordingIds)) {
                return ['recordings_last_30d' => 0, 'recent_recordings' => []];
            }

            $recordingClass = Relation::getMorphedModel('whisper_recording');
            if (! $recordingClass || ! class_exists($recordingClass)) {
                return ['recordings_last_30d' => 0, 'recent_recordings' => []];
            }

            $recordings = $recordingClass::whereIn('id', $recordingIds)
                ->where('created_at', '>=', now()->subDays(30))
                ->select(['id', 'title', 'summary', 'action_items', 'created_at'])
                ->latest()
                ->limit(5)
                ->get();

            return [
                'recordings_last_30d' => $recordings->count(),
                'recent_recordings' => $recordings->map(fn ($r) => [
                    'title' => $r->title,
                    'summary' => $r->summary ? mb_substr($r->summary, 0, 500) : null,
                    'action_items' => $r->action_items ? mb_substr($r->action_items, 0, 500) : null,
                    'date' => $r->created_at?->format('Y-m-d'),
                ])->toArray(),
            ];
        } catch (\Throwable) {
            return ['recordings_last_30d' => 0, 'recent_recordings' => []];
        }
    }

    protected function getRecentActivities(array $entityIds): array
    {
        try {
            // Get morph class for organization entities
            $morphMap = Relation::morphMap();
            $entityMorphType = array_search(\Platform\Organization\Models\OrganizationEntity::class, $morphMap, true);
            if ($entityMorphType === false) {
                $entityMorphType = \Platform\Organization\Models\OrganizationEntity::class;
            }

            $activities = ActivityLogActivity::where('activityable_type', $entityMorphType)
                ->whereIn('activityable_id', $entityIds)
                ->where('created_at', '>=', now()->subDays(14))
                ->select(['id', 'activity_type', 'name', 'message', 'activityable_id', 'created_at'])
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn ($a) => [
                    'entity_id' => $a->activityable_id,
                    'type' => $a->activity_type,
                    'name' => $a->name,
                    'message' => $a->message ? mb_substr($a->message, 0, 200) : null,
                    'date' => $a->created_at?->format('Y-m-d'),
                ])
                ->toArray();

            return $activities;
        } catch (\Throwable) {
            return [];
        }
    }

    protected function getZukunftsbildContext(int $rootTeamId): array
    {
        try {
            $forecastClass = Relation::getMorphedModel('forecast');
            if (!$forecastClass) {
                $forecastClass = \Platform\Okr\Models\Forecast::class;
            }
            if (!class_exists($forecastClass)) {
                return [];
            }

            $forecast = $forecastClass::where('team_id', $rootTeamId)
                ->with(['focusAreas.visionImages', 'focusAreas.obstacles', 'focusAreas.milestones'])
                ->latest()
                ->first();

            if (!$forecast) {
                return [];
            }

            $lines = [];
            $lines[] = "Forecast: {$forecast->title} (Zieldatum: " . ($forecast->target_date ? $forecast->target_date->format('Y-m-d') : 'offen') . ')';

            $focusAreas = $forecast->focusAreas ?? collect();
            if ($focusAreas->isNotEmpty()) {
                $lines[] = 'FocusAreas:';
                foreach ($focusAreas as $fa) {
                    $lines[] = "- {$fa->title}: " . ($fa->description ? mb_substr($fa->description, 0, 200) : '');

                    $visionImages = $fa->visionImages ?? collect();
                    if ($visionImages->isNotEmpty()) {
                        $viTitles = $visionImages->pluck('title')->filter()->implode(', ');
                        $lines[] = "  VisionImages: {$viTitles}";
                    }

                    $obstacles = $fa->obstacles ?? collect();
                    if ($obstacles->isNotEmpty()) {
                        $obTitles = $obstacles->pluck('title')->filter()->implode(', ');
                        $lines[] = "  Obstacles: {$obTitles}";
                    }

                    $milestones = $fa->milestones ?? collect();
                    if ($milestones->isNotEmpty()) {
                        $msParts = [];
                        $now = now();
                        foreach ($milestones as $ms) {
                            $label = $ms->title;
                            if ($ms->target_date) {
                                if ($ms->target_date->lt($now)) {
                                    $label .= ' (überfällig!)';
                                } else {
                                    $label .= ' (fällig: ' . $ms->target_date->format('Y-m-d') . ')';
                                }
                            }
                            $msParts[] = $label;
                        }
                        $lines[] = '  Milestones: ' . implode(', ', $msParts);
                    }
                }
            }

            return ['forecast_context' => implode("\n", $lines)];
        } catch (\Throwable) {
            return [];
        }
    }

    protected function loadMemoryContext(int $teamId, array $entityIds, int $promptId): array
    {
        try {
            $memory = [];

            // Entity profiles & baselines & suppressions for relevant entities
            $entityMemory = OrganizationMemoryEntry::forTeam($teamId)
                ->whereIn('entity_id', $entityIds)
                ->active()
                ->valid()
                ->whereIn('memory_type', ['entity_profile', 'baseline', 'suppression', 'relationship'])
                ->select(['id', 'entity_id', 'memory_type', 'content', 'structured_data', 'confidence'])
                ->orderByDesc('confidence')
                ->limit(50)
                ->get();

            $memory['entity_memory'] = $entityMemory->map(fn ($m) => [
                'entity_id' => $m->entity_id,
                'type' => $m->memory_type,
                'content' => $m->content,
                'structured_data' => $m->structured_data,
                'confidence' => $m->confidence,
            ])->toArray();

            // Prompt-specific experience
            $promptMemory = OrganizationMemoryEntry::forTeam($teamId)
                ->where('inference_prompt_id', $promptId)
                ->active()
                ->valid()
                ->whereIn('memory_type', ['prompt_experience', 'suppression'])
                ->select(['id', 'memory_type', 'content', 'structured_data', 'confidence'])
                ->orderByDesc('confidence')
                ->limit(10)
                ->get();

            $memory['prompt_experience'] = $promptMemory->map(fn ($m) => [
                'type' => $m->memory_type,
                'content' => $m->content,
                'structured_data' => $m->structured_data,
                'confidence' => $m->confidence,
            ])->toArray();

            // Recent inquiry outcomes for these entities
            $inquiryMemory = OrganizationMemoryEntry::forTeam($teamId)
                ->whereIn('entity_id', $entityIds)
                ->active()
                ->valid()
                ->ofType('inquiry_outcome')
                ->select(['id', 'entity_id', 'content', 'confidence', 'created_at'])
                ->latest()
                ->limit(10)
                ->get();

            $memory['inquiry_outcomes'] = $inquiryMemory->map(fn ($m) => [
                'entity_id' => $m->entity_id,
                'content' => $m->content,
                'confidence' => $m->confidence,
                'date' => $m->created_at?->format('Y-m-d'),
            ])->toArray();

            return $memory;
        } catch (\Throwable) {
            return [];
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'signals', 'inference', 'vsm', 'evaluate', 'context'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
