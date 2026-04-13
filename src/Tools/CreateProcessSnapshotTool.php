<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationProcess;
use Platform\Organization\Models\OrganizationProcessSnapshot;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class CreateProcessSnapshotTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_snapshots.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/process_snapshots - Erstellt einen Snapshot (eingefrorener Zustand) eines Prozesses inkl. Steps, Flows, Triggers, Outputs und strategischer Felder.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'    => ['type' => 'integer'],
                'process_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: ID des Prozesses.'],
                'label'      => ['type' => 'string', 'description' => 'Optional: Label, z.B. "Baseline", "Nach Optimierung".'],
            ],
            'required' => ['process_id'],
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

            $process = OrganizationProcess::with(['steps', 'flows', 'triggers', 'outputs'])->find($arguments['process_id'] ?? 0);
            if (! $process) {
                return ToolResult::error('NOT_FOUND', 'Prozess nicht gefunden.');
            }
            if ((int) $process->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Prozess gehört nicht zum Team.');
            }

            // Next version
            $maxVersion = OrganizationProcessSnapshot::where('process_id', $process->id)->max('version') ?? 0;
            $nextVersion = $maxVersion + 1;

            // Collect snapshot data
            $snapshotData = [
                'process' => $process->only([
                    'name', 'code', 'description', 'status', 'version', 'is_active',
                    'owner_entity_id', 'vsm_system_id', 'metadata',
                    'target_description', 'value_proposition', 'cost_analysis',
                    'risk_assessment', 'improvement_levers', 'action_plan', 'standardization_notes',
                ]),
                'steps'    => $process->steps->map(fn ($s) => $s->only([
                    'id', 'name', 'description', 'position', 'step_type',
                    'duration_target_minutes', 'wait_target_minutes',
                    'corefit_classification', 'automation_level', 'is_active',
                ]))->values()->toArray(),
                'flows'    => $process->flows->map(fn ($f) => $f->only([
                    'id', 'from_step_id', 'to_step_id', 'condition_label', 'is_default',
                ]))->values()->toArray(),
                'triggers' => $process->triggers->map(fn ($t) => $t->only([
                    'id', 'label', 'description', 'trigger_type',
                    'entity_id', 'source_process_id', 'interlink_id', 'schedule_expression',
                ]))->values()->toArray(),
                'outputs'  => $process->outputs->map(fn ($o) => $o->only([
                    'id', 'label', 'description', 'output_type',
                    'entity_id', 'target_process_id', 'interlink_id',
                ]))->values()->toArray(),
            ];

            // Calculate metrics
            $steps = $process->steps;
            $totalDuration = $steps->sum('duration_target_minutes') ?? 0;
            $totalWait = $steps->sum('wait_target_minutes') ?? 0;
            $corefitCounts = $steps->groupBy('corefit_classification')->map->count();
            $automationCounts = $steps->groupBy('automation_level')->map->count();

            $metrics = [
                'total_steps'      => $steps->count(),
                'total_flows'      => $process->flows->count(),
                'total_triggers'   => $process->triggers->count(),
                'total_outputs'    => $process->outputs->count(),
                'total_duration'   => $totalDuration,
                'total_wait'       => $totalWait,
                'corefit' => [
                    'core'    => $corefitCounts->get('core', 0),
                    'context' => $corefitCounts->get('context', 0),
                    'no_fit'  => $corefitCounts->get('no_fit', 0),
                ],
                'automation' => [
                    'human'          => $automationCounts->get('human', 0),
                    'llm_assisted'   => $automationCounts->get('llm_assisted', 0),
                    'llm_autonomous' => $automationCounts->get('llm_autonomous', 0),
                    'hybrid'         => $automationCounts->get('hybrid', 0),
                ],
            ];

            $snapshot = OrganizationProcessSnapshot::create([
                'process_id'         => $process->id,
                'version'            => $nextVersion,
                'label'              => ($arguments['label'] ?? null) ?: null,
                'snapshot_data'      => $snapshotData,
                'metrics'            => $metrics,
                'created_by_user_id' => $context->user?->id,
            ]);

            return ToolResult::success([
                'id'         => $snapshot->id,
                'uuid'       => $snapshot->uuid,
                'version'    => $snapshot->version,
                'label'      => $snapshot->label,
                'metrics'    => $metrics,
                'process_id' => $process->id,
                'message'    => "Snapshot v{$nextVersion} erstellt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Snapshots: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'processes', 'snapshots', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => false,
        ];
    }
}
