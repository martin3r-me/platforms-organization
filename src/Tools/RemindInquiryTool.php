<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationInquiry;
use Platform\Organization\Models\OrganizationInquiryRecipient;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class RemindInquiryTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.inquiries.remind';
    }

    public function getDescription(): string
    {
        return 'POST /organization/inquiries/{id}/remind - Sendet eine Erinnerung an unbeantwortete Empfänger.';
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
            ],
            'required' => ['inquiry_id'],
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

            $inquiry = OrganizationInquiry::where('id', (int) ($arguments['inquiry_id'] ?? 0))
                ->where('team_id', $rootTeamId)
                ->first();

            if (! $inquiry) {
                return ToolResult::error('NOT_FOUND', 'Inquiry nicht gefunden.');
            }

            if (! in_array($inquiry->status, ['pending', 'partial'])) {
                return ToolResult::error('INVALID_STATE', 'Inquiry ist nicht mehr offen.');
            }

            $pendingRecipients = $inquiry->recipients()
                ->whereIn('status', ['pending', 'sent'])
                ->get();

            if ($pendingRecipients->isEmpty()) {
                return ToolResult::success([
                    'reminded' => 0,
                    'message' => 'Keine offenen Empfänger zum Erinnern.',
                ]);
            }

            $reminded = 0;
            foreach ($pendingRecipients as $recipient) {
                $recipient->update(['reminded_at' => now()]);
                $reminded++;
                // TODO: Send actual notification (email/portal/whatsapp)
            }

            return ToolResult::success([
                'inquiry_id' => $inquiry->id,
                'reminded' => $reminded,
                'message' => "{$reminded} Empfänger erinnert.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erinnern: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'inquiries', 'remind'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
