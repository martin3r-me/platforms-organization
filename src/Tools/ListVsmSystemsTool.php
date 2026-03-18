<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationVsmSystem;

class ListVsmSystemsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.vsm_systems.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/vsm-systems - Listet VSM-Systeme (Viable System Model: S1-S5). VSM-Systeme sind global (nicht team-spezifisch). Nutze dieses Tool bevor du vsm_system_id setzt.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: aktive/inaktive Systeme. Default: alle.',
                ],
                'code' => [
                    'type' => 'string',
                    'description' => 'Optional: Exakter Code-Filter (z.B. "S1").',
                ],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $q = OrganizationVsmSystem::query()->ordered();

            if (array_key_exists('is_active', $arguments)) {
                $q->where('is_active', (bool) $arguments['is_active']);
            }

            if (array_key_exists('code', $arguments) && $arguments['code'] !== null && $arguments['code'] !== '') {
                $q->where('code', trim((string) $arguments['code']));
            }

            $items = $q->get()->map(fn ($s) => [
                'id' => $s->id,
                'code' => $s->code,
                'name' => $s->name,
                'description' => $s->description,
                'sort_order' => $s->sort_order,
                'is_active' => (bool) $s->is_active,
            ])->values()->toArray();

            return ToolResult::success([
                'data' => $items,
                'count' => count($items),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der VSM-Systeme: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'vsm', 'systems', 'lookup'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
