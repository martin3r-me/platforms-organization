<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationPersonJobProfile;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ListPersonJobProfilesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.person_job_profiles.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/person-job-profiles - Listet JobProfile-Zuweisungen an Personen. Filter: person_entity_id, job_profile_id.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'person_entity_id', 'job_profile_id']),
            [
                'properties' => [
                    'team_id'          => ['type' => 'integer'],
                    'person_entity_id' => ['type' => 'integer', 'description' => 'Optional: Nur Zuweisungen einer Person.'],
                    'job_profile_id'   => ['type' => 'integer', 'description' => 'Optional: Nur Zuweisungen eines bestimmten JobProfiles.'],
                ],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $q = OrganizationPersonJobProfile::query()
                ->with(['person:id,name,entity_type_id', 'jobProfile:id,name,level'])
                ->where('team_id', $rootTeamId);

            if (! empty($arguments['person_entity_id'])) {
                $q->where('person_entity_id', (int) $arguments['person_entity_id']);
            }
            if (! empty($arguments['job_profile_id'])) {
                $q->where('job_profile_id', (int) $arguments['job_profile_id']);
            }

            $this->applyStandardFilters($q, $arguments, ['team_id', 'person_entity_id', 'job_profile_id', 'is_primary', 'created_at']);
            $this->applyStandardSort($q, $arguments, ['id', 'percentage', 'valid_from', 'created_at'], 'id', 'desc');

            $result = $this->applyStandardPaginationResult($q, $arguments);
            $items = $result['data']->map(fn (OrganizationPersonJobProfile $a) => [
                'id'               => $a->id,
                'person_entity_id' => $a->person_entity_id,
                'person_name'      => $a->person?->name,
                'job_profile_id'   => $a->job_profile_id,
                'job_profile_name' => $a->jobProfile?->name,
                'level'            => $a->jobProfile?->level,
                'percentage'       => $a->percentage,
                'is_primary'       => (bool) $a->is_primary,
                'valid_from'       => $a->valid_from?->toDateString(),
                'valid_to'         => $a->valid_to?->toDateString(),
                'note'             => $a->note,
            ])->values()->toArray();

            return ToolResult::success([
                'data'         => $items,
                'pagination'   => $result['pagination'] ?? null,
                'team_id'      => $resolved['team_id'],
                'root_team_id' => $rootTeamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Person-JobProfile-Zuweisungen: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'read',
            'tags'          => ['organization', 'person_job_profiles', 'lookup'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
