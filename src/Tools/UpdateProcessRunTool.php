<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Enums\RunStatus;
use Platform\Organization\Models\OrganizationProcessRun;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class UpdateProcessRunTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_runs.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /organization/process_runs - Aktualisiert einen Durchlauf (Cancel oder Notes updaten).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => ['type' => 'integer'],
                'run_id'  => ['type' => 'integer', 'description' => 'ERFORDERLICH.'],
                'status'  => ['type' => 'string', 'description' => 'Optional: cancelled.'],
                'notes'   => ['type' => 'string', 'description' => 'Optional: Notizen aktualisieren.'],
            ],
            'required' => ['run_id'],
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

            $run = OrganizationProcessRun::find($arguments['run_id'] ?? 0);
            if (! $run) {
                return ToolResult::error('NOT_FOUND', 'Durchlauf nicht gefunden.');
            }
            if ((int) $run->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Durchlauf gehört nicht zum Team.');
            }

            $updates = [];

            if (! empty($arguments['status'])) {
                if ($arguments['status'] === 'cancelled') {
                    if ($run->status !== RunStatus::ACTIVE) {
                        return ToolResult::error('VALIDATION_ERROR', 'Nur aktive Durchläufe können abgebrochen werden.');
                    }
                    $updates['status'] = 'cancelled';
                    $updates['cancelled_at'] = now();
                } else {
                    return ToolResult::error('VALIDATION_ERROR', 'Nur status=cancelled wird unterstützt.');
                }
            }

            if (array_key_exists('notes', $arguments)) {
                $updates['notes'] = ($arguments['notes'] ?? null) ?: null;
            }

            if (empty($updates)) {
                return ToolResult::error('VALIDATION_ERROR', 'Keine Änderungen angegeben.');
            }

            $run->update($updates);

            return ToolResult::success([
                'id'      => $run->id,
                'uuid'    => $run->uuid,
                'status'  => $run->status?->value,
                'notes'   => $run->notes,
                'message' => 'Durchlauf aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Durchlaufs: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'processes', 'runs', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
