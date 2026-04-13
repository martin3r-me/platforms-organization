<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationProcessStepEntity;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class DeleteProcessStepEntityTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_step_entities.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/process-step-entities/{id} - Entfernt eine Step-Entity-Zuordnung (soft delete).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'                => ['type' => 'integer'],
                'process_step_entity_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH.'],
            ],
            'required' => ['process_step_entity_id'],
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
                'process_step_entity_id',
                OrganizationProcessStepEntity::class,
                'NOT_FOUND',
                'Step-Entity-Zuordnung nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationProcessStepEntity $stepEntity */
            $stepEntity = $found['model'];
            if ((int) $stepEntity->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Step-Entity-Zuordnung gehört nicht zum Root/Elterteam.');
            }

            $stepEntity->delete();

            return ToolResult::success([
                'id'      => $stepEntity->id,
                'message' => 'Step-Entity-Zuordnung gelöscht (soft delete).',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen der Step-Entity-Zuordnung: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'process_step_entities', 'delete'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
