<?php

namespace Platform\Organization\Tools;

use Illuminate\Validation\ValidationException;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationPersonJobProfile;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class UpdatePersonJobProfileTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.person_job_profiles.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/person-job-profiles/{id} - Aktualisiert eine Person-JobProfile-Zuweisung.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'                 => ['type' => 'integer'],
                'person_job_profile_id'   => ['type' => 'integer', 'description' => 'ERFORDERLICH: ID der Zuweisung.'],
                'percentage'              => ['type' => 'integer'],
                'is_primary'              => ['type' => 'boolean'],
                'valid_from'              => ['type' => 'string'],
                'valid_to'                => ['type' => 'string'],
                'note'                    => ['type' => 'string'],
            ],
            'required' => ['person_job_profile_id'],
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

            $update = [];
            if (array_key_exists('percentage', $arguments)) {
                $p = (int) $arguments['percentage'];
                if ($p < 0 || $p > 100) {
                    return ToolResult::error('VALIDATION_ERROR', 'percentage muss zwischen 0 und 100 liegen.');
                }
                $update['percentage'] = $p;
            }
            if (array_key_exists('is_primary', $arguments)) {
                $update['is_primary'] = (bool) $arguments['is_primary'];
            }
            foreach (['valid_from', 'valid_to', 'note'] as $field) {
                if (array_key_exists($field, $arguments)) {
                    $val = (string) ($arguments[$field] ?? '');
                    $update[$field] = $val === '' ? null : $val;
                }
            }

            if (! empty($update)) {
                $a->update($update);
            }
            $a->refresh();

            return ToolResult::success([
                'id'               => $a->id,
                'person_entity_id' => $a->person_entity_id,
                'job_profile_id'   => $a->job_profile_id,
                'percentage'       => $a->percentage,
                'is_primary'       => (bool) $a->is_primary,
                'message'          => 'Zuweisung erfolgreich aktualisiert.',
            ]);
        } catch (ValidationException $e) {
            return ToolResult::error('VALIDATION_ERROR', collect($e->errors())->flatten()->first() ?? $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'person_job_profiles', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
