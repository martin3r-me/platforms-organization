<?php

namespace Platform\Organization\Livewire\Agent;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Platform\Organization\Models\OrganizationAgentRunEvent;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityRelationType;
use Platform\Organization\Models\OrganizationEntityRelationship;
use Platform\Organization\Services\AgentSupervisor;
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

    /** Person-Entität, an die der Agent berichtet (Beziehung „Berichtet an“); null = aus der Struktur ableiten. */
    public ?int $reportsTo = null;

    public ?string $supervisorMsg = null;

    /** Frisch geminteter Token — NUR einmal sichtbar (danach nur der Hash in der DB). */
    public ?string $mintedToken = null;

    private const LOG_TAIL = 200;

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
        $this->reportsTo = $this->explicitReportsTo()?->to_entity_id;
    }

    // ── Bezugsperson ──

    private function reportsToTypeId(): ?int
    {
        return OrganizationEntityRelationType::query()->where('code', 'reports_to')->value('id');
    }

    /** Die ausdrückliche Beziehung „Berichtet an“ zu einer Person (nicht zu einer Einheit). */
    private function explicitReportsTo(): ?OrganizationEntityRelationship
    {
        $type = $this->reportsToTypeId();
        if (! $type) {
            return null;
        }

        return OrganizationEntityRelationship::query()->active()
            ->where('from_entity_id', $this->entity->id)->where('relation_type_id', $type)
            ->whereHas('toEntity', fn ($q) => $q->persons())
            ->latest('id')->first();
    }

    /** Personen der Organisation als Auswahl. */
    public function personOptions(): array
    {
        return OrganizationEntity::query()->persons()
            ->where('team_id', $this->entity->team_id)
            ->where('is_active', true)
            ->orderBy('name')->get(['id', 'name'])
            ->map(fn ($p) => ['value' => $p->id, 'label' => $p->name])->all();
    }

    /** Setzt (oder entfernt) „Berichtet an“. Wirkt beim nächsten Profil-Abruf des Engrams — ohne Neustart. */
    public function saveSupervisor(): void
    {
        $this->validate(['reportsTo' => 'nullable|integer']);
        $type = $this->reportsToTypeId();
        if (! $type) {
            $this->supervisorMsg = 'Beziehungsart „Berichtet an“ fehlt in der Organisation.';

            return;
        }
        $current = $this->explicitReportsTo();
        if ($current && (int) $current->to_entity_id === (int) $this->reportsTo) {
            $this->supervisorMsg = 'Unverändert.';

            return;
        }
        if ($this->reportsTo) {
            $person = OrganizationEntity::query()->persons()->where('team_id', $this->entity->team_id)->find($this->reportsTo);
            if (! $person) {
                $this->addError('reportsTo', 'Bitte eine Person der Organisation wählen.');

                return;
            }
        }
        $current?->delete();
        if ($this->reportsTo) {
            OrganizationEntityRelationship::create([
                'from_entity_id' => $this->entity->id,
                'to_entity_id' => $this->reportsTo,
                'relation_type_id' => $type,
                'team_id' => $this->entity->team_id,
            ]);
        }
        $this->supervisorMsg = 'Gespeichert — das Engram übernimmt es beim nächsten Profil-Abruf.';
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

        // Nur das Ende des Laufs: das Panel pollt alle 4 s, ein langer Lauf hat tausende Events.
        return OrganizationAgentRunEvent::query()
            ->where('organization_entity_id', $this->entity->id)
            ->where('run_id', $latestRun)
            ->orderByDesc('id')
            ->limit(self::LOG_TAIL)
            ->get(['kind', 'text', 'created_at'])
            ->reverse()
            ->values()
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

        // Kurz gecacht: der Live-Log-Poll (4 s) rendert das ganze Panel neu, die Queue ändert sich selten.
        return Cache::remember('organization:agent-next-tasks:' . $this->entity->id, 30, fn () => $this->loadNextTasks($user->id));
    }

    private function loadNextTasks(int $userId): array
    {

        $tasks = [];

        try {
            if (Schema::hasTable('dev_issues')) {
                $tasks = array_merge($tasks, DB::table('dev_issues')
                    ->leftJoin('dev_boards', 'dev_issues.dev_board_id', '=', 'dev_boards.id')
                    ->whereNull('dev_issues.deleted_at')
                    ->where('dev_issues.user_in_charge_id', $userId)
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
                    ->where('planner_tasks.user_in_charge_id', $userId)
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
            'supervisor' => app(AgentSupervisor::class)->resolve($this->entity, $this->jobProfileReportsTo()),
            'persons' => $this->personOptions(),
        ]);
    }

    /** Berichtslinie aus dem Job-Profil (Freitext), falls das People-Modul da ist. */
    private function jobProfileReportsTo(): ?string
    {
        $class = \Platform\People\Models\JobProfile::class;
        if (! class_exists($class)) {
            return null;
        }
        try {
            $jp = $class::query()->where('owner_entity_id', $this->entity->id)->where('status', 'active')->orderByDesc('id')->first();
        } catch (\Throwable $e) {
            return null;
        }

        return is_array($jp?->reporting) ? ($jp->reporting['reports_to'] ?? null) : null;
    }
}
