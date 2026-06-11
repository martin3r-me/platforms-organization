<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationJobProfile;
use Platform\Organization\Models\OrganizationRole;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

/**
 * Setzt die Rollen-Verteilung eines JobProfiles atomar — bestehende Pivot-Zeilen
 * werden ersetzt, neue erzeugt. Das ist der praktische Hauptweg, weil ein
 * JobProfile typisch in einem Zug definiert wird (alle Rollen + Anteile auf einmal).
 */
class SyncJobProfileRolesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.job_profile_roles.SET';
    }

    public function getDescription(): string
    {
        return 'POST /organization/job-profile-roles/sync - Setzt die Rollen-Verteilung eines JobProfiles atomar. Vorhandene Rollen-Verknuepfungen werden ersetzt. Input: roles als Array von { role_id, percentage_share, sort_order? }. Summe der percentage_share sollte typisch 100 ergeben.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'job_profile_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: ID des JobProfiles.'],
                'roles' => [
                    'type' => 'array',
                    'description' => 'ERFORDERLICH: Liste der Rollen mit Anteil. Leer = alle Rollen-Verknuepfungen entfernen.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'role_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: ID der Rolle.'],
                            'percentage_share' => ['type' => 'integer', 'description' => 'Anteil in Prozent (0..100). Default: 0.'],
                            'sort_order' => ['type' => 'integer', 'description' => 'Optional: Sortierung. Default: index in der Liste.'],
                        ],
                        'required' => ['role_id'],
                    ],
                ],
            ],
            'required' => ['job_profile_id', 'roles'],
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

            $found = $this->validateAndFindModel(
                $arguments, $context, 'job_profile_id',
                OrganizationJobProfile::class, 'NOT_FOUND', 'JobProfile nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }
            /** @var OrganizationJobProfile $jp */
            $jp = $found['model'];
            if ((int) $jp->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'JobProfile gehoert nicht zum Team.');
            }

            $roles = $arguments['roles'] ?? null;
            if (! is_array($roles)) {
                return ToolResult::error('VALIDATION_ERROR', 'roles muss ein Array sein.');
            }

            // Build sync-Map: role_id => [percentage_share, sort_order]
            $sync = [];
            $totalShare = 0;
            foreach ($roles as $i => $entry) {
                if (! is_array($entry) || ! isset($entry['role_id'])) {
                    return ToolResult::error('VALIDATION_ERROR', 'Jeder roles-Eintrag braucht role_id.');
                }
                $roleId = (int) $entry['role_id'];
                $share = isset($entry['percentage_share']) ? (int) $entry['percentage_share'] : 0;
                if ($share < 0 || $share > 100) {
                    return ToolResult::error('VALIDATION_ERROR', "percentage_share von role_id={$roleId} muss zwischen 0 und 100 liegen.");
                }
                $sortOrder = isset($entry['sort_order']) ? (int) $entry['sort_order'] : $i;

                // Existenz + Team-Pruefung
                $role = OrganizationRole::find($roleId);
                if (! $role) {
                    return ToolResult::error('NOT_FOUND', "Rolle #{$roleId} nicht gefunden.");
                }
                if ((int) $role->team_id !== $rootTeamId) {
                    return ToolResult::error('ACCESS_DENIED', "Rolle #{$roleId} gehoert nicht zum Team.");
                }

                $sync[$roleId] = [
                    'percentage_share' => $share,
                    'sort_order' => $sortOrder,
                ];
                $totalShare += $share;
            }

            $jp->roles()->sync($sync);

            return ToolResult::success([
                'job_profile_id' => $jp->id,
                'roles_count' => count($sync),
                'total_share' => $totalShare,
                'roles' => $jp->roles()->get()->map(fn ($r) => [
                    'role_id' => $r->id,
                    'role_name' => $r->name,
                    'vsm_system' => $r->vsm_system,
                    'percentage_share' => (int) $r->pivot->percentage_share,
                    'sort_order' => (int) $r->pivot->sort_order,
                ])->all(),
                'message' => 'Rollen-Verteilung des JobProfiles aktualisiert.' . ($totalShare !== 100 ? " Hinweis: Summe der Anteile = {$totalShare} (typisch 100)." : ''),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'job_profiles', 'roles', 'sync'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
