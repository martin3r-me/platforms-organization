<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationInquiry;
use Platform\Organization\Models\OrganizationInquiryRecipient;
use Platform\Organization\Models\OrganizationInferenceTrigger;
use Platform\Organization\Models\OrganizationMemoryEntry;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class RespondInquiryTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.inquiries.respond';
    }

    public function getDescription(): string
    {
        return 'POST /organization/inquiries/{id}/respond - Beantwortet eine Inquiry als Empfänger. Sende die Antworten als Key-Value-Paare.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID.',
                ],
                'inquiry_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID der Inquiry.',
                ],
                'recipient_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID des Recipient-Records (organization_inquiry_recipients.id).',
                ],
                'response' => [
                    'type' => 'object',
                    'description' => 'ERFORDERLICH: Antworten als Key-Value-Paare, passend zu den Formular-Feldern.',
                ],
            ],
            'required' => ['inquiry_id', 'recipient_id', 'response'],
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

            $inquiryId = (int) ($arguments['inquiry_id'] ?? 0);
            $recipientId = (int) ($arguments['recipient_id'] ?? 0);
            $response = $arguments['response'] ?? [];

            if (empty($response)) {
                return ToolResult::error('VALIDATION_ERROR', 'response ist erforderlich.');
            }

            $inquiry = OrganizationInquiry::where('id', $inquiryId)
                ->where('team_id', $rootTeamId)
                ->first();

            if (! $inquiry) {
                return ToolResult::error('NOT_FOUND', 'Inquiry nicht gefunden.');
            }

            if (in_array($inquiry->status, ['completed', 'cancelled'])) {
                return ToolResult::error('INVALID_STATE', 'Inquiry ist bereits abgeschlossen oder storniert.');
            }

            $recipient = OrganizationInquiryRecipient::where('id', $recipientId)
                ->where('inquiry_id', $inquiryId)
                ->first();

            if (! $recipient) {
                return ToolResult::error('NOT_FOUND', 'Recipient-Record nicht gefunden.');
            }

            if ($recipient->status === 'answered') {
                return ToolResult::error('ALREADY_ANSWERED', 'Dieser Empfänger hat bereits geantwortet.');
            }

            // Save response
            $recipient->update([
                'response' => $response,
                'response_at' => now(),
                'status' => 'answered',
            ]);

            // Check if inquiry is completed based on recipient_mode
            $completed = $inquiry->checkCompletion();

            // Create memory entry from response
            $this->createInquiryOutcomeMemory($inquiry, $recipient, $response, $rootTeamId);

            // If completed, create a trigger for re-evaluation
            if ($completed) {
                OrganizationInferenceTrigger::createDebounced([
                    'team_id' => $rootTeamId,
                    'trigger_type' => 'inquiry_answered',
                    'trigger_reference' => $inquiry->id,
                    'prompt_filter' => $inquiry->inference_prompt_id
                        ? ['prompt_ids' => [$inquiry->inference_prompt_id]]
                        : null,
                    'entity_filter' => ['entity_ids' => [$inquiry->entity_id]],
                    'priority' => 60,
                    'status' => 'pending',
                    'debounce_key' => "inquiry_answered:{$inquiry->id}",
                ]);
            }

            return ToolResult::success([
                'recipient_id' => $recipient->id,
                'inquiry_status' => $inquiry->fresh()->status,
                'completed' => $completed,
                'message' => $completed
                    ? 'Antwort gespeichert. Inquiry ist abgeschlossen — Re-Evaluation wird ausgelöst.'
                    : 'Antwort gespeichert. Warte auf weitere Antworten.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Beantworten: ' . $e->getMessage());
        }
    }

    protected function createInquiryOutcomeMemory(
        OrganizationInquiry $inquiry,
        OrganizationInquiryRecipient $recipient,
        array $response,
        int $teamId
    ): void {
        try {
            $recipientName = $recipient->recipientEntity?->name ?? 'Unbekannt';
            $responseText = collect($response)->map(fn ($v, $k) => "{$k}: {$v}")->implode(', ');

            OrganizationMemoryEntry::create([
                'team_id' => $teamId,
                'entity_id' => $inquiry->entity_id,
                'inference_prompt_id' => $inquiry->inference_prompt_id,
                'memory_type' => 'inquiry_outcome',
                'content' => "Inquiry-Antwort von {$recipientName}: {$responseText}",
                'structured_data' => [
                    'inquiry_id' => $inquiry->id,
                    'inquiry_type' => $inquiry->inquiry_type,
                    'recipient_entity_id' => $recipient->recipient_entity_id,
                    'response' => $response,
                ],
                'confidence' => 0.7,
                'source_type' => 'inquiry_response',
                'source_id' => $inquiry->id,
                'valid_until' => now()->addDays(90),
                'is_active' => true,
            ]);
        } catch (\Throwable) {
            // Memory creation should never block response saving
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'inquiries', 'respond'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
