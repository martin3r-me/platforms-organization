<?php

namespace Platform\Organization\Livewire\Agent;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Platform\Organization\Models\OrganizationAgentRunEvent;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationRoleAssignment;

/**
 * Agent-Tab: die „kleine UI". Bearbeitet den reinen Runtime-Facet (Governor/Claim-Cap/Modell/
 * an-aus), zeigt den vom Daemon gemeldeten Status (read-only) und mintet den Plattform-API-Token
 * für den Bot-User dieses Agenten INLINE (kein Login-Flow — einmal anzeigen, in die VM-ENV kopieren).
 * Was der Agent TUT, kommt aus seinem Job-Profil (people) + seinen Capabilities/Tokens — nicht aus
 * einer „Domäne". Die Rollen-Assignments zeigen wir read-only als Info (Stufe/Name).
 */
class ProfilePanel extends Component
{
    public OrganizationEntity $entity;

    public int $five_hour_reserve_pct = 90;
    public int $seven_day_burn_margin_pct = 10;
    public ?string $claude_model = null;
    public bool $claim_unassigned = true;
    public bool $active = true;

    public ?string $savedMsg = null;

    /** Frisch geminteter Token — NUR einmal sichtbar (danach nur der Hash in der DB). */
    public ?string $mintedToken = null;

    public function mount(OrganizationEntity $entity): void
    {
        $this->entity = $entity;
        if ($p = $entity->agentProfile) {
            $this->five_hour_reserve_pct = (int) $p->five_hour_reserve_pct;
            $this->seven_day_burn_margin_pct = (int) $p->seven_day_burn_margin_pct;
            $this->claude_model = $p->claude_model;
            $this->claim_unassigned = (bool) $p->claim_unassigned;
            $this->active = (bool) $p->active;
        }
    }

    public function save(): void
    {
        $this->validate([
            'five_hour_reserve_pct' => 'integer|min:0|max:100',
            'seven_day_burn_margin_pct' => 'integer|min:0|max:100',
            'claude_model' => 'nullable|string|max:64',
            'claim_unassigned' => 'boolean',
        ]);

        $this->entity->agentProfile()->updateOrCreate([], [
            'five_hour_reserve_pct' => $this->five_hour_reserve_pct,
            'seven_day_burn_margin_pct' => $this->seven_day_burn_margin_pct,
            'claude_model' => $this->claude_model ?: null,
            'claim_unassigned' => $this->claim_unassigned,
            'active' => $this->active,
        ]);

        $this->savedMsg = 'Gespeichert.';
    }

    /** Die Rollen-Assignments des Agenten (Stufe · Name) — read-only, gepflegt in der Rollen-UI. */
    public function agentRoles(): array
    {
        return OrganizationRoleAssignment::query()
            ->where('person_entity_id', $this->entity->id)
            ->with('role')
            ->get()
            ->pluck('role')
            ->filter()
            ->map(fn ($r) => trim(($r->stage ? $r->stage.' · ' : '').$r->name))
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

    /**
     * Nächste Aufgaben des Bot-Users (user_in_charge_id) — aus dev_issues UND planner_tasks
     * zusammengeführt. Kein Domänen-Schalter mehr: ein Agent hat faktisch nur in einer der Tabellen
     * Einträge; wir zeigen einfach, was ihm zugewiesen ist. Cross-Modul, nur lesend, per Schema-Guard
     * + try/catch (fehlt Modul/Spalte, bleibt der Block leer statt zu brechen).
     */
    public function nextTasks(): array
    {
        $user = $this->entity->linkedUser;
        if (! $user) {
            return [];
        }

        $tasks = [];

        try {
            if (Schema::hasTable('dev_issues')) {
                $tasks = array_merge($tasks, DB::table('dev_issues')
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
                    ->all());
            }
        } catch (\Throwable $e) {
            // dev-Modul nicht verfügbar → überspringen
        }

        try {
            if (Schema::hasTable('planner_tasks')) {
                $tasks = array_merge($tasks, DB::table('planner_tasks')
                    ->leftJoin('planner_projects', 'planner_tasks.project_id', '=', 'planner_projects.id')
                    ->whereNull('planner_tasks.deleted_at')
                    ->where('planner_tasks.user_in_charge_id', $user->id)
                    ->where('planner_tasks.is_done', false)
                    ->orderBy('planner_tasks.project_slot_order')
                    ->orderBy('planner_tasks.created_at')
                    ->limit(12)
                    ->get(['planner_tasks.title', 'planner_projects.name as board'])
                    ->map(fn ($r) => ['title' => $r->title, 'board' => $r->board ?? 'Planner', 'type' => 'task'])
                    ->all());
            }
        } catch (\Throwable $e) {
            // planner-Modul nicht verfügbar → überspringen
        }

        return array_slice($tasks, 0, 12);
    }

    public function render()
    {
        return view('organization::livewire.agent.profile-panel', [
            'profile' => $this->entity->agentProfile,
            'linkedUser' => $this->entity->linkedUser,
            'roles' => $this->agentRoles(),
            'events' => $this->recentEvents(),
            'nextTasks' => $this->nextTasks(),
        ]);
    }
}
