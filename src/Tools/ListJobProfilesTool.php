<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationJobProfile;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ListJobProfilesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.job_profiles.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/job-profiles - Listet JobProfile-Templates (Stellenbeschreibungen) im Root/Elterteam. Unterstützt filters/search/sort/limit/offset.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'status', 'level', 'job_family']),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID (wird auf Root/Elterteam aufgelöst). Default: Team aus Kontext.',
                    ],
                    'status' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach status (active/archived/draft). Default: active.',
                    ],
                    'level' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach level (junior/mid/senior/lead/principal).',
                    ],
                    'job_family' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach job_family (z.B. Engineering, Operations, Sales).',
                    ],
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

            $q = OrganizationJobProfile::query()->where('team_id', $rootTeamId);

            $status = $arguments['status'] ?? 'active';
            if ($status !== null && $status !== '') {
                $q->where('status', $status);
            }
            if (! empty($arguments['level'])) {
                $q->where('level', (string) $arguments['level']);
            }
            if (! empty($arguments['job_family'])) {
                $q->where('job_family', (string) $arguments['job_family']);
            }

            $this->applyStandardFilters($q, $arguments, ['team_id', 'status', 'level', 'job_family', 'created_at']);
            $this->applyStandardSearch($q, $arguments, ['name', 'description', 'content']);
            $this->applyStandardSort($q, $arguments, ['name', 'level', 'status', 'job_family', 'id', 'created_at'], 'name', 'asc');

            $result = $this->applyStandardPaginationResult($q, $arguments);
            $items = $result['data']->map(fn (OrganizationJobProfile $jp) => [
                'id'               => $jp->id,
                'uuid'             => $jp->uuid,
                'name'             => $jp->name,
                'description'      => $jp->description,
                'purpose'          => $jp->purpose,
                'job_family'       => $jp->job_family,
                'level'            => $jp->level,
                'status'           => $jp->status,
                'skills'           => $jp->skills,
                'responsibilities' => $jp->responsibilities,
                'requirements'     => $jp->requirements,
                'soft_skills'      => $jp->soft_skills,
                'kpis'             => $jp->kpis,
                'effective_from'   => $jp->effective_from?->toDateString(),
                'effective_to'     => $jp->effective_to?->toDateString(),
                'team_id'          => $jp->team_id,
            ])->values()->toArray();

            return ToolResult::success([
                'data'         => $items,
                'pagination'   => $result['pagination'] ?? null,
                'team_id'      => $resolved['team_id'],
                'root_team_id' => $rootTeamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der JobProfiles: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'read',
            'tags'          => ['organization', 'job_profiles', 'lookup'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
