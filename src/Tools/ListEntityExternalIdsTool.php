<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationEntityExternalId;

/**
 * Listet Fremd-IDs — entweder alle einer Entity, oder als Rückwärts-Auflösung
 * (welche Entity trägt im System X den Wert Y?).
 */
class ListEntityExternalIdsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.entity_external_ids.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/entity-external-ids - Listet Fremd-IDs. Mit entity_id: alle Fremd-IDs dieser Entity. Mit system+value: Rückwärts-Auflösung ("welche Entity ist Kostenstelle KST-4200?"). Optional nur system zum Filtern.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'entity_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: alle Fremd-IDs dieser Entity.',
                ],
                'system' => [
                    'type' => 'string',
                    'description' => 'Optional: nach System filtern (z.B. "kostenstelle", "datev").',
                ],
                'value' => [
                    'type' => 'string',
                    'description' => 'Optional (mit system): Rückwärts-Auflösung auf die Entity.',
                ],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $context->team?->id ?? auth()->user()?->currentTeam?->id;

            $query = OrganizationEntityExternalId::query()->with('entity');
            if ($teamId) {
                $query->where('team_id', $teamId);
            }
            if (!empty($arguments['entity_id'])) {
                $query->where('entity_id', (int) $arguments['entity_id']);
            }
            if (!empty($arguments['system'])) {
                $query->where('system', trim((string) $arguments['system']));
            }
            if (!empty($arguments['value'])) {
                $query->where('value', trim((string) $arguments['value']));
            }

            $items = $query->orderBy('system')->get()->map(fn ($x) => [
                'id' => $x->id,
                'entity_id' => $x->entity_id,
                'entity_name' => $x->entity?->name,
                'system' => $x->system,
                'value' => $x->value,
                'label' => $x->label,
            ])->values();

            return ToolResult::success([
                'count' => $items->count(),
                'external_ids' => $items->all(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['organization', 'entities', 'external-ids', 'cost-center'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'read',
            'idempotent' => true,
        ];
    }
}
