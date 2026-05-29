<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationInquiry;
use Platform\Organization\Models\OrganizationInquiryRecipient;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class CreateInquiryTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.inquiries.create';
    }

    public function getDescription(): string
    {
        return 'POST /organization/inquiries - Erzeugt eine strukturierte Inquiry (Formular) an Stakeholder. Rate-Limits: max 2/Person/Woche, max 1/Entity/Woche.';
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
                'entity_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: Entity zu der die Inquiry gehört.',
                ],
                'recipients' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'ERFORDERLICH: Array von Person-Entity-IDs als Empfänger.',
                ],
                'inquiry_type' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: assessment, clarification, validation, follow_up, context_request, periodic_check_in.',
                    'enum' => ['assessment', 'clarification', 'validation', 'follow_up', 'context_request', 'periodic_check_in'],
                ],
                'fields' => [
                    'type' => 'array',
                    'description' => 'ERFORDERLICH: Formular-Felder [{key, type, label, options?, optional?}]. Typen: select, text, boolean, date, number.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'key' => ['type' => 'string'],
                            'type' => ['type' => 'string', 'enum' => ['select', 'text', 'boolean', 'date', 'number']],
                            'label' => ['type' => 'string'],
                            'options' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'optional' => ['type' => 'boolean'],
                        ],
                    ],
                ],
                'recipient_mode' => [
                    'type' => 'string',
                    'description' => 'Optional: all (alle müssen antworten), any (einer genügt), consensus (Übereinstimmung prüfen). Default: all.',
                    'enum' => ['all', 'any', 'consensus'],
                ],
                'context_summary' => [
                    'type' => 'string',
                    'description' => 'Optional: Warum wird diese Frage gestellt? Kontext für den Empfänger.',
                ],
                'due_date' => [
                    'type' => 'string',
                    'description' => 'Optional: Fälligkeitsdatum (YYYY-MM-DD). Default: 7 Tage.',
                ],
                'inference_prompt_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID des auslösenden Inference-Prompts.',
                ],
                'inference_run_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID des auslösenden Inference-Runs.',
                ],
            ],
            'required' => ['entity_id', 'recipients', 'inquiry_type', 'fields'],
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

            // Validate entity
            $entityId = (int) ($arguments['entity_id'] ?? 0);
            $entity = OrganizationEntity::where('id', $entityId)->where('team_id', $rootTeamId)->first();
            if (! $entity) {
                return ToolResult::error('NOT_FOUND', 'Entity nicht gefunden.');
            }

            // Validate recipients
            $recipientIds = $arguments['recipients'] ?? [];
            if (empty($recipientIds)) {
                return ToolResult::error('VALIDATION_ERROR', 'Mindestens ein Empfänger erforderlich.');
            }

            // Rate limit: max 1 inquiry per entity per week
            $entityInquiryThisWeek = OrganizationInquiry::forTeam($rootTeamId)
                ->where('entity_id', $entityId)
                ->where('created_at', '>=', now()->startOfWeek())
                ->count();

            if ($entityInquiryThisWeek >= 1) {
                return ToolResult::error('RATE_LIMIT', 'Entity hat diese Woche bereits eine Inquiry. Max 1 pro Entity pro Woche.');
            }

            // Rate limit per recipient: max 2 per person per week
            foreach ($recipientIds as $recipientId) {
                $personInquiryCount = OrganizationInquiryRecipient::where('recipient_entity_id', (int) $recipientId)
                    ->where('created_at', '>=', now()->startOfWeek())
                    ->count();

                if ($personInquiryCount >= 2) {
                    $recipientName = OrganizationEntity::find((int) $recipientId)?->name ?? $recipientId;
                    return ToolResult::error('RATE_LIMIT', "Person {$recipientName} hat diese Woche bereits 2 Inquiries. Max 2 pro Person pro Woche.");
                }

                // Cooldown: 30 days if person ignored last inquiry
                $lastIgnored = OrganizationInquiryRecipient::where('recipient_entity_id', (int) $recipientId)
                    ->where('status', 'timeout')
                    ->where('updated_at', '>=', now()->subDays(30))
                    ->exists();

                if ($lastIgnored) {
                    $recipientName = OrganizationEntity::find((int) $recipientId)?->name ?? $recipientId;
                    return ToolResult::error('COOLDOWN', "Person {$recipientName} hat letzte Inquiry ignoriert. 30 Tage Cooldown aktiv.");
                }
            }

            // Validate fields
            $fields = $arguments['fields'] ?? [];
            if (empty($fields)) {
                return ToolResult::error('VALIDATION_ERROR', 'Mindestens ein Formular-Feld erforderlich.');
            }

            $dueDate = ! empty($arguments['due_date'])
                ? $arguments['due_date']
                : now()->addDays(7)->format('Y-m-d');

            // Create inquiry
            $inquiry = OrganizationInquiry::create([
                'team_id' => $rootTeamId,
                'inference_run_id' => ! empty($arguments['inference_run_id']) ? (int) $arguments['inference_run_id'] : null,
                'inference_prompt_id' => ! empty($arguments['inference_prompt_id']) ? (int) $arguments['inference_prompt_id'] : null,
                'entity_id' => $entityId,
                'inquiry_type' => $arguments['inquiry_type'],
                'recipient_mode' => $arguments['recipient_mode'] ?? 'all',
                'fields' => $fields,
                'context_summary' => $arguments['context_summary'] ?? null,
                'status' => 'pending',
                'due_date' => $dueDate,
            ]);

            // Create recipient records
            foreach ($recipientIds as $recipientId) {
                OrganizationInquiryRecipient::create([
                    'inquiry_id' => $inquiry->id,
                    'recipient_entity_id' => (int) $recipientId,
                    'channel' => 'portal',
                    'status' => 'pending',
                ]);
            }

            return ToolResult::success([
                'id' => $inquiry->id,
                'uuid' => $inquiry->uuid,
                'inquiry_type' => $inquiry->inquiry_type,
                'recipients_count' => count($recipientIds),
                'due_date' => $dueDate,
                'message' => 'Inquiry erstellt und an ' . count($recipientIds) . ' Empfänger verteilt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen der Inquiry: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'inquiries', 'inference', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
