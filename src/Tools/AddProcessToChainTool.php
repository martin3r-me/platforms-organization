<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Enums\ChainMemberRole;
use Platform\Organization\Models\OrganizationProcess;
use Platform\Organization\Models\OrganizationProcessChain;
use Platform\Organization\Models\OrganizationProcessChainMember;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class AddProcessToChainTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_chain_members.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/process-chain-members - Fügt einen Prozess als Mitglied einer Kette hinzu. role: entry | middle | exit | optional.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'          => ['type' => 'integer'],
                'process_chain_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: Kette.'],
                'process_id'       => ['type' => 'integer', 'description' => 'ERFORDERLICH: Prozess.'],
                'position'         => ['type' => 'integer', 'description' => 'Optional: wenn leer, wird max(position)+1 verwendet.'],
                'role'             => ['type' => 'string', 'description' => 'Optional: entry | middle | exit | optional. Default: middle.'],
                'is_required'      => ['type' => 'boolean', 'description' => 'Optional: Default true.'],
                'notes'            => ['type' => 'string'],
                'metadata'         => ['type' => 'object'],
            ],
            'required' => ['process_chain_id', 'process_id'],
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

            $chainId = (int) ($arguments['process_chain_id'] ?? 0);
            $processId = (int) ($arguments['process_id'] ?? 0);
            if ($chainId <= 0 || $processId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'process_chain_id und process_id sind erforderlich.');
            }

            $chain = OrganizationProcessChain::find($chainId);
            if (! $chain || (int) $chain->team_id !== $rootTeamId) {
                return ToolResult::error('NOT_FOUND', 'Prozesskette nicht gefunden (oder falsches Team).');
            }
            $process = OrganizationProcess::find($processId);
            if (! $process || (int) $process->team_id !== $rootTeamId) {
                return ToolResult::error('NOT_FOUND', 'Prozess nicht gefunden (oder falsches Team).');
            }

            // Uniqueness: chain_id + process_id
            $existing = OrganizationProcessChainMember::where('chain_id', $chainId)
                ->where('process_id', $processId)
                ->first();
            if ($existing) {
                return ToolResult::error('VALIDATION_ERROR', 'Dieser Prozess ist bereits Mitglied der Kette.');
            }

            $role = (string) ($arguments['role'] ?? ChainMemberRole::Middle->value);
            if (! in_array($role, ChainMemberRole::values(), true)) {
                return ToolResult::error('VALIDATION_ERROR', 'Ungültige role. Erlaubt: '.implode(', ', ChainMemberRole::values()));
            }

            $position = $arguments['position'] ?? null;
            if ($position === null || $position === '') {
                $max = OrganizationProcessChainMember::where('chain_id', $chainId)->max('position') ?? 0;
                $position = $max + 1;
            }

            $member = OrganizationProcessChainMember::create([
                'team_id'     => $rootTeamId,
                'user_id'     => $context->user?->id,
                'chain_id'    => $chainId,
                'process_id'  => $processId,
                'position'    => (int) $position,
                'role'        => $role,
                'is_required' => $arguments['is_required'] ?? true,
                'notes'       => ($arguments['notes'] ?? null) ?: null,
                'metadata'    => $arguments['metadata'] ?? null,
            ]);

            // Model observer (saved) already invokes $chain->syncEndpointsFromMembers()

            return ToolResult::success([
                'id'               => $member->id,
                'uuid'             => $member->uuid,
                'chain_id'         => $member->chain_id,
                'process_id'       => $member->process_id,
                'position'         => $member->position,
                'role'             => $member->role instanceof \BackedEnum ? $member->role->value : $member->role,
                'is_required'      => $member->is_required,
                'team_id'          => $member->team_id,
                'message'          => 'Prozess erfolgreich zur Kette hinzugefügt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Hinzufügen zur Kette: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'process_chains', 'members', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => false,
        ];
    }
}
