<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Enums\ProcessFlowKind;
use Platform\Organization\Models\OrganizationProcessFlow;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class UpdateProcessFlowTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_flows.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/process-flows/{id} - Aktualisiert einen Prozess-Flow.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'              => ['type' => 'integer'],
                'process_flow_id'      => ['type' => 'integer', 'description' => 'ERFORDERLICH.'],
                'from_step_id'         => ['type' => 'integer'],
                'to_step_id'           => ['type' => 'integer'],
                'condition_label'      => ['type' => 'string', 'description' => '"" zum Leeren.'],
                'condition_expression' => ['type' => 'object', 'description' => 'null zum Leeren.'],
                'flow_kind'            => ['type' => 'string', 'description' => 'sequence | conditional | exception | loop_back | compensation.'],
                'priority'             => ['type' => 'integer', 'description' => '0-255.'],
                'is_default'           => ['type' => 'boolean'],
                'metadata'             => ['type' => 'object'],
            ],
            'required' => ['process_flow_id'],
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
                'process_flow_id',
                OrganizationProcessFlow::class,
                'NOT_FOUND',
                'Prozess-Flow nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationProcessFlow $flow */
            $flow = $found['model'];
            if ((int) $flow->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Prozess-Flow gehört nicht zum Root/Elterteam.');
            }

            $update = [];
            foreach (['from_step_id', 'to_step_id'] as $field) {
                if (array_key_exists($field, $arguments)) {
                    $val = (int) $arguments[$field];
                    if ($val <= 0) {
                        return ToolResult::error('VALIDATION_ERROR', $field.' muss eine gültige ID sein.');
                    }
                    $update[$field] = $val;
                }
            }
            if (array_key_exists('condition_label', $arguments)) {
                $val = (string) ($arguments['condition_label'] ?? '');
                $update['condition_label'] = $val === '' ? null : $val;
            }
            if (array_key_exists('condition_expression', $arguments)) {
                $update['condition_expression'] = $arguments['condition_expression'];
            }
            if (array_key_exists('flow_kind', $arguments)) {
                $val = (string) ($arguments['flow_kind'] ?? '');
                if ($val === '' || ! in_array($val, ProcessFlowKind::values(), true)) {
                    return ToolResult::error('VALIDATION_ERROR', 'Ungültiger flow_kind. Erlaubt: '.implode(', ', ProcessFlowKind::values()));
                }
                $update['flow_kind'] = $val;
            }
            if (array_key_exists('priority', $arguments)) {
                $val = (int) $arguments['priority'];
                if ($val < 0 || $val > 255) {
                    return ToolResult::error('VALIDATION_ERROR', 'priority muss zwischen 0 und 255 liegen.');
                }
                $update['priority'] = $val;
            }
            if (array_key_exists('is_default', $arguments)) {
                $update['is_default'] = (bool) $arguments['is_default'];
            }
            if (array_key_exists('metadata', $arguments)) {
                $update['metadata'] = $arguments['metadata'];
            }

            if (! empty($update)) {
                $flow->update($update);
            }
            $flow->refresh();

            return ToolResult::success([
                'id'           => $flow->id,
                'uuid'         => $flow->uuid,
                'process_id'   => $flow->process_id,
                'from_step_id' => $flow->from_step_id,
                'to_step_id'   => $flow->to_step_id,
                'team_id'      => $flow->team_id,
                'message'      => 'Prozess-Flow erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Prozess-Flows: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'process_flows', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
