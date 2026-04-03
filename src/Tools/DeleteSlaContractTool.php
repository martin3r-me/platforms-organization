<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationSlaContract;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class DeleteSlaContractTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.sla_contracts.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/sla_contracts/{id} - Löscht einen SLA-Vertrag (soft delete).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (wird auf Root/Elterteam aufgelöst). Default: Team aus Kontext.',
                ],
                'sla_contract_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID des SLA-Vertrags.',
                ],
            ],
            'required' => ['sla_contract_id'],
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
                'sla_contract_id',
                OrganizationSlaContract::class,
                'NOT_FOUND',
                'SLA-Vertrag nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationSlaContract $sla */
            $sla = $found['model'];

            if ((int) $sla->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'SLA-Vertrag gehört nicht zum Root/Elterteam des angegebenen Teams.');
            }

            $sla->delete();

            return ToolResult::success([
                'id' => $sla->id,
                'message' => 'SLA-Vertrag gelöscht (soft delete).',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen des SLA-Vertrags: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'sla', 'contracts', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
