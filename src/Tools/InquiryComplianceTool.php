<?php

namespace Platform\Organization\Tools;

use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationInquiry;
use Platform\Organization\Models\OrganizationInquiryRecipient;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class InquiryComplianceTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.inquiries.compliance';
    }

    public function getDescription(): string
    {
        return 'GET /organization/inquiries/compliance - S5-Dashboard: Zeigt Inquiry-Compliance pro Person (Antwortrate, offene Inquiries, Überfällige).';
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
            ],
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

            // Get all inquiry IDs for this team
            $inquiryIds = OrganizationInquiry::forTeam($rootTeamId)->pluck('id');

            if ($inquiryIds->isEmpty()) {
                return ToolResult::success([
                    'data' => [],
                    'summary' => ['total_inquiries' => 0],
                ]);
            }

            // Per-person compliance stats
            $recipientStats = OrganizationInquiryRecipient::whereIn('inquiry_id', $inquiryIds)
                ->select('recipient_entity_id')
                ->selectRaw('COUNT(*) as total')
                ->selectRaw("SUM(CASE WHEN status = 'answered' THEN 1 ELSE 0 END) as answered")
                ->selectRaw("SUM(CASE WHEN status = 'timeout' THEN 1 ELSE 0 END) as timeouts")
                ->selectRaw("SUM(CASE WHEN status IN ('pending', 'sent') THEN 1 ELSE 0 END) as pending")
                ->groupBy('recipient_entity_id')
                ->get();

            // Load entity names
            $entityIds = $recipientStats->pluck('recipient_entity_id')->unique();
            $entityNames = \Platform\Organization\Models\OrganizationEntity::whereIn('id', $entityIds)
                ->pluck('name', 'id');

            $data = $recipientStats->map(function ($stat) use ($entityNames) {
                $total = (int) $stat->total;
                $answered = (int) $stat->answered;

                return [
                    'person_entity_id' => $stat->recipient_entity_id,
                    'person_name' => $entityNames[$stat->recipient_entity_id] ?? 'Unbekannt',
                    'total_inquiries' => $total,
                    'answered' => $answered,
                    'timeouts' => (int) $stat->timeouts,
                    'pending' => (int) $stat->pending,
                    'compliance_rate' => $total > 0 ? round($answered / $total, 2) : 0.0,
                ];
            })->sortBy('compliance_rate')->values()->toArray();

            // Overdue inquiries
            $overdueCount = OrganizationInquiry::forTeam($rootTeamId)
                ->overdue()
                ->count();

            return ToolResult::success([
                'data' => $data,
                'summary' => [
                    'total_persons' => count($data),
                    'overdue_inquiries' => $overdueCount,
                    'total_inquiries' => OrganizationInquiry::forTeam($rootTeamId)->count(),
                    'open_inquiries' => OrganizationInquiry::forTeam($rootTeamId)->open()->count(),
                ],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Compliance-Daten: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'inquiries', 'compliance', 's5', 'dashboard'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
