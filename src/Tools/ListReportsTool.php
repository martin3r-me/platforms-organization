<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationReport;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ListReportsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.reports.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/reports - Listet generierte Berichte des aktuellen Users. Automatisch gefiltert auf user_id = aktueller User. Unterstützt filters/sort/limit/offset.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'report_type_id', 'entity_id', 'status']),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Default: Team aus Kontext.',
                    ],
                    'report_type_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach Berichtstyp-ID.',
                    ],
                    'entity_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach Entity-ID.',
                    ],
                    'status' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Status (draft/generating/final/failed).',
                        'enum' => ['draft', 'generating', 'final', 'failed'],
                    ],
                    'snapshot_from' => [
                        'type' => 'string',
                        'description' => 'Optional: Berichte ab diesem Datum (ISO 8601).',
                    ],
                    'snapshot_to' => [
                        'type' => 'string',
                        'description' => 'Optional: Berichte bis zu diesem Datum (ISO 8601).',
                    ],
                    'include_content' => [
                        'type' => 'boolean',
                        'description' => 'Optional: generated_content mitladen. Default: false (nur Metadaten).',
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

            // User-Ownership: Berichte gehören dem User, jeder sieht nur seine eigenen
            $userId = $context->user?->id;
            if (!$userId) {
                return ToolResult::error('AUTH_ERROR', 'Kein authentifizierter User.');
            }

            $q = OrganizationReport::query()
                ->where('team_id', $rootTeamId)
                ->where('user_id', $userId);

            if (array_key_exists('report_type_id', $arguments) && $arguments['report_type_id'] !== null) {
                $q->where('report_type_id', (int) $arguments['report_type_id']);
            }

            if (array_key_exists('entity_id', $arguments) && $arguments['entity_id'] !== null) {
                $q->where('entity_id', (int) $arguments['entity_id']);
            }

            if (!empty($arguments['status'])) {
                $q->where('status', (string) $arguments['status']);
            }

            if (!empty($arguments['snapshot_from'])) {
                $q->where('snapshot_at', '>=', $arguments['snapshot_from']);
            }

            if (!empty($arguments['snapshot_to'])) {
                $q->where('snapshot_at', '<=', $arguments['snapshot_to']);
            }

            $this->applyStandardFilters($q, $arguments, ['team_id', 'report_type_id', 'entity_id', 'status', 'created_at']);
            $this->applyStandardSort($q, $arguments, ['id', 'snapshot_at', 'status', 'created_at'], 'created_at', 'desc');

            $result = $this->applyStandardPaginationResult($q, $arguments);
            $includeContent = !empty($arguments['include_content']);

            $items = $result['data']->map(function ($r) use ($includeContent) {
                $item = [
                    'id' => $r->id,
                    'uuid' => $r->uuid,
                    'report_type_id' => $r->report_type_id,
                    'entity_id' => $r->entity_id,
                    'status' => $r->status,
                    'output_channel' => $r->output_channel,
                    'obsidian_path' => $r->obsidian_path,
                    'snapshot_at' => $r->snapshot_at?->toIso8601String(),
                    'error_message' => $r->error_message,
                    'metadata' => $r->metadata,
                    'created_at' => $r->created_at?->toIso8601String(),
                ];

                if ($includeContent) {
                    $item['generated_content'] = $r->generated_content;
                }

                return $item;
            })->values()->toArray();

            return ToolResult::success([
                'data' => $items,
                'pagination' => $result['pagination'] ?? null,
                'team_id' => $resolved['team_id'],
                'root_team_id' => $rootTeamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Berichte: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'reports', 'lookup'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
