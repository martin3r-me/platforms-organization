<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationProcessStep;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class UpdateProcessStepTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_steps.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/process-steps/{id} - Aktualisiert einen Prozess-Schritt.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'                 => ['type' => 'integer'],
                'process_step_id'         => ['type' => 'integer', 'description' => 'ERFORDERLICH.'],
                'name'                    => ['type' => 'string'],
                'description'             => ['type' => 'string', 'description' => '"" zum Leeren.'],
                'position'                => ['type' => 'integer'],
                'step_type'               => ['type' => 'string'],
                'duration_target_minutes' => ['type' => 'integer', 'description' => '0 oder null zum Leeren.'],
                'wait_target_minutes'     => ['type' => 'integer', 'description' => '0 oder null zum Leeren.'],
                'corefit_classification'  => ['type' => 'string', 'description' => '"" zum Leeren.'],
                'is_active'               => ['type' => 'boolean'],
                'metadata'                => ['type' => 'object'],
            ],
            'required' => ['process_step_id'],
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
                'process_step_id',
                OrganizationProcessStep::class,
                'NOT_FOUND',
                'Prozess-Schritt nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationProcessStep $step */
            $step = $found['model'];
            if ((int) $step->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Prozess-Schritt gehört nicht zum Root/Elterteam.');
            }

            $update = [];
            if (array_key_exists('name', $arguments)) {
                $val = trim((string) ($arguments['name'] ?? ''));
                if ($val === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'name darf nicht leer sein.');
                }
                $update['name'] = $val;
            }
            if (array_key_exists('description', $arguments)) {
                $val = (string) ($arguments['description'] ?? '');
                $update['description'] = $val === '' ? null : $val;
            }
            if (array_key_exists('position', $arguments)) {
                $update['position'] = (int) $arguments['position'];
            }
            if (array_key_exists('step_type', $arguments)) {
                $update['step_type'] = (string) $arguments['step_type'];
            }
            foreach (['duration_target_minutes', 'wait_target_minutes'] as $field) {
                if (array_key_exists($field, $arguments)) {
                    $val = $arguments[$field];
                    $update[$field] = ($val === null || $val === '' || (int) $val === 0) ? null : (int) $val;
                }
            }
            if (array_key_exists('corefit_classification', $arguments)) {
                $val = (string) ($arguments['corefit_classification'] ?? '');
                $update['corefit_classification'] = $val === '' ? null : $val;
            }
            if (array_key_exists('is_active', $arguments)) {
                $update['is_active'] = (bool) $arguments['is_active'];
            }
            if (array_key_exists('metadata', $arguments)) {
                $update['metadata'] = $arguments['metadata'];
            }

            if (! empty($update)) {
                $step->update($update);
            }
            $step->refresh();

            return ToolResult::success([
                'id'         => $step->id,
                'uuid'       => $step->uuid,
                'process_id' => $step->process_id,
                'name'       => $step->name,
                'position'   => $step->position,
                'step_type'  => $step->step_type,
                'team_id'    => $step->team_id,
                'message'    => 'Prozess-Schritt erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Prozess-Schritts: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'process_steps', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
