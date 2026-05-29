<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationInferencePromptStat;
use Platform\Organization\Models\OrganizationMemoryEntry;
use Platform\Organization\Models\OrganizationSignal;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class AcknowledgeSignalTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.signals.acknowledge';
    }

    public function getDescription(): string
    {
        return 'POST /organization/signals/{id}/acknowledge - Ändert den Status eines Signals (acknowledge, resolve, dismiss). Erzeugt automatisch Memory-Entries für die Lernpipeline.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (wird auf Root/Elterteam aufgelöst). Default: Team aus Kontext.',
                ],
                'signal_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID des Signals.',
                ],
                'action' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: acknowledge, resolve oder dismiss.',
                    'enum' => ['acknowledge', 'resolve', 'dismiss'],
                ],
                'reason' => [
                    'type' => 'string',
                    'description' => 'Optional bei acknowledge/resolve, EMPFOHLEN bei dismiss: Begründung. Bei dismiss wird diese als Suppression in die Memory-Pipeline übernommen.',
                ],
            ],
            'required' => ['signal_id', 'action'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $found = $this->validateAndFindModel(
                $arguments,
                $context,
                'signal_id',
                OrganizationSignal::class,
                'NOT_FOUND',
                'Signal nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationSignal $signal */
            $signal = $found['model'];

            if ((int) $signal->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Signal gehört nicht zum Root/Elterteam des angegebenen Teams.');
            }

            $action = $arguments['action'] ?? '';
            if (! in_array($action, ['acknowledge', 'resolve', 'dismiss'])) {
                return ToolResult::error('VALIDATION_ERROR', 'action muss acknowledge, resolve oder dismiss sein.');
            }

            $reason = trim((string) ($arguments['reason'] ?? ''));

            $update = match ($action) {
                'acknowledge' => ['status' => 'acknowledged'],
                'resolve' => [
                    'status' => 'resolved',
                    'resolved_at' => now(),
                    'resolved_by' => $context->user?->id,
                ],
                'dismiss' => [
                    'status' => 'dismissed',
                    'dismissed_reason' => $reason ?: null,
                ],
            };

            $signal->update($update);

            // Asymmetric learning: create memory entries based on feedback
            $this->processSignalFeedback($signal, $action, $reason, $rootTeamId);

            $statusLabels = [
                'acknowledge' => 'bestätigt',
                'resolve' => 'gelöst',
                'dismiss' => 'verworfen',
            ];

            return ToolResult::success([
                'id' => $signal->id,
                'uuid' => $signal->uuid,
                'status' => $signal->status,
                'message' => 'Signal ' . $statusLabels[$action] . '.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Signals: ' . $e->getMessage());
        }
    }

    /**
     * Process signal feedback into the learning pipeline.
     * Acknowledge -> Baseline (confidence 0.9, no expiry)
     * Dismiss + reason -> Suppression (confidence 0.9, no expiry)
     * Resolve -> Baseline update
     */
    protected function processSignalFeedback(OrganizationSignal $signal, string $action, string $reason, int $teamId): void
    {
        try {
            // Only create memory for inference signals (not rule-based)
            if ($signal->source !== 'inference' || ! $signal->inference_prompt_id) {
                return;
            }

            match ($action) {
                'acknowledge' => $this->createBaselineMemory($signal, $teamId),
                'dismiss' => $this->createSuppressionMemory($signal, $reason, $teamId),
                'resolve' => $this->createResolvedMemory($signal, $teamId),
            };

            // Update prompt precision stats
            $this->updatePromptStats($signal->inference_prompt_id, $action);
        } catch (\Throwable) {
            // Memory creation should never block signal acknowledgment
        }
    }

    protected function createBaselineMemory(OrganizationSignal $signal, int $teamId): void
    {
        OrganizationMemoryEntry::create([
            'team_id' => $teamId,
            'entity_id' => $signal->entity_id,
            'inference_prompt_id' => $signal->inference_prompt_id,
            'memory_type' => 'baseline',
            'content' => 'Signal bestätigt: ' . mb_substr($signal->message, 0, 500),
            'structured_data' => [
                'signal_id' => $signal->id,
                'severity' => $signal->severity,
                'trigger_metrics' => $signal->trigger_metrics,
            ],
            'confidence' => 0.9,
            'source_type' => 'signal_feedback',
            'source_id' => $signal->id,
            'valid_until' => null, // No expiry for explicit feedback
            'is_active' => true,
        ]);
    }

    protected function createSuppressionMemory(OrganizationSignal $signal, string $reason, int $teamId): void
    {
        $content = $reason
            ? 'Signal verworfen: ' . $reason
            : 'Signal verworfen (ohne Begründung): ' . mb_substr($signal->message, 0, 300);

        OrganizationMemoryEntry::create([
            'team_id' => $teamId,
            'entity_id' => $signal->entity_id,
            'inference_prompt_id' => $signal->inference_prompt_id,
            'memory_type' => 'suppression',
            'content' => $content,
            'structured_data' => [
                'signal_id' => $signal->id,
                'severity' => $signal->severity,
                'reason' => $reason ?: null,
            ],
            'confidence' => 0.9,
            'source_type' => 'signal_feedback',
            'source_id' => $signal->id,
            'valid_until' => null, // No expiry for explicit feedback
            'is_active' => true,
        ]);
    }

    protected function createResolvedMemory(OrganizationSignal $signal, int $teamId): void
    {
        OrganizationMemoryEntry::create([
            'team_id' => $teamId,
            'entity_id' => $signal->entity_id,
            'inference_prompt_id' => $signal->inference_prompt_id,
            'memory_type' => 'baseline',
            'content' => 'Signal gelöst: ' . mb_substr($signal->message, 0, 500),
            'structured_data' => [
                'signal_id' => $signal->id,
                'severity' => $signal->severity,
                'resolved' => true,
            ],
            'confidence' => 0.9,
            'source_type' => 'signal_feedback',
            'source_id' => $signal->id,
            'valid_until' => null,
            'is_active' => true,
        ]);
    }

    protected function updatePromptStats(int $promptId, string $action): void
    {
        $period = now()->startOfMonth()->toDateString();

        $stat = OrganizationInferencePromptStat::firstOrCreate(
            ['inference_prompt_id' => $promptId, 'period' => $period],
            ['signals_created' => 0, 'signals_acknowledged' => 0, 'signals_dismissed' => 0, 'signals_resolved' => 0, 'precision' => 0.0]
        );

        match ($action) {
            'acknowledge' => $stat->increment('signals_acknowledged'),
            'dismiss' => $stat->increment('signals_dismissed'),
            'resolve' => $stat->increment('signals_resolved'),
        };

        // Recalculate precision
        $stat->refresh();
        $total = $stat->signals_acknowledged + $stat->signals_dismissed;
        $stat->precision = $total > 0 ? round($stat->signals_acknowledged / $total, 3) : 0.0;
        $stat->save();
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'signals', 'algedonic', 'acknowledge', 'feedback'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
