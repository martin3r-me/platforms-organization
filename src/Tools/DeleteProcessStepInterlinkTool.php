<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationProcessStepInterlink;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class DeleteProcessStepInterlinkTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_step_interlinks.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/process-step-interlinks/{id} - Entfernt eine Step-Interlink-Zuordnung (soft delete).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'                   => ['type' => 'integer'],
                'process_step_interlink_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH.'],
            ],
            'required' => ['process_step_interlink_id'],
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
                'process_step_interlink_id',
                OrganizationProcessStepInterlink::class,
                'NOT_FOUND',
                'Step-Interlink-Zuordnung nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationProcessStepInterlink $stepInterlink */
            $stepInterlink = $found['model'];
            if ((int) $stepInterlink->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Step-Interlink-Zuordnung gehört nicht zum Root/Elterteam.');
            }

            $stepInterlink->delete();

            return ToolResult::success([
                'id'      => $stepInterlink->id,
                'message' => 'Step-Interlink-Zuordnung gelöscht (soft delete).',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen der Step-Interlink-Zuordnung: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'process_step_interlinks', 'delete'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
