<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationPersonJobProfile;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class DeletePersonJobProfileTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.person_job_profiles.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/person-job-profiles/{id} - Entfernt eine JobProfile-Zuweisung von einer Person (soft delete).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'               => ['type' => 'integer'],
                'person_job_profile_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: ID der Zuweisung.'],
            ],
            'required' => ['person_job_profile_id'],
        ]);
    }

    protected function getAccessAction(): string
    {
        return 'delete';
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
                $arguments,
                $context,
                'person_job_profile_id',
                OrganizationPersonJobProfile::class,
                'NOT_FOUND',
                'Person-JobProfile-Zuweisung nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationPersonJobProfile $a */
            $a = $found['model'];
            if ((int) $a->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Zuweisung gehört nicht zum Root/Elterteam des angegebenen Teams.');
            }

            $a->delete();

            return ToolResult::success([
                'id'      => $a->id,
                'message' => 'Zuweisung gelöscht (soft delete).',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'person_job_profiles', 'delete'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
