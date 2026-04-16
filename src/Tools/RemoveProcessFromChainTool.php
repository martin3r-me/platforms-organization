<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationProcessChainMember;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class RemoveProcessFromChainTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_chain_members.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/process-chain-members - Entfernt einen Prozess aus einer Kette. Entweder process_chain_member_id ODER (process_chain_id + process_id).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'                 => ['type' => 'integer'],
                'process_chain_member_id' => ['type' => 'integer', 'description' => 'Optional: direkte Member-ID.'],
                'process_chain_id'        => ['type' => 'integer', 'description' => 'Alternative: Kette + Prozess angeben.'],
                'process_id'              => ['type' => 'integer', 'description' => 'Alternative: Kette + Prozess angeben.'],
            ],
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

            $memberId = (int) ($arguments['process_chain_member_id'] ?? 0);
            $chainId = (int) ($arguments['process_chain_id'] ?? 0);
            $processId = (int) ($arguments['process_id'] ?? 0);

            $member = null;
            if ($memberId > 0) {
                $member = OrganizationProcessChainMember::find($memberId);
            } elseif ($chainId > 0 && $processId > 0) {
                $member = OrganizationProcessChainMember::where('chain_id', $chainId)
                    ->where('process_id', $processId)
                    ->first();
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'Entweder process_chain_member_id ODER (process_chain_id + process_id) angeben.');
            }

            if (! $member) {
                return ToolResult::error('NOT_FOUND', 'Chain-Mitglied nicht gefunden.');
            }
            if ((int) $member->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Chain-Mitglied gehört nicht zum Root/Elterteam.');
            }

            $member->delete();

            // Model observer (deleted) invokes $chain->syncEndpointsFromMembers()

            return ToolResult::success([
                'id'       => $member->id,
                'chain_id' => $member->chain_id,
                'process_id' => $member->process_id,
                'message'  => 'Prozess aus Kette entfernt (soft delete).',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Entfernen aus der Kette: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'process_chains', 'members', 'delete'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
