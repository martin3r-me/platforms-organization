<?php

namespace Platform\Organization\Livewire\Agent;

use Livewire\Component;
use Platform\Organization\Models\OrganizationAgentProfile;
use Platform\Organization\Models\OrganizationEntity;

/**
 * Agent-Tab: die „kleine UI". Bearbeitet die Runtime-Config (Domäne/Stufen/Governor/an-aus),
 * zeigt den vom Daemon gemeldeten Status (read-only) und mintet den Plattform-API-Token für
 * den Bot-User dieses Agenten INLINE (kein Login-Flow — einmal anzeigen, in die VM-ENV kopieren).
 * Claude-Login + GitHub-Token bleiben auf dem Client; hier nur github_username als Referenz.
 */
class ProfilePanel extends Component
{
    public OrganizationEntity $entity;

    public ?string $domain = null;
    public array $stages = [];
    public int $five_hour_reserve_pct = 90;
    public int $seven_day_burn_margin_pct = 10;
    public bool $active = true;
    public ?string $github_username = null;

    public ?string $savedMsg = null;

    /** Frisch geminteter Token — NUR einmal sichtbar (danach nur der Hash in der DB). */
    public ?string $mintedToken = null;

    /** Auswahl der Stufen (operativ + analysis). */
    public array $availableStages = ['triage', 'execute', 'learn', 'signal'];

    public function mount(OrganizationEntity $entity): void
    {
        $this->entity = $entity;
        if ($p = $entity->agentProfile) {
            $this->domain = $p->domain;
            $this->stages = $p->stages ?? [];
            $this->five_hour_reserve_pct = (int) $p->five_hour_reserve_pct;
            $this->seven_day_burn_margin_pct = (int) $p->seven_day_burn_margin_pct;
            $this->active = (bool) $p->active;
            $this->github_username = $p->github_username;
        }
    }

    public function save(): void
    {
        $this->validate([
            'domain' => 'nullable|string|in:'.implode(',', OrganizationAgentProfile::DOMAINS),
            'stages' => 'array',
            'stages.*' => 'string|in:'.implode(',', $this->availableStages),
            'five_hour_reserve_pct' => 'integer|min:0|max:100',
            'seven_day_burn_margin_pct' => 'integer|min:0|max:100',
            'github_username' => 'nullable|string|max:255',
        ]);

        $this->entity->agentProfile()->updateOrCreate([], [
            'domain' => $this->domain,
            'stages' => array_values(array_filter($this->stages)),
            'five_hour_reserve_pct' => $this->five_hour_reserve_pct,
            'seven_day_burn_margin_pct' => $this->seven_day_burn_margin_pct,
            'active' => $this->active,
            'github_username' => $this->github_username ?: null,
        ]);

        $this->savedMsg = 'Gespeichert.';
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
        ]);
    }
}
