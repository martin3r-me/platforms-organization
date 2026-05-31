<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationInferencePromptStat;
use Platform\Organization\Models\OrganizationSignal;
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

            // Create signal
            $signal = OrganizationSignal::create([
                'team_id' => $rootTeamId,
                'source' => 'inference',
                'inference_prompt_id' => $promptId,
                'entity_id' => $entityId,
                'status' => 'open',
                'severity' => $severity,
                'message' => $message,
                'trigger_metrics' => $arguments['evidence'] ?? null,
                'suggested_actions' => $suggestedActions,
            ]);

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
