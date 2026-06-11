<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationPersonJobProfile;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

/**
 * Liefert die effektive Rollen-Verteilung einer PersonJobProfile-Zuweisung.
 * Inklusive Quellen-Markierung ('override' oder 'default') und der
 * Multiplikation mit der overall percentage als 'effective_overall_share'.
 */
class GetPersonJobProfileEffectiveRolesTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.person_job_profile_roles.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/person-job-profile-roles?person_job_profile_id=X - Effektive Rollen-Verteilung einer Person-Profile-Zuweisung. Zeigt Override (wenn gesetzt) oder Default-Anteile aus dem JobProfile, jeweils auch multipliziert mit der overall percentage.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => ['type' => 'integer'],
                'person_job_profile_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH.'],
            ],
            'required' => ['person_job_profile_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $id = (int) ($arguments['person_job_profile_id'] ?? 0);
            $pjp = OrganizationPersonJobProfile::query()
                ->where('id', $id)
                ->where('team_id', $rootTeamId)
                ->with(['jobProfile', 'person', 'contextEntity'])
                ->first();
            if (! $pjp) {
                return ToolResult::error('NOT_FOUND', 'PersonJobProfile-Zuweisung nicht gefunden.');
            }

            $overall = (int) $pjp->percentage;
            $rows = $pjp->effectiveRoleShares()->map(function ($e) use ($overall) {
                $share = (int) $e['percentage_share'];
                return [
                    'role_id' => $e['role_id'],
                    'role_name' => $e['role']->name,
                    'vsm_system' => $e['role']->vsm_system,
                    'percentage_share' => $share,
                    'effective_overall_share' => (int) round($share * $overall / 100),
                    'source' => $e['source'],
                ];
            })->values()->all();

            return ToolResult::success([
                'person_job_profile_id' => $pjp->id,
                'person_name' => $pjp->person?->name,
                'job_profile_id' => $pjp->job_profile_id,
                'job_profile_name' => $pjp->jobProfile?->name,
                'context_entity_id' => $pjp->context_entity_id,
                'context_name' => $pjp->contextEntity?->name,
                'overall_percentage' => $overall,
                'is_primary' => (bool) $pjp->is_primary,
                'effective_roles' => $rows,
                'total_share' => array_sum(array_column($rows, 'percentage_share')),
                'total_effective_share' => array_sum(array_column($rows, 'effective_overall_share')),
                'override_active' => ! empty($rows) && $rows[0]['source'] === 'override',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'person_job_profiles', 'roles', 'effective'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
