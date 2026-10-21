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
     * Maschinenraum (org-intern) · Reporting (wandert ins reporting-Modul) ·
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
                    ['route' => 'organization.signals.index', 'label' => 'Signale', 'icon' => 'bell-alert', 'match' => '^/organization/signals'],
                    ['route' => 'organization.ops-room', 'label' => 'Ops-Room', 'icon' => 'squares-2x2', 'match' => '^/organization/ops-room'],
                    ['route' => 'organization.pulse', 'label' => 'Pulse', 'icon' => 'signal', 'match' => '^/organization/pulse'],
                    ['route' => 'organization.my-inquiries.index', 'label' => 'Meine Rückfragen', 'icon' => 'inbox', 'match' => '^/organization/my-inquiries', 'migrates' => 'home'],
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
                'label' => 'Maschinenraum',
                'note' => 'org-intern',
                'muted' => true,
                'items' => [
                    ['route' => 'organization.inference-runs.index', 'label' => 'Inference-Läufe', 'icon' => 'play', 'match' => '^/organization/inference-runs'],
                    ['route' => 'organization.memory.index', 'label' => 'Memory', 'icon' => 'circle-stack', 'match' => '^/organization/memory'],
                    ['route' => 'organization.environment-sources.index', 'label' => 'Umwelt-Quellen', 'icon' => 'globe-alt', 'match' => '^/organization/environment-sources'],
                    ['route' => 'organization.environment-snapshots.index', 'label' => 'Umwelt-Snapshots', 'icon' => 'camera', 'match' => '^/organization/environment-snapshots'],
                    ['route' => 'organization.inquiries.index', 'label' => 'Rückfragen (Admin)', 'icon' => 'question-mark-circle', 'match' => '^/organization/inquiries'],
                ],
            ],
            [
                'label' => 'Reporting',
                'note' => '→ reporting',
                'muted' => true,
                'items' => [
                    ['route' => 'core.verbalization.factory', 'label' => 'Baukasten', 'icon' => 'wrench-screwdriver', 'match' => '/verbalization/factory'],
                    ['route' => 'organization.synthesis-reports.index', 'label' => 'Synthese-Berichte', 'icon' => 'document-text', 'match' => '^/organization/synthesis-reports'],
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
            foreach ($section['items'] as &$item) {
                $item['active'] = (bool) preg_match('#' . $item['match'] . '#', $path);
            }
        }

        return $sections;
    }

    public function render()
    {
        // Fallback in case mount() wasn't called (e.g. layout @include without Livewire lifecycle)
        if (empty($this->sections)) {
            $this->sections = $this->buildSections();
        }

        return view('organization::livewire.sidebar', [
            'sections' => $this->markActive($this->sections),
        ])->layout('platform::layouts.app');
    }
}
