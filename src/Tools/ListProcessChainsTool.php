<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationProcessChain;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ListProcessChainsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_chains.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/process-chains - Listet Prozessketten (Chains). Filter: chain_type (value_stream | end_to_end | sub_chain | ad_hoc), is_active, is_auto_detected.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'chain_type', 'is_active', 'is_auto_detected']),
            [
                'properties' => [
                    'team_id'          => ['type' => 'integer'],
                    'chain_type'       => ['type' => 'string', 'description' => 'Optional: value_stream | end_to_end | sub_chain | ad_hoc.'],
                    'is_active'        => ['type' => 'boolean'],
                    'is_auto_detected' => ['type' => 'boolean'],
                    'with_members'     => ['type' => 'boolean', 'description' => 'Optional: Mitglieder (Prozesse) mit zurückgeben. Default false.'],
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

            $q = OrganizationProcessChain::query()->where('team_id', $rootTeamId);

            if (! empty($arguments['chain_type'])) {
                $q->where('chain_type', (string) $arguments['chain_type']);
            }
            if (array_key_exists('is_active', $arguments)) {
                $q->where('is_active', (bool) $arguments['is_active']);
            }
            if (array_key_exists('is_auto_detected', $arguments)) {
                $q->where('is_auto_detected', (bool) $arguments['is_auto_detected']);
            }

            $this->applyStandardFilters($q, $arguments, ['team_id', 'chain_type', 'is_active', 'is_auto_detected', 'created_at']);
            $this->applyStandardSearch($q, $arguments, ['name', 'code', 'description']);
            $this->applyStandardSort($q, $arguments, ['name', 'code', 'chain_type', 'id', 'created_at'], 'name', 'asc');

            $withMembers = (bool) ($arguments['with_members'] ?? false);
            if ($withMembers) {
                $q->with(['members.process:id,name,code']);
            }

            $result = $this->applyStandardPaginationResult($q, $arguments);
            $items = $result['data']->map(function (OrganizationProcessChain $c) use ($withMembers) {
                $row = [
                    'id'               => $c->id,
                    'uuid'             => $c->uuid,
                    'name'             => $c->name,
                    'code'             => $c->code,
                    'description'      => $c->description,
                    'chain_type'       => $c->chain_type instanceof \BackedEnum ? $c->chain_type->value : $c->chain_type,
                    'is_active'        => $c->is_active,
                    'is_auto_detected' => $c->is_auto_detected,
                    'entry_process_id' => $c->entry_process_id,
                    'exit_process_id'  => $c->exit_process_id,
                    'team_id'          => $c->team_id,
                ];
                if ($withMembers) {
                    $row['members'] = $c->members->map(fn ($m) => [
                        'id'         => $m->id,
                        'process_id' => $m->process_id,
                        'process'    => $m->process ? ['id' => $m->process->id, 'name' => $m->process->name, 'code' => $m->process->code] : null,
                        'position'   => $m->position,
                        'role'       => $m->role instanceof \BackedEnum ? $m->role->value : $m->role,
                        'is_required'=> $m->is_required,
                    ])->values()->toArray();
                }
                return $row;
            })->values()->toArray();

            return ToolResult::success([
                'data'         => $items,
                'pagination'   => $result['pagination'] ?? null,
                'team_id'      => $resolved['team_id'],
                'root_team_id' => $rootTeamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Prozessketten: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'read',
            'tags'          => ['organization', 'process_chains', 'lookup'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
