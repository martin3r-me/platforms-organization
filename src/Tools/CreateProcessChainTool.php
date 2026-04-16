<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Enums\ProcessChainType;
use Platform\Organization\Models\OrganizationProcessChain;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class CreateProcessChainTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_chains.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/process-chains - Erstellt eine Prozesskette. chain_type: value_stream | end_to_end | sub_chain | ad_hoc.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'          => ['type' => 'integer'],
                'name'             => ['type' => 'string', 'description' => 'ERFORDERLICH.'],
                'code'             => ['type' => 'string', 'description' => 'Optional: eindeutig pro Team.'],
                'description'      => ['type' => 'string'],
                'chain_type'       => ['type' => 'string', 'description' => 'Optional: value_stream | end_to_end | sub_chain | ad_hoc. Default: ad_hoc.'],
                'is_active'        => ['type' => 'boolean', 'description' => 'Optional: Default true.'],
                'is_auto_detected' => ['type' => 'boolean', 'description' => 'Optional: Default false.'],
                'metadata'         => ['type' => 'object'],
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

            $chainType = (string) ($arguments['chain_type'] ?? ProcessChainType::AdHoc->value);
            if (! in_array($chainType, ProcessChainType::values(), true)) {
                return ToolResult::error('VALIDATION_ERROR', 'Ungültiger chain_type. Erlaubt: '.implode(', ', ProcessChainType::values()));
            }

            $code = ($arguments['code'] ?? null) ?: null;
            if ($code !== null) {
                $exists = OrganizationProcessChain::where('team_id', $rootTeamId)->where('code', $code)->exists();
                if ($exists) {
                    return ToolResult::error('VALIDATION_ERROR', 'Eine Kette mit diesem code existiert bereits im Team.');
                }
            }

            $chain = OrganizationProcessChain::create([
                'team_id'          => $rootTeamId,
                'user_id'          => $context->user?->id,
                'name'             => $name,
                'code'             => $code,
                'description'      => ($arguments['description'] ?? null) ?: null,
                'chain_type'       => $chainType,
                'is_active'        => $arguments['is_active'] ?? true,
                'is_auto_detected' => $arguments['is_auto_detected'] ?? false,
                'metadata'         => $arguments['metadata'] ?? null,
            ]);

            return ToolResult::success([
                'id'         => $chain->id,
                'uuid'       => $chain->uuid,
                'name'       => $chain->name,
                'code'       => $chain->code,
                'chain_type' => $chain->chain_type instanceof \BackedEnum ? $chain->chain_type->value : $chain->chain_type,
                'team_id'    => $chain->team_id,
                'message'    => 'Prozesskette erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen der Prozesskette: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'process_chains', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => false,
        ];
    }
}
