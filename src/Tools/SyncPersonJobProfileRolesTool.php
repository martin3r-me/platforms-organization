<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationPersonJobProfile;
use Platform\Organization\Models\OrganizationRole;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

/**
 * Setzt die Override-Rollen-Anteile einer PersonJobProfile-Zuweisung.
 * Override gewinnt gegenueber den Default-Anteilen aus dem JobProfile.
 *
 * Leeres roles-Array entfernt alle Overrides — danach gelten wieder die
 * Defaults aus dem JobProfile.
 */
class SyncPersonJobProfileRolesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.person_job_profile_roles.SET';
    }

    public function getDescription(): string
    {
        return 'POST /organization/person-job-profile-roles/sync - Setzt individuelle Rollen-Anteile fuer eine Person-Profile-Zuweisung (Override gegenueber JobProfile-Defaults). Leere Liste entfernt alle Overrides — Defaults greifen wieder. Input: roles als Array von { role_id, percentage_share, sort_order? }.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => ['type' => 'integer'],
                'person_job_profile_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: ID der PersonJobProfile-Zuweisung.'],
                'roles' => [
                    'type' => 'array',
                    'description' => 'ERFORDERLICH: Liste der Rollen-Overrides. Leeres Array entfernt alle Overrides (Defaults aus JobProfile greifen wieder).',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'role_id' => ['type' => 'integer'],
                            'percentage_share' => ['type' => 'integer'],
                            'sort_order' => ['type' => 'integer'],
                        ],
                        'required' => ['role_id'],
                    ],
                ],
            ],
            'required' => ['person_job_profile_id', 'roles'],
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
                $arguments, $context, 'person_job_profile_id',
                OrganizationPersonJobProfile::class, 'NOT_FOUND', 'PersonJobProfile-Zuweisung nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }
            /** @var OrganizationPersonJobProfile $pjp */
            $pjp = $found['model'];
            if ((int) $pjp->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Zuweisung gehoert nicht zum Team.');
            }

            $roles = $arguments['roles'] ?? null;
            if (! is_array($roles)) {
                return ToolResult::error('VALIDATION_ERROR', 'roles muss ein Array sein.');
            }

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

            $pjp->roleOverrides()->sync($sync);

            // Effektive Verteilung neu berechnen — Quelle ist jetzt 'override' wenn nicht leer
            $effective = $pjp->fresh()->effectiveRoleShares();

            return ToolResult::success([
                'person_job_profile_id' => $pjp->id,
                'overrides_count' => count($sync),
                'total_share' => $totalShare,
                'source' => count($sync) > 0 ? 'override' : 'default',
                'effective_roles' => $effective->map(fn ($e) => [
                    'role_id' => $e['role_id'],
                    'role_name' => $e['role']->name,
                    'vsm_system' => $e['role']->vsm_system,
                    'percentage_share' => $e['percentage_share'],
                    'source' => $e['source'],
                ])->values()->all(),
                'message' => count($sync) > 0
                    ? 'Override-Rollen-Anteile gesetzt.' . ($totalShare !== 100 ? " Hinweis: Summe = {$totalShare} (typisch 100)." : '')
                    : 'Alle Overrides entfernt — Defaults aus JobProfile greifen wieder.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'person_job_profiles', 'roles', 'override', 'sync'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
