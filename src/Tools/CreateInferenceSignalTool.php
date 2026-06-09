<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityType;
use Platform\Organization\Models\OrganizationEntityVsmAssignment;
use Platform\Organization\Models\OrganizationInferencePromptStat;
use Platform\Organization\Models\OrganizationSignal;
use Platform\Organization\Models\OrganizationSignalAction;
use Platform\Organization\Models\OrganizationSignalInferencePrompt;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class CreateInferenceSignalTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.signal_inference.create_signal';
    }

    public function getDescription(): string
    {
        return 'POST /organization/signal_inference/create_signal - Erzeugt ein Signal aus Claudes Inferenz-Ergebnis. Nutze organization.signal_inference.evaluate um zuerst den Kontext zu laden und zu analysieren.';
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
                    'description' => 'ERFORDERLICH: ID des Inference-Prompts, der die Erkenntnis erzeugt hat.',
                ],
                'entity_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID der betroffenen Entity.',
                ],
                'severity' => [
                    'type' => 'string',
                    'description' => 'Optional: info, warning, critical. Default: default_severity des Prompts.',
                    'enum' => ['info', 'warning', 'critical', 'algedonic'],
                ],
                'message' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Claudes Erkenntnis / diagnostische Aussage.',
                ],
                'evidence' => [
                    'type' => 'object',
                    'description' => 'Optional: Quellen-Referenzen (z.B. transcript_ids, correspondence_ids, snapshot_keys).',
                ],
                'suggested_actions' => [
                    'type' => 'array',
                    'description' => 'Optional: 1-3 konkrete Handlungsoptionen. Jede Option hat title (kurze Aktion) und description (Erklärung warum/wie).',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string', 'description' => 'Kurze Handlungsaufforderung (z.B. "Weekly mit Team X einrichten")'],
                            'description' => ['type' => 'string', 'description' => 'Erklärung und erwartete Wirkung'],
                        ],
                        'required' => ['title'],
                    ],
                    'maxItems' => 3,
                ],
                'affected_entity_ids' => [
                    'type' => 'array',
                    'description' => 'Optional: IDs weiterer betroffener Entities.',
                    'items' => ['type' => 'integer'],
                ],
                'assignee_entity_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID der Entity, die für die Bearbeitung zuständig ist.',
                ],
                'perspective_entity_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Carrier-Entity, aus deren Sicht das Signal entsteht. Default: Parent-Carrier des Agents (= Carrier, dessen Kontext der Agent bedient). Muss ein vsm_class=carrier sein.',
                ],
            ],
            'required' => ['inference_prompt_id', 'entity_id', 'message'],
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

            // Validate inference_prompt_id
            $promptId = (int) ($arguments['inference_prompt_id'] ?? 0);
            if ($promptId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'inference_prompt_id ist erforderlich.');
            }

            $prompt = OrganizationSignalInferencePrompt::where('id', $promptId)
                ->where('team_id', $rootTeamId)
                ->first();

            if (! $prompt) {
                return ToolResult::error('NOT_FOUND', 'Inference-Prompt nicht gefunden.');
            }

            // Validate entity_id
            $entityId = (int) ($arguments['entity_id'] ?? 0);
            if ($entityId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'entity_id ist erforderlich.');
            }

            $entity = OrganizationEntity::where('id', $entityId)
                ->where('team_id', $rootTeamId)
                ->first();

            if (! $entity) {
                return ToolResult::error('NOT_FOUND', 'Entity nicht gefunden.');
            }

            // Validate message
            $message = trim((string) ($arguments['message'] ?? ''));
            if ($message === '') {
                return ToolResult::error('VALIDATION_ERROR', 'message ist erforderlich.');
            }

            // Check for existing open inference signal for this prompt + entity
            $existing = OrganizationSignal::where('inference_prompt_id', $promptId)
                ->where('entity_id', $entityId)
                ->where('team_id', $rootTeamId)
                ->open()
                ->first();

            if ($existing) {
                return ToolResult::success([
                    'id' => $existing->id,
                    'uuid' => $existing->uuid,
                    'skipped' => true,
                    'message' => 'Es existiert bereits ein offenes Inference-Signal für diesen Prompt und diese Entity.',
                ]);
            }

            // Resolve severity
            $severity = $arguments['severity'] ?? $prompt->default_severity ?? 'warning';
            if (! in_array($severity, ['info', 'warning', 'critical', 'algedonic'])) {
                $severity = 'warning';
            }

            // Validate suggested_actions
            $suggestedActions = null;
            if (! empty($arguments['suggested_actions']) && is_array($arguments['suggested_actions'])) {
                $suggestedActions = array_slice(
                    array_map(fn ($a) => [
                        'title' => trim((string) ($a['title'] ?? '')),
                        'description' => trim((string) ($a['description'] ?? '')),
                    ], $arguments['suggested_actions']),
                    0,
                    3
                );
                $suggestedActions = array_filter($suggestedActions, fn ($a) => $a['title'] !== '');
                $suggestedActions = array_values($suggestedActions);
                if (empty($suggestedActions)) {
                    $suggestedActions = null;
                }
            }

            // Validate affected_entity_ids if provided
            $affectedEntityIds = null;
            if (! empty($arguments['affected_entity_ids']) && is_array($arguments['affected_entity_ids'])) {
                $ids = array_map('intval', $arguments['affected_entity_ids']);
                $existingCount = OrganizationEntity::where('team_id', $rootTeamId)
                    ->whereIn('id', $ids)
                    ->count();
                if ($existingCount !== count($ids)) {
                    return ToolResult::error('VALIDATION_ERROR', 'Nicht alle affected_entity_ids gehören zum selben Team.');
                }
                $affectedEntityIds = $ids;
            }

            // Validate assignee_entity_id if provided
            $assigneeEntityId = ! empty($arguments['assignee_entity_id']) ? (int) $arguments['assignee_entity_id'] : null;
            if ($assigneeEntityId) {
                $assignee = OrganizationEntity::where('id', $assigneeEntityId)
                    ->where('team_id', $rootTeamId)
                    ->first();
                if (! $assignee) {
                    return ToolResult::error('NOT_FOUND', 'Assignee-Entity nicht gefunden.');
                }
            }

            // Resolve perspective_entity_id: arg > Agent-Parent > Root-Carrier
            $perspectiveEntityId = $this->resolvePerspectiveEntityId(
                $arguments['perspective_entity_id'] ?? null,
                $prompt->agent_entity_id ?? null,
                $rootTeamId
            );

            // Routing: vsm_level + source_type aus Prompt ableiten
            $vsmLevel = $prompt->vsm_system ?: null;
            $sourceType = $vsmLevel === 's3_star'
                ? OrganizationSignal::SOURCE_TYPE_INFERENCE_S3STAR
                : OrganizationSignal::SOURCE_TYPE_INFERENCE;

            // Current owner: 1) assignee_entity_id (explizit) 2) VSM-Assignment-Lookup
            $currentOwnerId = $assigneeEntityId
                ?? $this->resolveCurrentOwner($perspectiveEntityId, $vsmLevel);

            // Deadline: konfigurierbar pro VSM-Ebene, default 7 Tage
            $deadlineHours = (int) config(
                "organization.signal_deadlines.{$vsmLevel}",
                config('organization.signal_deadlines.default', 168) // 7 Tage
            );
            $deadlineAt = now()->copy()->addHours($deadlineHours);

            // Create signal
            $signal = OrganizationSignal::create([
                'team_id' => $rootTeamId,
                'source' => 'inference',
                'source_type' => $sourceType,
                'inference_prompt_id' => $promptId,
                'entity_id' => $entityId,
                'perspective_entity_id' => $perspectiveEntityId,
                'created_by_agent_entity_id' => $prompt->agent_entity_id ?? null,
                'current_owner_entity_id' => $currentOwnerId,
                'vsm_level' => $vsmLevel,
                'status' => 'open',
                'severity' => $severity,
                'message' => $message,
                'trigger_metrics' => $arguments['evidence'] ?? [],
                'suggested_actions' => $suggestedActions,
                'deadline_at' => $deadlineAt,
                'affected_entity_ids' => $affectedEntityIds,
                'assignee_entity_id' => $assigneeEntityId,
            ]);

            // Materialize suggested_actions into rows for per-action feedback
            if (! empty($suggestedActions)) {
                foreach (array_values($suggestedActions) as $idx => $action) {
                    OrganizationSignalAction::create([
                        'signal_id' => $signal->id,
                        'position' => $idx,
                        'title' => mb_substr((string) $action['title'], 0, 255),
                        'description' => $action['description'] ?? null,
                        'status' => 'pending',
                    ]);
                }
            }

            // Update prompt stats: signals_created
            try {
                $period = now()->startOfMonth()->toDateString();
                $stat = OrganizationInferencePromptStat::firstOrCreate(
                    ['inference_prompt_id' => $promptId, 'period' => $period],
                    ['signals_created' => 0, 'signals_acknowledged' => 0, 'signals_dismissed' => 0, 'signals_resolved' => 0, 'precision' => 0.0]
                );
                $stat->increment('signals_created');
            } catch (\Throwable) {}

            return ToolResult::success([
                'id' => $signal->id,
                'uuid' => $signal->uuid,
                'entity_id' => $signal->entity_id,
                'entity_name' => $entity->name,
                'severity' => $signal->severity,
                'source' => 'inference',
                'inference_prompt_name' => $prompt->name,
                'message' => 'Inference-Signal erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Inference-Signals: ' . $e->getMessage());
        }
    }

    /**
     * Aufloesungs-Kaskade fuer perspective_entity_id:
     *  1. Explizites Argument (muss Carrier sein, sonst ignoriert)
     *  2. Parent des Agent-Entitys des Prompts (Carrier, in dessen Kontext der Agent bedient)
     *  3. Root-Carrier des Teams
     *  4. NULL (Fallback, UI behandelt das als "in allen Perspektiven sichtbar")
     */
    protected function resolvePerspectiveEntityId(?int $argId, ?int $agentEntityId, int $teamId): ?int
    {
        if ($argId) {
            $cand = OrganizationEntity::with('type')->find($argId);
            if ($cand && $cand->team_id === $teamId
                && $cand->type?->vsm_class === OrganizationEntityType::VSM_CLASS_CARRIER) {
                return $cand->id;
            }
        }

        if ($agentEntityId) {
            $agent = OrganizationEntity::with('parent.type')->find($agentEntityId);
            $parent = $agent?->parent;
            if ($parent && $parent->team_id === $teamId
                && $parent->type?->vsm_class === OrganizationEntityType::VSM_CLASS_CARRIER) {
                return $parent->id;
            }
        }

        $rootCarrier = OrganizationEntity::query()
            ->where('team_id', $teamId)
            ->whereNull('parent_entity_id')
            ->whereHas('type', fn ($q) => $q->where('vsm_class', OrganizationEntityType::VSM_CLASS_CARRIER))
            ->orderBy('id')
            ->value('id');

        return $rootCarrier ? (int) $rootCarrier : null;
    }

    /**
     * Aktueller Owner: erster *menschlicher* Actor (kein system_agent) in der
     * Cell oder eine Ebene hoeher, falls dort nur Agenten sitzen. Verhindert,
     * dass Signale bei Agenten haengen bleiben (Agenten erfuellen Funktionen,
     * tragen aber keine Verantwortung).
     */
    protected function resolveCurrentOwner(?int $perspectiveEntityId, ?string $vsmLevel): ?int
    {
        return \Platform\Organization\Services\PerspectiveService::resolveHumanOwner($perspectiveEntityId, $vsmLevel);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'signals', 'inference', 'vsm', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
