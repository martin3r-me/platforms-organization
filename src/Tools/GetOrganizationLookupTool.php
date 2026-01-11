<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;

/**
 * Generisches Lookup-GET für Organization (derzeit: cost_centers).
 *
 * Hinweis: Für cost_centers gibt es auch organization.cost_centers.GET.
 * Dieses Tool ist für ein konsistentes "lookups/lookup" Pattern gedacht.
 */
class GetOrganizationLookupTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;

    private const LOOKUP_KEYS = ['cost_centers'];

    public function getName(): string
    {
        return 'organization.lookup.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/lookup - Listet Einträge aus einer Organization-Lookup-Tabelle (z.B. cost_centers). Nutze organization.lookups.GET für Keys. Unterstützt Suche/Filter/Sort/Pagination.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['lookup', 'team_id', 'is_active', 'code', 'root_entity_id']),
            [
                'properties' => [
                    'lookup' => [
                        'type' => 'string',
                        'description' => 'ERFORDERLICH. Lookup-Key. Nutze organization.lookups.GET.',
                        'enum' => self::LOOKUP_KEYS,
                    ],
                ],
                'required' => ['lookup'],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        $lookup = (string)($arguments['lookup'] ?? '');
        if ($lookup === '') {
            return ToolResult::error('VALIDATION_ERROR', 'lookup ist erforderlich. Nutze organization.lookups.GET.');
        }

        return match ($lookup) {
            'cost_centers' => (new ListCostCentersTool())->execute($arguments, $context),
            default => ToolResult::error('VALIDATION_ERROR', 'Unbekannter lookup. Nutze organization.lookups.GET.'),
        };
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'lookup', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}

