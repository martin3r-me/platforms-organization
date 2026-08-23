<?php

namespace Platform\Organization\Services;

use Platform\Organization\Contracts\AgentSettingsProvider;

/**
 * Sammelt AgentSettingsProvider je VSM-Domaene (loose gekoppelt, #810). Das ProfilePanel fragt
 * hier nur nach der Domaene des jeweiligen Agenten — welche Module dafuer Felder registrieren,
 * ist dieser Registry egal.
 */
class AgentSettingsRegistry
{
    /** @var array<string, AgentSettingsProvider[]> domain => providers */
    protected array $providersByDomain = [];

    public function register(AgentSettingsProvider $provider): void
    {
        $this->providersByDomain[$provider->domain()][] = $provider;
    }

    /**
     * Merged Feld-Schema aller Provider einer Domaene (Reihenfolge = Registrierung, spaeter
     * registrierte Provider ueberschreiben gleiche Keys).
     *
     * @return array<int, array>
     */
    public function fieldsForDomain(?string $domain): array
    {
        if (! $domain) {
            return [];
        }

        $fields = [];
        foreach ($this->providersByDomain[$domain] ?? [] as $provider) {
            foreach ($provider->fields() as $field) {
                $fields[$field['key']] = $field;
            }
        }

        return array_values($fields);
    }
}
