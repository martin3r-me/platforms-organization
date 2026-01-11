<?php

namespace Platform\Organization\Tools\Concerns;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\Team;

trait ResolvesOrganizationTeam
{
    /**
     * Resolves a team the user has access to, and derives its root/parent team.
     *
     * @return array{team_id:int|null, root_team_id:int|null, team:Team|null, error:ToolResult|null}
     */
    protected function resolveTeamAndRoot(array $arguments, ToolContext $context): array
    {
        $teamId = $arguments['team_id'] ?? $context->team?->id;
        if ($teamId === 0 || $teamId === '0') {
            $teamId = null;
        }

        if (!$teamId) {
            return [
                'team_id' => null,
                'root_team_id' => null,
                'team' => null,
                'error' => ToolResult::error('MISSING_TEAM', 'Kein Team angegeben und kein Team im Kontext gefunden.'),
            ];
        }

        $team = Team::find((int)$teamId);
        if (!$team) {
            return [
                'team_id' => (int)$teamId,
                'root_team_id' => null,
                'team' => null,
                'error' => ToolResult::error('TEAM_NOT_FOUND', 'Team nicht gefunden.'),
            ];
        }

        if (!$context->user) {
            return [
                'team_id' => (int)$teamId,
                'root_team_id' => null,
                'team' => $team,
                'error' => ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.'),
            ];
        }

        $userHasAccess = $context->user->teams()->where('teams.id', $team->id)->exists();
        if (!$userHasAccess) {
            return [
                'team_id' => (int)$teamId,
                'root_team_id' => null,
                'team' => $team,
                'error' => ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf dieses Team.'),
            ];
        }

        $root = $team->getRootTeam();

        return [
            'team_id' => $team->id,
            'root_team_id' => $root->id,
            'team' => $team,
            'error' => null,
        ];
    }
}

