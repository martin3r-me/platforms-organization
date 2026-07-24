<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Enums\StandardRole;
use Platform\Core\Models\AuthzGrant;
use Platform\Core\Models\Module;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

/**
 * Setzt den Modul-Zugang (an/aus) einer Person — idempotent. Schreibt bzw.
 * entfernt einen authz_grant(scope=module, capability=use) fuer das
 * Person-Entity. Nur Team-Owner/Admin (Root of Trust).
 */
class SetPersonModuleAccessTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.person_module_access.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/person-module-access - Setzt den Modul-Zugang einer Person (an/aus, idempotent). ERFORDERLICH: person_entity_id, module_key, enabled. Nur Team-Admins.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'          => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'person_entity_id' => ['type' => 'integer', 'description' => 'ID der Person-Entity (ERFORDERLICH).'],
                'module_key'       => ['type' => 'string', 'description' => 'Modul-Key, z.B. "crm", "planner" (ERFORDERLICH).'],
                'enabled'          => ['type' => 'boolean', 'description' => 'true = Zugang gewaehren, false = entziehen (ERFORDERLICH).'],
            ],
            'required' => ['person_entity_id', 'module_key', 'enabled'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            // Root of Trust: nur Team-Owner/Admin dürfen Modul-Zugänge vergeben.
            $role = auth()->user()?->teams()->where('teams.id', $rootTeamId)->first()?->pivot?->role;
            if (! in_array($role, StandardRole::getAdminRoles(), true)) {
                return ToolResult::error('ACCESS_DENIED', 'Nur Team-Admins dürfen Modul-Zugänge vergeben.');
            }

            $found = $this->validateAndFindModel(
                $arguments, $context, 'person_entity_id',
                OrganizationEntity::class, 'NOT_FOUND', 'Person-Entity nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }
            $person = $found['model'];
            if ((int) $person->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Person gehört nicht zum Team.');
            }

            if (! \Illuminate\Support\Facades\Schema::hasTable('authz_grant')) {
                return ToolResult::error('KERNEL_NOT_READY', 'Autorisierungs-Kernel ist nicht migriert (authz_grant fehlt).');
            }

            $moduleKey = trim((string) ($arguments['module_key'] ?? ''));
            if ($moduleKey === '' || ! Module::where('key', $moduleKey)->exists()) {
                return ToolResult::error('VALIDATION_ERROR', 'Unbekannter module_key.');
            }

            $enabled = filter_var($arguments['enabled'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($enabled === null) {
                return ToolResult::error('VALIDATION_ERROR', 'enabled muss true oder false sein.');
            }

            $query = AuthzGrant::query()
                ->where('subject_type', 'entity')
                ->where('subject_id', $person->id)
                ->where('scope_type', 'module')
                ->where('scope_key', $moduleKey)
                ->where('capability', 'use');

            if ($enabled) {
                if (! $query->exists()) {
                    AuthzGrant::create([
                        'subject_type' => 'entity',
                        'subject_id'   => $person->id,
                        'capability'   => 'use',
                        'scope_type'   => 'module',
                        'scope_id'     => null,
                        'scope_key'    => $moduleKey,
                        'source'       => 'mcp:person-module',
                        'team_id'      => $person->team_id,
                    ]);
                }
            } else {
                $query->delete();
            }

            return ToolResult::success([
                'person_entity_id' => $person->id,
                'module_key'       => $moduleKey,
                'enabled'          => $enabled,
                'message'          => $enabled ? 'Modul-Zugang gewährt.' : 'Modul-Zugang entzogen.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'persons', 'modules', 'access', 'authz'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
