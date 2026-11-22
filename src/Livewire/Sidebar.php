<?php

namespace Platform\Organization\Livewire;

use Livewire\Component;

class Sidebar extends Component
{
    public array $sections = [];

    public function mount(): void
    {
        $this->sections = $this->buildSections();
    }

    /**
     * Navigation der Organisation. Gruppen: Übersicht · Organisation · Zeit ·
     * Reporting (wandert ins reporting-Modul) ·
     * Einstellungen (ein Eintrag, Kategorien in der Einstellungs-Sidebar). Abgesetzte Gruppen sind 'muted' und tragen eine 'note'.
     *
     * Item: route, label, icon, match (Regex auf den Pfad fürs Aktiv-Highlight),
     * optional migrates (Ziel-Modul, als leiser Hinweis).
     */
    protected function buildSections(): array
    {
        return [
            [
                'label' => 'Übersicht',
                'items' => [
                    ['route' => 'organization.dashboard', 'label' => 'Dashboard', 'icon' => 'home', 'match' => '^/organization/?$'],
                    ['route' => 'organization.pulse', 'label' => 'Pulse', 'icon' => 'signal', 'match' => '^/organization/pulse'],
                ],
            ],
            [
                'label' => 'Organisation',
                'items' => [
                    ['route' => 'organization.entities.index', 'label' => 'Einheiten', 'icon' => 'building-office', 'match' => '^/organization/entities'],
                    ['route' => 'organization.roles.index', 'label' => 'Rollen', 'icon' => 'user-group', 'match' => '^/organization/roles'],
                    ['route' => 'organization.agents.index', 'label' => 'Agenten', 'icon' => 'cpu-chip', 'match' => '^/organization/agents'],
                    ['route' => 'organization.interlinks.index', 'label' => 'Schnittstellen', 'icon' => 'arrows-right-left', 'match' => '^/organization/interlinks'],
                ],
            ],
            [
                'label' => 'Zeit',
                'items' => [
                    ['route' => 'organization.time-entries.index', 'label' => 'Ist-Zeiten', 'icon' => 'clock', 'match' => '^/organization/time-entries'],
                    ['route' => 'organization.planned-times.index', 'label' => 'Geplante Zeiten', 'icon' => 'calendar', 'match' => '^/organization/planned-times'],
                ],
            ],
            [
                'label' => 'Reporting',
                'note' => '→ reporting',
                'muted' => true,
                'items' => [
                    ['route' => 'reporting.dashboard', 'label' => 'Berichte', 'icon' => 'document-chart-bar', 'match' => '^/reporting(/reports.*)?$'],
                ],
            ],
            [
                'label' => 'Einstellungen',
                // Ein Eintrag — die Kategorien stehen in der zweiten Sidebar der Einstellungsseiten.
                'items' => [
                    ['route' => 'organization.settings.entity-types.index', 'label' => 'Einstellungen', 'icon' => 'cog-6-tooth', 'match' => '^/organization/settings'],
                ],
            ],
        ];
    }

    /** Aktiv-Markierung serverseitig — wire:navigate rendert das Layout je Seite neu. */
    protected function markActive(array $sections): array
    {
        $path = '/' . ltrim(request()->path(), '/');
        foreach ($sections as &$section) {
            // Links in lose gekoppelte Module (z. B. reporting) nur, wenn die Route existiert.
            $section['items'] = array_values(array_filter(
                $section['items'],
                fn ($item) => \Illuminate\Support\Facades\Route::has($item['route']),
            ));
            foreach ($section['items'] as &$item) {
                $item['active'] = (bool) preg_match('#' . $item['match'] . '#', $path);
            }
            unset($item);
        }
        unset($section);

        return array_values(array_filter($sections, fn ($section) => $section['items'] !== []));
    }

    public function render()
    {
        // Fallback in case mount() wasn't called (e.g. layout @include without Livewire lifecycle)
        if (empty($this->sections)) {
            $this->sections = $this->buildSections();
        }

        // Eigener Name: Livewire reicht die Property $sections ebenfalls an die View
        // und würde eine gleichnamige Variable überdecken.
        return view('organization::livewire.sidebar', [
            'nav' => $this->markActive($this->sections),
        ])->layout('platform::layouts.app');
    }
}
