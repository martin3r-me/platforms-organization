<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationSynthesisReport;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ListSynthesisReportsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.synthesis.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/synthesis - Listet Synthese-Reports (wöchentlich/monatlich). Verdichtete Organisationsdiagnostik für S4/S5.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'report_type', 'status']),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID.',
                    ],
                    'report_type' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Typ (weekly, monthly, quarterly, ad_hoc).',
                    ],
                    'status' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Status (draft, published, archived).',
                    ],
                ],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $q = OrganizationSynthesisReport::query()->where('team_id', $rootTeamId);

            if (! empty($arguments['report_type'])) {
                $q->where('report_type', $arguments['report_type']);
            }

            if (! empty($arguments['status'])) {
                $q->where('status', $arguments['status']);
            }

            $this->applyStandardFilters($q, $arguments, ['team_id', 'report_type', 'status']);
            $this->applyStandardSort($q, $arguments, ['id', 'period_start', 'created_at'], 'period_start', 'desc');

            $result = $this->applyStandardPaginationResult($q, $arguments);

            $items = $result['data']->map(fn ($report) => [
                'id' => $report->id,
                'uuid' => $report->uuid,
                'report_type' => $report->report_type,
                'period_start' => $report->period_start?->format('Y-m-d'),
                'period_end' => $report->period_end?->format('Y-m-d'),
                'title' => $report->title,
                'content' => $report->content,
                'status' => $report->status,
                'algedonic_signals_count' => count($report->algedonic_signals ?? []),
                'signals_included_count' => count($report->signals_included ?? []),
                'published_at' => $report->published_at?->toIso8601String(),
                'created_at' => $report->created_at?->toIso8601String(),
            ])->values()->toArray();

            return ToolResult::success([
                'data' => $items,
                'pagination' => $result['pagination'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Reports: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'synthesis', 'reports', 'vsm'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
