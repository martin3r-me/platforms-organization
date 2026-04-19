<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationJobProfile;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class UpdateJobProfileTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.job_profiles.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/job-profiles/{id} - Aktualisiert ein JobProfile.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'          => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: Team aus Kontext.'],
                'job_profile_id'   => ['type' => 'integer', 'description' => 'ID des JobProfiles (ERFORDERLICH).'],
                'name'             => ['type' => 'string'],
                'description'      => ['type' => 'string', 'description' => '"" zum Leeren.'],
                'purpose'          => ['type' => 'string', 'description' => 'Rollenzweck. "" zum Leeren.'],
                'job_family'       => ['type' => 'string', 'description' => 'Laufbahn/Kategorie. "" zum Leeren.'],
                'content'          => ['type' => 'string', 'description' => '"" zum Leeren.'],
                'level'            => ['type' => 'string'],
                'skills'           => ['type' => 'array', 'description' => 'Strukturierte Skills: [{"name": "...", "level": "basic|advanced|expert", "category": "technical|methodical|domain"}]'],
                'responsibilities' => ['type' => 'array', 'description' => 'Verantwortungen: [{"name": "...", "is_core": true}]'],
                'requirements'     => ['type' => 'array', 'description' => 'Qualifikationen: [{"name": "...", "type": "degree|certification|experience", "required": true}]'],
                'soft_skills'      => ['type' => 'array', 'description' => 'Soft Skills: [{"name": "...", "level": "basic|advanced|expert"}]'],
                'kpis'             => ['type' => 'array', 'description' => 'Bewertungskriterien: [{"name": "...", "description": "..."}]'],
                'status'           => ['type' => 'string'],
                'effective_from'   => ['type' => 'string'],
                'effective_to'     => ['type' => 'string'],
            ],
            'required' => ['job_profile_id'],
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
                $arguments,
                $context,
                'job_profile_id',
                OrganizationJobProfile::class,
                'NOT_FOUND',
                'JobProfile nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationJobProfile $jp */
            $jp = $found['model'];
            if ((int) $jp->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'JobProfile gehört nicht zum Root/Elterteam des angegebenen Teams.');
            }

            $update = [];
            foreach (['name', 'level', 'status', 'job_family'] as $field) {
                if (array_key_exists($field, $arguments)) {
                    $val = trim((string) ($arguments[$field] ?? ''));
                    if ($field === 'name' && $val === '') {
                        return ToolResult::error('VALIDATION_ERROR', 'name darf nicht leer sein.');
                    }
                    $update[$field] = $val === '' ? null : $val;
                }
            }
            foreach (['description', 'content', 'purpose'] as $field) {
                if (array_key_exists($field, $arguments)) {
                    $val = (string) ($arguments[$field] ?? '');
                    $update[$field] = $val === '' ? null : $val;
                }
            }
            foreach (['skills', 'responsibilities', 'requirements', 'soft_skills', 'kpis'] as $field) {
                if (array_key_exists($field, $arguments)) {
                    $update[$field] = is_array($arguments[$field]) ? $arguments[$field] : null;
                }
            }
            foreach (['effective_from', 'effective_to'] as $field) {
                if (array_key_exists($field, $arguments)) {
                    $val = (string) ($arguments[$field] ?? '');
                    $update[$field] = $val === '' ? null : $val;
                }
            }

            if (! empty($update)) {
                $jp->update($update);
            }
            $jp->refresh();

            return ToolResult::success([
                'id'      => $jp->id,
                'uuid'    => $jp->uuid,
                'name'    => $jp->name,
                'level'   => $jp->level,
                'status'  => $jp->status,
                'team_id' => $jp->team_id,
                'message' => 'JobProfile erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des JobProfiles: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'job_profiles', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
