<?php

namespace Platform\Organization\Livewire\Agent;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Platform\Organization\Models\OrganizationAgentRunEvent;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationMemoryEntry;
use Platform\Organization\Models\OrganizationRoleAssignment;

/**
 * Agent-Tab: die „kleine UI". Bearbeitet den reinen Runtime-Facet (Governor/Claim-Cap/Modell/
 * an-aus), zeigt den vom Daemon gemeldeten Status (read-only) und mintet den Plattform-API-Token
 * für den Bot-User dieses Agenten INLINE (kein Login-Flow — einmal anzeigen, in die VM-ENV kopieren).
 * Was der Agent TUT (Domäne × Stufe) kommt NICHT von hier, sondern aus seinen Rollen-Assignments
 * (Rollen-UI, wie bei jedem Mitglied) — hier read-only als Info. Claude-Login + GitHub-Token
 * bleiben auf dem Client; hier nur github_username als Referenz.
 */
class ProfilePanel extends Component
{
    public OrganizationEntity $entity;

    public int $five_hour_reserve_pct = 90;
    public int $seven_day_burn_margin_pct = 10;
    public ?int $max_story_points = null;
    public ?string $claude_model = null;
    public bool $claim_unassigned = true;
    public bool $active = true;
    public ?string $github_username = null;

    public ?string $savedMsg = null;

    /** Frisch geminteter Token — NUR einmal sichtbar (danach nur der Hash in der DB). */
    public ?string $mintedToken = null;

    public function mount(OrganizationEntity $entity): void
    {
        $this->entity = $entity;
        if ($p = $entity->agentProfile) {
            $this->five_hour_reserve_pct = (int) $p->five_hour_reserve_pct;
            $this->seven_day_burn_margin_pct = (int) $p->seven_day_burn_margin_pct;
            $this->max_story_points = $p->max_story_points;
            $this->claude_model = $p->claude_model;
            $this->claim_unassigned = (bool) $p->claim_unassigned;
            $this->active = (bool) $p->active;
            $this->github_username = $p->github_username;
        }
    }

    public function save(): void
    {
        $this->validate([
            'five_hour_reserve_pct' => 'integer|min:0|max:100',
            'seven_day_burn_margin_pct' => 'integer|min:0|max:100',
            'max_story_points' => 'nullable|integer|min:1|max:100',
            'claude_model' => 'nullable|string|max:64',
            'claim_unassigned' => 'boolean',
            'github_username' => 'nullable|string|max:255',
        ]);

        $this->entity->agentProfile()->updateOrCreate([], [
            'five_hour_reserve_pct' => $this->five_hour_reserve_pct,
            'seven_day_burn_margin_pct' => $this->seven_day_burn_margin_pct,
            'max_story_points' => $this->max_story_points ?: null,
            'claude_model' => $this->claude_model ?: null,
            'claim_unassigned' => $this->claim_unassigned,
            'active' => $this->active,
            'github_username' => $this->github_username ?: null,
        ]);

        $this->savedMsg = 'Gespeichert.';
    }

    /** Die Rollen des Agenten (Domäne × Stufe) — read-only, gepflegt in der Rollen-UI. */
    public function agentRoles(): array
    {
        return OrganizationRoleAssignment::query()
            ->where('person_entity_id', $this->entity->id)
            ->with('role')
            ->get()
            ->pluck('role')
            ->filter()
            ->map(fn ($r) => trim(($r->domain ?? '—').' · '.($r->stage ?? '—')).'  ('.$r->name.')')
            ->values()
            ->all();
    }

    /** Plattform-API-Token für den Bot-User minten (einmal anzeigen, in VM-ENV kopieren). */
    public function mintToken(): void
    {
        $user = $this->entity->linkedUser;
        if (! $user) {
            $this->savedMsg = 'Kein Bot-User verknüpft — bitte zuerst der Agent-Entity einen User zuordnen.';

            return;
        }
        if (! method_exists($user, 'createToken')) {
            $this->savedMsg = 'Token-Ausstellung nicht verfügbar.';

            return;
        }
        $this->mintedToken = $user->createToken('agent-daemon', ['*'], null)->accessToken;
    }

    public function dismissToken(): void
    {
        $this->mintedToken = null;
    }

    /** Der Aktivitäts-Feed des jüngsten Laufs (vom Daemon gemeldet) — read-only, live gepollt. */
    public function recentEvents(): array
    {
        $latestRun = OrganizationAgentRunEvent::query()
            ->where('organization_entity_id', $this->entity->id)
            ->orderByDesc('id')
            ->value('run_id');

        if (! $latestRun) {
            return [];
        }

        return OrganizationAgentRunEvent::query()
            ->where('organization_entity_id', $this->entity->id)
            ->where('run_id', $latestRun)
            ->orderBy('id')
            ->get(['kind', 'text', 'created_at'])
            ->all();
    }

    /** Die Domäne des Agenten aus seinen Rollen (wie der /agent/profile-Endpoint). null = keine. */
    private function agentDomain(): ?string
    {
        return OrganizationRoleAssignment::query()
            ->where('person_entity_id', $this->entity->id)
            ->with('role')
            ->get()
            ->pluck('role.domain')
            ->filter()
            ->first();
    }

    /**
     * Nächste Aufgaben — DOMÄNEN-abhängig: ein Dev-Agent sieht seine dev_issues, ein Backoffice-Agent
     * seine planner_tasks (jeweils user_in_charge_id = sein Bot-User). Cross-Modul, nur lesend, per
     * Schema-Guard + try/catch (fehlt das Modul/Spalte, bleibt der Block leer statt zu brechen).
     */
    public function nextTasks(): array
    {
        $user = $this->entity->linkedUser;
        if (! $user) {
            return [];
        }
        $domain = $this->agentDomain();

        try {
            if ($domain === 'development' && Schema::hasTable('dev_issues')) {
                return DB::table('dev_issues')
                    ->leftJoin('dev_boards', 'dev_issues.dev_board_id', '=', 'dev_boards.id')
                    ->whereNull('dev_issues.deleted_at')
                    ->where('dev_issues.user_in_charge_id', $user->id)
                    ->where('dev_issues.status', 'open')
                    ->where('dev_issues.is_done', false)
                    ->orderBy('dev_boards.order')
                    ->orderBy('dev_issues.slot_order')
                    ->orderBy('dev_issues.created_at')
                    ->limit(12)
                    ->get(['dev_issues.title', 'dev_boards.name as board', 'dev_boards.type as board_type'])
                    ->map(fn ($r) => ['title' => $r->title, 'board' => $r->board, 'type' => $r->board_type])
                    ->all();
            }
            if ($domain === 'backoffice' && Schema::hasTable('planner_tasks')) {
                return DB::table('planner_tasks')
                    ->leftJoin('planner_projects', 'planner_tasks.project_id', '=', 'planner_projects.id')
                    ->whereNull('planner_tasks.deleted_at')
                    ->where('planner_tasks.user_in_charge_id', $user->id)
                    ->where('planner_tasks.is_done', false)
                    ->orderBy('planner_tasks.project_slot_order')
                    ->orderBy('planner_tasks.created_at')
                    ->limit(12)
                    ->get(['planner_tasks.title', 'planner_projects.name as board'])
                    ->map(fn ($r) => ['title' => $r->title, 'board' => $r->board ?? 'Planner', 'type' => 'task'])
                    ->all();
            }
        } catch (\Throwable $e) {
            return [];
        }

        return [];
    }

    /**
     * Gelerntes = die Lektionen der EIGENEN Domäne des Agenten (nicht global!). Wissen gehört der
     * Domäne — ein Backoffice-Agent darf NICHT die Dev-Lektionen sehen. Naming-Wart: die Domäne
     * heißt "development", der Dev-Learn-Loop legt aber unter memory_type "dev.*" ab → gemappt.
     * Andere Domänen (backoffice …) haben (noch) keinen eigenen Store hier → leer statt Leak.
     */
    public function learnings(): array
    {
        if (! Schema::hasTable('organization_memory_entries')) {
            return [];
        }
        $prefix = match ($this->agentDomain()) {
            'development' => 'dev',
            null => null,
            default => $this->agentDomain(),
        };
        if (! $prefix) {
            return [];
        }

        return OrganizationMemoryEntry::query()
            ->where('team_id', (int) $this->entity->team_id)
            ->where('memory_type', 'like', $prefix.'.%')
            ->where('is_active', true)
            ->orderByDesc('reinforcement_count')
            ->orderByDesc('id')
            ->limit(12)
            ->get(['content', 'structured_data', 'reinforcement_count'])
            ->map(fn ($m) => [
                'content' => $m->content,
                'package' => data_get($m->structured_data, 'package'),
                'count' => (int) $m->reinforcement_count,
            ])
            ->all();
    }

    public function render()
    {
        return view('organization::livewire.agent.profile-panel', [
            'profile' => $this->entity->agentProfile,
            'linkedUser' => $this->entity->linkedUser,
            'roles' => $this->agentRoles(),
            'events' => $this->recentEvents(),
            'nextTasks' => $this->nextTasks(),
            'learnings' => $this->learnings(),
        ]);
    }
}
