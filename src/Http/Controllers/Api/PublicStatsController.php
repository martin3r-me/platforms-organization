<?php

namespace Platform\Organization\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Platform\Core\Http\Controllers\ApiController;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationRoleAssignment;
use Platform\Organization\Models\OrganizationTimeEntry;

/**
 * Public Stats — kuratierte, unkritische Kennzahlen für die öffentliche Website.
 *
 * Auth: Bearer-Token (api.auth). Team = current_team_id des Token-Users.
 * Bewusst NICHT ausgeliefert: Notizen (note), Kontext-Labels (Kundennamen),
 * Beträge/€ (rate/amount), Nutzernamen von Menschen, Governor-Interna
 * (five_hour_pct/-reserve_pct, seven_day_pct/-burn_margin_pct), Token,
 * E-Mail, linked_user_id. Agenten werden namentlich genannt (Teil der
 * Story), Menschen erscheinen nur aggregiert/anonym.
 *
 * Mensch vs. Agent: Agent-Zeiteinträge tragen metadata.source = 'agent'
 * (gesetzt vom AgentTimeController). Alles andere zählt als Menschenzeit.
 */
class PublicStatsController extends ApiController
{
    /** T-Shirt-Größe → Story Points. */
    private const SP = ['xs' => 1, 's' => 2, 'm' => 3, 'l' => 5, 'xl' => 8, 'xxl' => 13];

    public function index(Request $request)
    {
        $teamId = (int) (Auth::user()?->current_team_id ?? 0);
        if (! $teamId) {
            return $this->error('Kein Team im Token-Kontext.', null, 422);
        }

        try {
            $base = OrganizationTimeEntry::query()->where('team_id', $teamId);

            $totalMin = (int) (clone $base)->sum('minutes');
            $agentMin = (int) (clone $base)->where('metadata->source', 'agent')->sum('minutes');
            $humanMin = max(0, $totalMin - $agentMin);

            return $this->success([
                'time' => [
                    'human_hours' => round($humanMin / 60, 1),
                    'agent_hours' => round($agentMin / 60, 1),
                    'total_hours' => round($totalMin / 60, 1),
                    'agent_share_pct' => $totalMin > 0 ? round($agentMin / $totalMin * 100, 1) : 0.0,
                ],
                'agents' => $this->agents($teamId),
                'activity' => $this->activity($teamId),
                'agent_activity' => $this->agentActivity($teamId, 50),
                'generated_at' => now()->toIso8601String(),
            ], 'Public Stats');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('PublicStats error', ['message' => $e->getMessage()]);

            return $this->error('Stats momentan nicht verfügbar.', null, 500);
        }
    }

    public function health(Request $request)
    {
        $teamId = (int) (Auth::user()?->current_team_id ?? 0);

        return $this->success([
            'status' => 'ok',
            'team_id' => $teamId,
            'agent_entries' => $teamId
                ? OrganizationTimeEntry::where('team_id', $teamId)->where('metadata->source', 'agent')->count()
                : 0,
            'timestamp' => now()->toIso8601String(),
        ], 'Public Stats API erreichbar');
    }

    /**
     * Kennzahlen je Agent: über die Agent-Entity gefunden (nicht mehr nur über Zeiteinträge),
     * damit auch frisch onboardete Agenten ohne gebuchte Zeit auftauchen. Zeit-Kennzahlen,
     * Rolle/Beschreibung/Domäne/Stufe und Status/Steckbrief werden per linked_user_id bzw.
     * Rollen-Zuweisung/Agent-Profil angehängt (LEFT JOIN-Semantik über PHP-Maps, best-effort).
     */
    protected function agents(int $teamId): array
    {
        $entities = OrganizationEntity::query()
            ->forTeam($teamId)
            ->agents()
            ->with('agentProfile')
            ->get();

        if ($entities->isEmpty()) {
            return [];
        }

        $linkedUserIds = $entities->pluck('linked_user_id')->filter()->unique()->values()->all();

        $timeByUser = $linkedUserIds
            ? OrganizationTimeEntry::query()
                ->where('team_id', $teamId)
                ->where('metadata->source', 'agent')
                ->whereIn('user_id', $linkedUserIds)
                ->selectRaw('user_id, SUM(minutes) as minutes, COUNT(*) as entries, MIN(work_date) as since')
                ->groupBy('user_id')
                ->get()
                ->keyBy('user_id')
            : collect();

        $rolesByEntity = OrganizationRoleAssignment::query()
            ->whereIn('person_entity_id', $entities->pluck('id')->all())
            ->with('role')
            ->get()
            ->groupBy('person_entity_id');

        return $entities->map(function (OrganizationEntity $entity) use ($timeByUser, $rolesByEntity) {
            $userId = (int) ($entity->linked_user_id ?? 0);
            $time = $userId ? $timeByUser->get($userId) : null;
            [$closed, $points] = $this->devTotals($userId);

            $role = $rolesByEntity->get($entity->id, collect())->pluck('role')->filter()->first();
            $profile = $entity->agentProfile;
            $online = (bool) $profile?->active && (bool) $profile?->isOnline();

            return [
                'name' => $this->cleanName($entity->name),
                'since' => $time->since ?? null,
                'hours' => round(((int) ($time->minutes ?? 0)) / 60, 1),
                'entries' => (int) ($time->entries ?? 0),
                'issues_closed' => $closed,
                'story_points' => $points,
                'role' => $role?->name,
                'description' => $entity->description,
                'domain' => $role?->domain,
                'stage' => $role?->stage,
                'status' => $online ? 'online' : 'idle',
                'last_active' => optional($profile?->last_heartbeat_at)->toIso8601String(),
                'claude_model' => $profile?->claude_model,
                'github_username' => $profile?->github_username,
            ];
        })->sortByDesc('hours')->values()->all();
    }

    /**
     * Geschlossene Dev-Issues und summierte Story Points eines Users (best-effort).
     *
     * @return array{0:int,1:int} [issues_closed, story_points]
     */
    protected function devTotals(int $userId): array
    {
        $issueClass = 'Platform\Dev\Models\DevIssue';
        if (! class_exists($issueClass)) {
            return [0, 0];
        }

        try {
            $issues = $issueClass::query()
                ->where('user_in_charge_id', $userId)
                ->where('is_done', true)
                ->get(['story_points']);

            $points = $issues->reduce(function (int $sum, $issue) {
                $size = is_string($issue->story_points ?? null) ? strtolower($issue->story_points) : null;

                return $sum + (self::SP[$size] ?? 0);
            }, 0);

            return [$issues->count(), $points];
        } catch (\Throwable $e) {
            return [0, 0];
        }
    }

    /**
     * Anonymisierter Aktivitäts-Log: nur Zeitpunkt, Typ, Dauer und Mensch/Agent.
     * Keine Notizen, keine Kontext-Labels, keine Namen.
     */
    protected function activity(int $teamId, int $limit = 24): array
    {
        return OrganizationTimeEntry::query()
            ->where('team_id', $teamId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['minutes', 'context_type', 'created_at', 'metadata'])
            ->map(function (OrganizationTimeEntry $entry) {
                $isAgent = ($entry->metadata['source'] ?? null) === 'agent';

                return [
                    'at' => optional($entry->created_at)->toIso8601String(),
                    'type' => $this->classify((string) $entry->context_type, $isAgent),
                    'minutes' => (int) $entry->minutes,
                    'actor' => $isAgent ? 'agent' : 'human',
                ];
            })
            ->all();
    }

    /**
     * Aktivitäts-Log nur der Agenten (Bumblebee/Ironhide), inkl. Agent-Name.
     * Letzte $limit Einträge. Keine Notizen/Kontext-Labels.
     */
    protected function agentActivity(int $teamId, int $limit = 50): array
    {
        $rows = OrganizationTimeEntry::query()
            ->where('team_id', $teamId)
            ->where('metadata->source', 'agent')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['user_id', 'minutes', 'context_type', 'created_at']);

        if ($rows->isEmpty()) {
            return [];
        }

        $userModel = config('auth.providers.users.model');
        $names = $userModel::query()
            ->whereIn('id', $rows->pluck('user_id')->unique()->all())
            ->pluck('name', 'id');

        return $rows->map(fn ($e) => [
            'at' => optional($e->created_at)->toIso8601String(),
            'agent' => $this->cleanName($names[$e->user_id] ?? 'Agent'),
            'type' => $this->classify((string) $e->context_type, true),
            'minutes' => (int) $e->minutes,
        ])->all();
    }

    /** Kontext-Typ (FQCN) → grober Arbeitstyp für die Anzeige. */
    protected function classify(string $contextType, bool $isAgent): string
    {
        if (str_contains($contextType, 'Dev')) {
            return 'Development';
        }
        if (str_contains($contextType, 'Helpdesk')) {
            return 'Support';
        }
        if (str_contains($contextType, 'Planner')) {
            // Agenten buchen Assistenz auf ein eigenes RUN-Projekt.
            return $isAgent && str_contains($contextType, 'Project') ? 'Assistent' : 'Projekt';
        }

        return 'Sonstiges';
    }

    /** "BUMBLEBEE 🐝" → "Bumblebee". */
    protected function cleanName(string $name): string
    {
        $name = trim(preg_replace('/[^\p{L}\p{N}\s.\-]/u', '', $name) ?? $name);

        return \Illuminate\Support\Str::title(mb_strtolower($name));
    }
}
