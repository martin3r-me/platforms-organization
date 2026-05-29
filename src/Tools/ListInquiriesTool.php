<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationInquiry;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ListInquiriesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.inquiries.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/inquiries - Listet Inquiries (strukturierte Formulare an Stakeholder). Filter nach Status, Entity, Typ.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'status', 'entity_id', 'inquiry_type']),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Default: Team aus Kontext.',
                    ],
                    'status' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Status (pending, partial, completed, timeout, cancelled).',
                    ],
                    'entity_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach Entity-ID.',
                    ],
                    'inquiry_type' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Typ (assessment, clarification, validation, follow_up, context_request, periodic_check_in).',
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

            $q = OrganizationInquiry::query()
                ->where('team_id', $rootTeamId)
                ->with(['entity:id,name', 'recipients.recipientEntity:id,name']);

            if (! empty($arguments['status'])) {
                $q->where('status', $arguments['status']);
            }

            if (! empty($arguments['entity_id'])) {
                $q->where('entity_id', (int) $arguments['entity_id']);
            }

            if (! empty($arguments['inquiry_type'])) {
                $q->where('inquiry_type', $arguments['inquiry_type']);
            }

            $this->applyStandardFilters($q, $arguments, ['team_id', 'status', 'entity_id', 'inquiry_type']);
            $this->applyStandardSearch($q, $arguments, ['context_summary']);
            $this->applyStandardSort($q, $arguments, ['id', 'created_at', 'due_date', 'status'], 'created_at', 'desc');

            $result = $this->applyStandardPaginationResult($q, $arguments);

            $items = $result['data']->map(fn ($inq) => [
                'id' => $inq->id,
                'uuid' => $inq->uuid,
                'entity_id' => $inq->entity_id,
                'entity_name' => $inq->entity?->name,
                'inquiry_type' => $inq->inquiry_type,
                'recipient_mode' => $inq->recipient_mode,
                'status' => $inq->status,
                'due_date' => $inq->due_date?->format('Y-m-d'),
                'context_summary' => $inq->context_summary ? mb_substr($inq->context_summary, 0, 200) : null,
                'fields_count' => count($inq->fields ?? []),
                'recipients' => $inq->recipients->map(fn ($r) => [
                    'id' => $r->id,
                    'recipient_name' => $r->recipientEntity?->name,
                    'status' => $r->status,
                    'response_at' => $r->response_at?->toIso8601String(),
                ])->toArray(),
                'completed_at' => $inq->completed_at?->toIso8601String(),
                'created_at' => $inq->created_at?->toIso8601String(),
            ])->values()->toArray();

            return ToolResult::success([
                'data' => $items,
                'pagination' => $result['pagination'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Inquiries: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'inquiries', 'inference', 'lookup'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
