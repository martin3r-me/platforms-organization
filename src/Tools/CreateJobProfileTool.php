<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationJobProfile;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class CreateJobProfileTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.job_profiles.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/job-profiles - Erstellt ein JobProfile (wiederverwendbares Stellenprofil-Template) im Root/Elterteam.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'          => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: Team aus Kontext.'],
                'name'             => ['type' => 'string', 'description' => 'ERFORDERLICH: Name des JobProfiles (z.B. "Senior Backend Engineer").'],
                'description'      => ['type' => 'string', 'description' => 'Optional: Kurzbeschreibung.'],
                'content'          => ['type' => 'string', 'description' => 'Optional: Ausführliches Profil als Markdown.'],
                'level'            => ['type' => 'string', 'description' => 'Optional: junior/mid/senior/lead/principal.'],
                'purpose'          => ['type' => 'string', 'description' => 'Optional: Rollenzweck — warum existiert diese Rolle?'],
                'job_family'       => ['type' => 'string', 'description' => 'Optional: Laufbahn/Kategorie (z.B. Engineering, Operations, Sales).'],
                'skills'           => ['type' => 'array', 'description' => 'Optional: Strukturierte Skills. Format: [{"name": "PHP", "level": "basic|advanced|expert", "category": "technical|methodical|domain"}]'],
                'responsibilities' => ['type' => 'array', 'description' => 'Optional: Verantwortungen. Format: [{"name": "Code Reviews", "is_core": true}]'],
                'requirements'     => ['type' => 'array', 'description' => 'Optional: Qualifikationen. Format: [{"name": "BSc Informatik", "type": "degree|certification|experience", "required": true}]'],
                'soft_skills'      => ['type' => 'array', 'description' => 'Optional: Soft Skills. Format: [{"name": "Teamfähigkeit", "level": "basic|advanced|expert"}]'],
                'kpis'             => ['type' => 'array', 'description' => 'Optional: Bewertungskriterien. Format: [{"name": "Code Quality", "description": "..."}]'],
                'status'           => ['type' => 'string', 'description' => 'Optional: active/archived/draft. Default: active.'],
                'effective_from'   => ['type' => 'string', 'description' => 'Optional: Gültig ab (YYYY-MM-DD).'],
                'effective_to'     => ['type' => 'string', 'description' => 'Optional: Gültig bis (YYYY-MM-DD).'],
            ],
            'required' => ['name'],
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

            $name = trim((string) ($arguments['name'] ?? ''));
            if ($name === '') {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich.');
            }

            $jp = OrganizationJobProfile::create([
                'team_id'          => $rootTeamId,
                'user_id'          => $context->user?->id,
                'name'             => $name,
                'description'      => ($arguments['description'] ?? null) ?: null,
                'purpose'          => ($arguments['purpose'] ?? null) ?: null,
                'job_family'       => ($arguments['job_family'] ?? null) ?: null,
                'content'          => ($arguments['content'] ?? null) ?: null,
                'level'            => ($arguments['level'] ?? null) ?: null,
                'skills'           => (isset($arguments['skills']) && is_array($arguments['skills'])) ? $arguments['skills'] : null,
                'responsibilities' => (isset($arguments['responsibilities']) && is_array($arguments['responsibilities'])) ? $arguments['responsibilities'] : null,
                'requirements'     => (isset($arguments['requirements']) && is_array($arguments['requirements'])) ? $arguments['requirements'] : null,
                'soft_skills'      => (isset($arguments['soft_skills']) && is_array($arguments['soft_skills'])) ? $arguments['soft_skills'] : null,
                'kpis'             => (isset($arguments['kpis']) && is_array($arguments['kpis'])) ? $arguments['kpis'] : null,
                'status'           => ($arguments['status'] ?? 'active'),
                'effective_from'   => ($arguments['effective_from'] ?? null) ?: null,
                'effective_to'     => ($arguments['effective_to'] ?? null) ?: null,
            ]);

            return ToolResult::success([
                'id'      => $jp->id,
                'uuid'    => $jp->uuid,
                'name'    => $jp->name,
                'level'   => $jp->level,
                'status'  => $jp->status,
                'team_id' => $jp->team_id,
                'message' => 'JobProfile erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des JobProfiles: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'job_profiles', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => false,
        ];
    }
}
