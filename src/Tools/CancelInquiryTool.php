<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationInquiry;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class CancelInquiryTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.inquiries.cancel';
    }

    public function getDescription(): string
    {
        return 'POST /organization/inquiries/{id}/cancel - Storniert eine offene Inquiry.';
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

            if (in_array($inquiry->status, ['completed', 'cancelled'])) {
                return ToolResult::error('INVALID_STATE', 'Inquiry ist bereits abgeschlossen oder storniert.');
            }

            $inquiry->update(['status' => 'cancelled']);

            return ToolResult::success([
                'id' => $inquiry->id,
                'uuid' => $inquiry->uuid,
                'status' => 'cancelled',
                'message' => 'Inquiry storniert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Stornieren: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'inquiries', 'cancel'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
