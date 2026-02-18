<?php

namespace Platform\Organization\Tools\Concerns;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\Team;

trait ResolvesTimeEntryTeamScope
{
    /**
     * Resolves team scope for time entry queries.
     * In a parent team with Organization access: returns all child team IDs (cross-team, like the UI).
     * In a leaf/child team: returns only the single team ID.
     *
     * @return array{team_ids: int[]|null, team: Team|null, is_cross_team: bool, error: ToolResult|null}
     */
    protected function resolveTimeEntryTeamScope(array $arguments, ToolContext $context): array
    {
        if (!$context->user) {
            return [
                'team_ids' => null,
                'team' => null,
                'is_cross_team' => false,
                'error' => ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.'),
            ];
        }

        $teamId = $arguments['team_id'] ?? $context->team?->id;
        if (!$teamId) {
            return [
                'team_ids' => null,
                'team' => null,
                'is_cross_team' => false,
                'error' => ToolResult::error('MISSING_TEAM', 'Kein Team angegeben und kein Team im Kontext gefunden.'),
            ];
        }

        $team = Team::find((int) $teamId);
        if (!$team) {
            return [
                'team_ids' => null,
                'team' => null,
                'is_cross_team' => false,
                'error' => ToolResult::error('TEAM_NOT_FOUND', 'Team nicht gefunden.'),
            ];
        }

        $userHasAccess = $context->user->teams()->where('teams.id', $team->id)->exists();
        if (!$userHasAccess) {
            return [
                'team_ids' => null,
                'team' => $team,
                'is_cross_team' => false,
                'error' => ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf dieses Team.'),
            ];
        }

        // Check if cross_team is explicitly set; default: true (matching UI behavior)
        $crossTeam = $arguments['cross_team'] ?? true;

        if ($crossTeam) {
            // Mirror the Livewire UI: get root team + all child teams
            $rootTeam = $team->getRootTeam();
            $teamIds = [$rootTeam->id];
            $this->collectChildTeamIds($rootTeam, $teamIds);

            return [
                'team_ids' => $teamIds,
                'team' => $team,
                'is_cross_team' => count($teamIds) > 1,
                'error' => null,
            ];
        }

        // Single-team mode (cross_team: false)
        return [
            'team_ids' => [$team->id],
            'team' => $team,
            'is_cross_team' => false,
            'error' => null,
        ];
    }

    /**
     * Recursively collect all child team IDs.
     * Same pattern as Livewire\TimeEntries\Index::collectChildTeamIds().
     */
    protected function collectChildTeamIds(Team $team, array &$teamIds): void
    {
        $childTeams = $team->childTeams()->get();

        foreach ($childTeams as $childTeam) {
            $teamIds[] = $childTeam->id;
            $this->collectChildTeamIds($childTeam, $teamIds);
        }
    }
}
