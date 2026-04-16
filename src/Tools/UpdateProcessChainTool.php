<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Enums\ProcessChainType;
use Platform\Organization\Models\OrganizationProcessChain;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class UpdateProcessChainTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_chains.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/process-chains/{id} - Aktualisiert eine Prozesskette.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'          => ['type' => 'integer'],
                'process_chain_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH.'],
                'name'             => ['type' => 'string'],
                'code'             => ['type' => 'string', 'description' => '"" zum Leeren.'],
                'description'      => ['type' => 'string', 'description' => '"" zum Leeren.'],
                'chain_type'       => ['type' => 'string', 'description' => 'value_stream | end_to_end | sub_chain | ad_hoc.'],
                'is_active'        => ['type' => 'boolean'],
                'is_auto_detected' => ['type' => 'boolean'],
                'metadata'         => ['type' => 'object'],
            ],
            'required' => ['process_chain_id'],
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
                'process_chain_id',
                OrganizationProcessChain::class,
                'NOT_FOUND',
                'Prozesskette nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationProcessChain $chain */
            $chain = $found['model'];
            if ((int) $chain->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Prozesskette gehört nicht zum Root/Elterteam.');
            }

            $update = [];
            if (array_key_exists('name', $arguments)) {
                $val = trim((string) ($arguments['name'] ?? ''));
                if ($val === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'name darf nicht leer sein.');
                }
                $update['name'] = $val;
            }
            if (array_key_exists('code', $arguments)) {
                $val = (string) ($arguments['code'] ?? '');
                $newCode = $val === '' ? null : $val;
                if ($newCode !== null && $newCode !== $chain->code) {
                    $exists = OrganizationProcessChain::where('team_id', $rootTeamId)
                        ->where('code', $newCode)
                        ->where('id', '!=', $chain->id)
                        ->exists();
                    if ($exists) {
                        return ToolResult::error('VALIDATION_ERROR', 'Eine Kette mit diesem code existiert bereits im Team.');
                    }
                }
                $update['code'] = $newCode;
            }
            if (array_key_exists('description', $arguments)) {
                $val = (string) ($arguments['description'] ?? '');
                $update['description'] = $val === '' ? null : $val;
            }
            if (array_key_exists('chain_type', $arguments)) {
                $val = (string) $arguments['chain_type'];
                if (! in_array($val, ProcessChainType::values(), true)) {
                    return ToolResult::error('VALIDATION_ERROR', 'Ungültiger chain_type. Erlaubt: '.implode(', ', ProcessChainType::values()));
                }
                $update['chain_type'] = $val;
            }
            if (array_key_exists('is_active', $arguments)) {
                $update['is_active'] = (bool) $arguments['is_active'];
            }
            if (array_key_exists('is_auto_detected', $arguments)) {
                $update['is_auto_detected'] = (bool) $arguments['is_auto_detected'];
            }
            if (array_key_exists('metadata', $arguments)) {
                $update['metadata'] = $arguments['metadata'];
            }

            if (! empty($update)) {
                $chain->update($update);
            }
            $chain->refresh();

            return ToolResult::success([
                'id'         => $chain->id,
                'uuid'       => $chain->uuid,
                'name'       => $chain->name,
                'code'       => $chain->code,
                'chain_type' => $chain->chain_type instanceof \BackedEnum ? $chain->chain_type->value : $chain->chain_type,
                'team_id'    => $chain->team_id,
                'message'    => 'Prozesskette erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren der Prozesskette: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'process_chains', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
