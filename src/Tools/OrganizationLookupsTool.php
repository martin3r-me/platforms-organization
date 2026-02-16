<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Services\ContextTypeRegistry;

/**
 * Übersicht über verfügbare Organization-Lookups.
 *
 * Zweck: Agenten sollen IDs (z.B. Kostenstellen) nicht raten, sondern deterministisch nachschlagen.
 */
class OrganizationLookupsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.lookups.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/lookups - Listet verfügbare Organization-Lookup-Typen (Keys) auf. Nutze danach "organization.lookup.GET" oder spezifische GET-Tools (IDs nie raten).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => new \stdClass(),
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return ToolResult::success([
            'lookups' => [
                [
                    'key' => 'cost_centers',
                    'description' => 'Kostenstellen (Root/Elterteam-scoped). Suche nach code/name und verwende dann cost_center_id in anderen Modulen.',
                    'tool' => 'organization.lookup.GET',
                ],
                [
                    'key' => 'context_types',
                    'description' => 'Erlaubte context_type-Werte für Zeiteinträge (organization.time_entries.POST/PUT/GET). Kurzformen wie "project", "task" etc. werden automatisch aufgelöst.',
                    'tool' => 'organization.lookup.GET',
                ],
            ],
            'how_to' => [
                'step_1' => 'Nutze organization.lookups.GET um den passenden lookup-key zu finden.',
                'step_2' => 'Nutze organization.lookup.GET (oder organization.cost_centers.GET) um Einträge zu suchen. IDs nie raten.',
                'step_3' => 'Verwende die gefundene id in Write-Tools (z.B. hcm.contracts.PUT cost_center_id=...).',
            ],
        ]);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'lookup', 'help', 'overview'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}

