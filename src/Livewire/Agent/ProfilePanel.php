<?php

namespace Platform\Organization\Livewire\Agent;

use Livewire\Component;
use Platform\Organization\Models\OrganizationEntity;
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
            'github_username' => 'nullable|string|max:255',
        ]);

        $this->entity->agentProfile()->updateOrCreate([], [
            'five_hour_reserve_pct' => $this->five_hour_reserve_pct,
            'seven_day_burn_margin_pct' => $this->seven_day_burn_margin_pct,
            'max_story_points' => $this->max_story_points ?: null,
            'claude_model' => $this->claude_model ?: null,
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

    public function render()
    {
        return view('organization::livewire.agent.profile-panel', [
            'profile' => $this->entity->agentProfile,
            'linkedUser' => $this->entity->linkedUser,
            'roles' => $this->agentRoles(),
        ]);
    }
}
