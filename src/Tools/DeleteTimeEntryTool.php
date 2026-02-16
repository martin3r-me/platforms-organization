<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\Team;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationTimeEntry;

class DeleteTimeEntryTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;

    public function getName(): string
    {
        return 'organization.time_entries.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/time-entries/{id} - Löscht einen Zeiteintrag (soft delete). Parameter: time_entry_id (required).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: Team aus Kontext.',
                ],
                'time_entry_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Zeiteintrags (ERFORDERLICH). Nutze organization.time_entries.GET um IDs zu finden.',
                ],
            ],
            'required' => ['time_entry_id'],
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
                'time_entry_id',
                OrganizationTimeEntry::class,
                'NOT_FOUND',
                'Zeiteintrag nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationTimeEntry $entry */
            $entry = $found['model'];

            // Team-Zugehörigkeit prüfen
            if ((int) $entry->team_id !== (int) $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Zeiteintrag gehört nicht zum angegebenen Team.');
            }

            $deletedInfo = [
                'id' => $entry->id,
                'uuid' => $entry->uuid,
                'work_date' => $entry->work_date->format('Y-m-d'),
                'minutes' => $entry->minutes,
                'formatted' => OrganizationTimeEntry::formatMinutes($entry->minutes),
                'note' => $entry->note,
            ];

            $entry->delete();

            return ToolResult::success([
                ...$deletedInfo,
                'message' => 'Zeiteintrag gelöscht (soft delete).',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen des Zeiteintrags: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'time_entries', 'delete', 'time_tracking'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
