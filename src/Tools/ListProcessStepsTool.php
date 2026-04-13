<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationProcessStep;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ListProcessStepsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_steps.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/process-steps - Listet Prozess-Schritte. Filter: process_id (empfohlen), step_type, is_active, corefit_classification.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'process_id', 'step_type', 'is_active', 'corefit_classification']),
            [
                'properties' => [
                    'team_id'                => ['type' => 'integer'],
                    'process_id'             => ['type' => 'integer', 'description' => 'EMPFOHLEN: Filter nach Prozess.'],
                    'step_type'              => ['type' => 'string', 'description' => 'Optional: action | gateway | wait | subprocess.'],
                    'is_active'              => ['type' => 'boolean'],
                    'corefit_classification' => ['type' => 'string', 'description' => 'Optional: green | yellow | red.'],
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

            $q = OrganizationProcessStep::query()->where('team_id', $rootTeamId);

            if (! empty($arguments['process_id'])) {
                $q->where('process_id', (int) $arguments['process_id']);
            }
            if (! empty($arguments['step_type'])) {
                $q->where('step_type', (string) $arguments['step_type']);
            }
            if (array_key_exists('is_active', $arguments)) {
                $q->where('is_active', (bool) $arguments['is_active']);
            }
            if (! empty($arguments['corefit_classification'])) {
                $q->where('corefit_classification', (string) $arguments['corefit_classification']);
            }

            $this->applyStandardFilters($q, $arguments, ['team_id', 'process_id', 'step_type', 'is_active', 'corefit_classification', 'created_at']);
            $this->applyStandardSearch($q, $arguments, ['name', 'description']);
            $this->applyStandardSort($q, $arguments, ['position', 'name', 'id', 'created_at'], 'position', 'asc');

            $result = $this->applyStandardPaginationResult($q, $arguments);
            $items = $result['data']->map(fn (OrganizationProcessStep $s) => [
                'id'                      => $s->id,
                'uuid'                    => $s->uuid,
                'process_id'              => $s->process_id,
                'name'                    => $s->name,
                'description'             => $s->description,
                'position'                => $s->position,
                'step_type'               => $s->step_type,
                'duration_target_minutes' => $s->duration_target_minutes,
                'wait_target_minutes'     => $s->wait_target_minutes,
                'corefit_classification'  => $s->corefit_classification,
                'is_active'               => $s->is_active,
                'team_id'                 => $s->team_id,
            ])->values()->toArray();

            return ToolResult::success([
                'data'         => $items,
                'pagination'   => $result['pagination'] ?? null,
                'team_id'      => $resolved['team_id'],
                'root_team_id' => $rootTeamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Prozess-Schritte: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'read',
            'tags'          => ['organization', 'process_steps', 'lookup'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
