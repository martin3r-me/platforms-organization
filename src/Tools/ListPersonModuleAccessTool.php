<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\AuthzGrant;
use Platform\Core\Models\Module;
use Platform\Organization\Models\OrganizationEntity;

/**
 * Listet den Modul-Zugang (an/aus) einer Person. Spiegelt den "Module"-Tab am
 * Person-Entity: pro Modul ob ein authz_grant(scope=module, capability=use)
 * fuer dieses Person-Entity existiert.
 */
class ListPersonModuleAccessTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.person_module_access.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/person-module-access - Listet den Modul-Zugang (an/aus) einer Person. Zeigt pro Modul, ob die Person es nutzen darf. ERFORDERLICH: person_entity_id.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team.',
                ],
                'person_entity_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Person-Entity (ERFORDERLICH).',
                ],
            ],
            'required' => ['person_entity_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $context->team?->id ?? auth()->user()?->currentTeam?->id;

            $person = OrganizationEntity::find((int) ($arguments['person_entity_id'] ?? 0));
            if (! $person || ($teamId && (int) $person->team_id !== (int) $teamId)) {
                return ToolResult::error('NOT_FOUND', 'Person-Entity nicht gefunden oder gehört nicht zum Team.');
            }

            if (! \Illuminate\Support\Facades\Schema::hasTable('authz_grant')) {
                return ToolResult::error('KERNEL_NOT_READY', 'Autorisierungs-Kernel ist nicht migriert (authz_grant fehlt).');
            }

            $granted = AuthzGrant::query()
                ->where('subject_type', 'entity')
                ->where('subject_id', $person->id)
                ->where('scope_type', 'module')
                ->where('capability', 'use')
                ->pluck('scope_key')
                ->all();

            $modules = Module::query()
                ->orderBy('title')
                ->get(['key', 'title'])
                ->map(fn ($m) => [
                    'module_key' => $m->key,
                    'title'      => $m->title ?: $m->key,
                    'enabled'    => in_array($m->key, $granted, true),
                ])
                ->values();

            return ToolResult::success([
                'person_entity_id' => $person->id,
                'person_name'      => $person->name,
                'enabled_count'    => $modules->where('enabled', true)->count(),
                'modules'          => $modules->all(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['organization', 'persons', 'modules', 'access', 'authz'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'read',
            'idempotent'    => true,
        ];
    }
}
