<?php

namespace Platform\Organization\Tools;

use Illuminate\Validation\ValidationException;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationPersonJobProfile;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class CreatePersonJobProfileTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.person_job_profiles.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/person-job-profiles - Weist einer Person ein JobProfile mit Prozentsatz und optionalem Zeitraum zu. person_entity_id muss EntityType "person" sein.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'          => ['type' => 'integer'],
                'person_entity_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: Entity-ID einer Person.'],
                'job_profile_id'   => ['type' => 'integer', 'description' => 'ERFORDERLICH: ID des JobProfiles.'],
                'percentage'       => ['type' => 'integer', 'description' => 'Optional: 0–100. Default: 100.'],
                'is_primary'       => ['type' => 'boolean', 'description' => 'Optional: Ist das Hauptprofil der Person? Default: false.'],
                'valid_from'       => ['type' => 'string', 'description' => 'Optional: YYYY-MM-DD.'],
                'valid_to'         => ['type' => 'string', 'description' => 'Optional: YYYY-MM-DD.'],
                'note'             => ['type' => 'string', 'description' => 'Optional: Notiz.'],
            ],
            'required' => ['person_entity_id', 'job_profile_id'],
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

            $personId = (int) ($arguments['person_entity_id'] ?? 0);
            $jobProfileId = (int) ($arguments['job_profile_id'] ?? 0);
            if ($personId <= 0 || $jobProfileId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'person_entity_id und job_profile_id sind erforderlich.');
            }

            $percentage = (int) ($arguments['percentage'] ?? 100);
            if ($percentage < 0 || $percentage > 100) {
                return ToolResult::error('VALIDATION_ERROR', 'percentage muss zwischen 0 und 100 liegen.');
            }

            $assignment = OrganizationPersonJobProfile::create([
                'team_id'          => $rootTeamId,
                'person_entity_id' => $personId,
                'job_profile_id'   => $jobProfileId,
                'percentage'       => $percentage,
                'is_primary'       => (bool) ($arguments['is_primary'] ?? false),
                'valid_from'       => ($arguments['valid_from'] ?? null) ?: null,
                'valid_to'         => ($arguments['valid_to'] ?? null) ?: null,
                'note'             => ($arguments['note'] ?? null) ?: null,
            ]);

            return ToolResult::success([
                'id'               => $assignment->id,
                'person_entity_id' => $assignment->person_entity_id,
                'job_profile_id'   => $assignment->job_profile_id,
                'percentage'       => $assignment->percentage,
                'is_primary'       => (bool) $assignment->is_primary,
                'message'          => 'JobProfile erfolgreich der Person zugewiesen.',
            ]);
        } catch (ValidationException $e) {
            return ToolResult::error('VALIDATION_ERROR', collect($e->errors())->flatten()->first() ?? $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler bei der Zuweisung: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'person_job_profiles', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => false,
        ];
    }
}
