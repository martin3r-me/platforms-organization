<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationProcess;
use Platform\Organization\Models\OrganizationProcessImprovement;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ListProcessImprovementsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_improvements.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/process_improvements - Listet Verbesserungen eines Prozesses. Filter: status, category, priority.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'    => ['type' => 'integer'],
                'process_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH.'],
                'status'     => ['type' => 'string', 'description' => 'Optional: identified | planned | in_progress | completed | rejected.'],
                'category'   => ['type' => 'string', 'description' => 'Optional: cost | quality | speed | risk | standardization.'],
                'priority'   => ['type' => 'string', 'description' => 'Optional: low | medium | high | critical.'],
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

            $process = OrganizationProcess::find($arguments['process_id'] ?? 0);
            if (! $process) {
                return ToolResult::error('NOT_FOUND', 'Prozess nicht gefunden.');
            }
            if ((int) $process->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Prozess gehört nicht zum Team.');
            }

            $q = OrganizationProcessImprovement::where('process_id', $process->id)
                ->where('team_id', $rootTeamId);

            if (! empty($arguments['status'])) {
                $q->where('status', (string) $arguments['status']);
            }
            if (! empty($arguments['category'])) {
                $q->where('category', (string) $arguments['category']);
            }
            if (! empty($arguments['priority'])) {
                $q->where('priority', (string) $arguments['priority']);
            }

            $items = $q->orderByDesc('created_at')
                ->get()
                ->map(fn (OrganizationProcessImprovement $i) => [
                    'id'                 => $i->id,
                    'uuid'               => $i->uuid,
                    'title'              => $i->title,
                    'description'        => $i->description,
                    'category'           => $i->category,
                    'priority'           => $i->priority,
                    'status'             => $i->status,
                    'expected_outcome'   => $i->expected_outcome,
                    'actual_outcome'     => $i->actual_outcome,
                    'before_snapshot_id' => $i->before_snapshot_id,
                    'after_snapshot_id'  => $i->after_snapshot_id,
                    'completed_at'       => $i->completed_at?->toIso8601String(),
                    'user_id'            => $i->user_id,
                    'created_at'         => $i->created_at?->toIso8601String(),
                ])
                ->values()
                ->toArray();

            return ToolResult::success([
                'data'       => $items,
                'process_id' => $process->id,
                'total'      => count($items),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Verbesserungen: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'read',
            'tags'          => ['organization', 'processes', 'improvements', 'lookup'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
