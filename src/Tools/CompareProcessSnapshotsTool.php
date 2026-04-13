<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationProcessSnapshot;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class CompareProcessSnapshotsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_snapshots.COMPARE';
    }

    public function getDescription(): string
    {
        return 'COMPARE /organization/process_snapshots - Vergleicht zwei Snapshots und zeigt Unterschiede in Steps, Metrics und strategischen Feldern.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'       => ['type' => 'integer'],
                'snapshot_a_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: ID des ersten (älteren) Snapshots.'],
                'snapshot_b_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: ID des zweiten (neueren) Snapshots.'],
            ],
            'required' => ['snapshot_a_id', 'snapshot_b_id'],
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

            $a = OrganizationProcessSnapshot::with('process')->find($arguments['snapshot_a_id'] ?? 0);
            $b = OrganizationProcessSnapshot::with('process')->find($arguments['snapshot_b_id'] ?? 0);

            if (! $a || ! $b) {
                return ToolResult::error('NOT_FOUND', 'Einer oder beide Snapshots nicht gefunden.');
            }
            if ((int) $a->process->team_id !== $rootTeamId || (int) $b->process->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Snapshots gehören nicht zum Team.');
            }

            $dataA = $a->snapshot_data ?? [];
            $dataB = $b->snapshot_data ?? [];
            $metricsA = $a->metrics ?? [];
            $metricsB = $b->metrics ?? [];

            // Steps diff
            $stepsA = collect($dataA['steps'] ?? []);
            $stepsB = collect($dataB['steps'] ?? []);
            $stepNamesA = $stepsA->pluck('name')->toArray();
            $stepNamesB = $stepsB->pluck('name')->toArray();

            $stepsAdded = array_values(array_diff($stepNamesB, $stepNamesA));
            $stepsRemoved = array_values(array_diff($stepNamesA, $stepNamesB));

            // Changed steps (same name, different attributes)
            $stepsChanged = [];
            foreach ($stepsB as $stepB) {
                $stepA = $stepsA->firstWhere('name', $stepB['name']);
                if ($stepA) {
                    $changes = [];
                    foreach (['step_type', 'duration_target_minutes', 'wait_target_minutes', 'corefit_classification', 'is_active', 'position'] as $field) {
                        if (($stepA[$field] ?? null) !== ($stepB[$field] ?? null)) {
                            $changes[$field] = ['from' => $stepA[$field] ?? null, 'to' => $stepB[$field] ?? null];
                        }
                    }
                    if (! empty($changes)) {
                        $stepsChanged[] = ['name' => $stepB['name'], 'changes' => $changes];
                    }
                }
            }

            // Metrics delta
            $metricsDelta = [];
            foreach (['total_steps', 'total_flows', 'total_triggers', 'total_outputs', 'total_duration', 'total_wait'] as $key) {
                $valA = $metricsA[$key] ?? 0;
                $valB = $metricsB[$key] ?? 0;
                $metricsDelta[$key] = [
                    'before' => $valA,
                    'after'  => $valB,
                    'delta'  => $valB - $valA,
                ];
            }

            // CoreFit delta
            $corefitDelta = [];
            foreach (['core', 'context', 'no_fit'] as $cf) {
                $valA = $metricsA['corefit'][$cf] ?? 0;
                $valB = $metricsB['corefit'][$cf] ?? 0;
                $corefitDelta[$cf] = ['before' => $valA, 'after' => $valB, 'delta' => $valB - $valA];
            }

            // Process-level field changes
            $processChanges = [];
            $processA = $dataA['process'] ?? [];
            $processB = $dataB['process'] ?? [];
            foreach (['name', 'code', 'description', 'status', 'version', 'target_description', 'value_proposition', 'cost_analysis', 'risk_assessment', 'improvement_levers', 'action_plan', 'standardization_notes'] as $field) {
                if (($processA[$field] ?? null) !== ($processB[$field] ?? null)) {
                    $processChanges[$field] = [
                        'before' => $processA[$field] ?? null,
                        'after'  => $processB[$field] ?? null,
                    ];
                }
            }

            return ToolResult::success([
                'snapshot_a' => ['id' => $a->id, 'version' => $a->version, 'label' => $a->label],
                'snapshot_b' => ['id' => $b->id, 'version' => $b->version, 'label' => $b->label],
                'steps' => [
                    'added'   => $stepsAdded,
                    'removed' => $stepsRemoved,
                    'changed' => $stepsChanged,
                ],
                'metrics_delta'  => $metricsDelta,
                'corefit_delta'  => $corefitDelta,
                'process_changes' => $processChanges,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Vergleich: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'read',
            'tags'          => ['organization', 'processes', 'snapshots', 'compare'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
