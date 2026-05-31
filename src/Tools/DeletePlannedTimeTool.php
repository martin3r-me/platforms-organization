<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\Team;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationTimePlanned;

class DeletePlannedTimeTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;

    public function getName(): string
    {
        return 'organization.planned_time.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/planned-time/{id} - Löscht einen Soll-Zeiteintrag. Parameter: planned_time_id (required).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: Team aus Kontext.',
                ],
                'planned_time_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Soll-Zeiteintrags (ERFORDERLICH). Nutze organization.planned_time.GET um IDs zu finden.',
                ],
            ],
            'required' => ['planned_time_id'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $teamId = $arguments['team_id'] ?? $context->team?->id;
            if (!$teamId) {
                return ToolResult::error('MISSING_TEAM', 'Kein Team angegeben und kein Team im Kontext gefunden.');
            }

            $team = Team::find((int) $teamId);
            if (!$team) {
                return ToolResult::error('TEAM_NOT_FOUND', 'Team nicht gefunden.');
            }

            $userHasAccess = $context->user->teams()->where('teams.id', $team->id)->exists();
            if (!$userHasAccess) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf dieses Team.');
            }

            $found = $this->validateAndFindModel(
                $arguments,
                $context,
                'planned_time_id',
                OrganizationTimePlanned::class,
                'NOT_FOUND',
                'Soll-Zeiteintrag nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationTimePlanned $entry */
            $entry = $found['model'];

            if ((int) $entry->team_id !== (int) $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Soll-Zeiteintrag gehört nicht zum angegebenen Team.');
            }

            $deletedInfo = [
                'id' => $entry->id,
                'uuid' => $entry->uuid,
                'planned_minutes' => $entry->planned_minutes,
                'hours' => $entry->hours,
                'context_type' => $entry->context_type,
                'context_id' => $entry->context_id,
                'note' => $entry->note,
            ];

            $entry->delete();

            return ToolResult::success([
                ...$deletedInfo,
                'message' => 'Soll-Zeiteintrag gelöscht.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen des Soll-Zeiteintrags: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'planned_time', 'delete', 'time_planning'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
