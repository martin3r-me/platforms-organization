<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationStrategicDocument;

class ListStrategicDocumentsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;

    public function getName(): string
    {
        return 'organization.strategic_documents.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/strategic-documents - Listet Mission/Vision-Dokumente. Filter: entity_id, type, is_active.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['entity_id', 'type', 'is_active', 'team_id']),
            [
                'properties' => [
                    'entity_id' => ['type' => 'integer', 'description' => 'Optional: Carrier-Entity filtern.'],
                    'type'      => ['type' => 'string', 'enum' => ['mission', 'vision'], 'description' => 'Optional: Typ-Filter.'],
                    'is_active' => ['type' => 'boolean', 'description' => 'Optional: nur aktive Versionen.'],
                    'team_id'   => ['type' => 'integer', 'description' => 'Optional: Team-Scope.'],
                ],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $q = OrganizationStrategicDocument::query()->whereNull('deleted_at');

            if (!empty($arguments['entity_id'])) {
                $q->where('entity_id', (int) $arguments['entity_id']);
            }
            if (!empty($arguments['type'])) {
                $q->where('type', (string) $arguments['type']);
            }
            if (array_key_exists('is_active', $arguments)) {
                $q->where('is_active', (bool) $arguments['is_active']);
            }
            if (!empty($arguments['team_id'])) {
                $q->where('team_id', (int) $arguments['team_id']);
            }

            $this->applyStandardFilters($q, $arguments, ['entity_id', 'type', 'is_active', 'team_id', 'version', 'created_at']);
            $this->applyStandardSearch($q, $arguments, ['title', 'change_note']);
            $this->applyStandardSort($q, $arguments, ['id', 'version', 'valid_from', 'created_at'], 'id', 'desc');

            $result = $this->applyStandardPaginationResult($q, $arguments);
            $items = $result['data']->map(fn ($d) => [
                'id'         => $d->id,
                'uuid'       => $d->uuid,
                'entity_id'  => $d->entity_id,
                'type'       => $d->type,
                'title'      => $d->title,
                'version'    => $d->version,
                'is_active'  => (bool) $d->is_active,
                'valid_from' => $d->valid_from?->toDateString(),
                'team_id'    => $d->team_id,
            ])->values()->toArray();

            return ToolResult::success([
                'data'       => $items,
                'pagination' => $result['pagination'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'read',
            'tags'          => ['organization', 'strategy', 'strategic_documents', 'lookup'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
