<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationJobProfile;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ListJobProfileRolesTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.job_profile_roles.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/job-profile-roles?job_profile_id=X - Listet die Rollen-Verteilung eines JobProfiles mit Anteil und VSM-System.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => ['type' => 'integer'],
                'job_profile_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: ID des JobProfiles.'],
            ],
            'required' => ['job_profile_id'],
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

            $jobProfileId = (int) ($arguments['job_profile_id'] ?? 0);
            $jp = OrganizationJobProfile::query()
                ->where('id', $jobProfileId)
                ->where('team_id', $rootTeamId)
                ->first();
            if (! $jp) {
                return ToolResult::error('NOT_FOUND', 'JobProfile nicht gefunden.');
            }

            $rows = $jp->roles()->get()->map(fn ($r) => [
                'role_id' => $r->id,
                'role_name' => $r->name,
                'role_slug' => $r->slug,
                'vsm_system' => $r->vsm_system,
                'percentage_share' => (int) $r->pivot->percentage_share,
                'sort_order' => (int) $r->pivot->sort_order,
            ])->all();

            return ToolResult::success([
                'job_profile_id' => $jp->id,
                'job_profile_name' => $jp->name,
                'roles' => $rows,
                'total_share' => array_sum(array_column($rows, 'percentage_share')),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'job_profiles', 'roles'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
