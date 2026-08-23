<?php

namespace Platform\Organization\Services\AgentSettings;

use Platform\Organization\Contracts\AgentSettingsProvider;

/**
 * Backoffice-Domaene (#812): vorerst keine eigenen Settings-Felder. Modul-Zugriff/Capabilities
 * sind bewusst NICHT hier — die sind person_module_access bzw. Rollen-Capability, keine Settings.
 * Registriert trotzdem, damit ein Backoffice-Agent im Agent-Tab garantiert keine Dev-Felder sieht.
 */
class BackofficeAgentSettingsProvider implements AgentSettingsProvider
{
    public function domain(): string
    {
        return 'backoffice';
    }

    public function fields(): array
    {
        return [];
    }
}
