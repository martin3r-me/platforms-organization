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
     * Sidebar navigation structure — Schritt 1 der IA-Restrukturierung.
     *
     * Gruppen spiegeln die Klassifikation „Betreiben / Organisation / Maschinenraum":
     * front-of-house (Betreiben, Organisation, Zeit & Kosten) vs. abgesetzte,
     * org-interne bzw. abwandernde Bereiche (Maschinenraum, Reporting).
     *
     * NICHTS wird entfernt oder verschoben — alle 25 Zugänge bleiben lauffähig.
     * Abwandernde Ziele werden nur MARKIERT (lebende Migrations-Landkarte):
     *   - Gruppe: 'note' (z. B. „→ eigenes Modul") + 'muted' (optisch abgesetzt)
     *   - Item:   'migrates' => 'reporting' | 'home' (Marker-Pille am Item)
     *
     * Each item: route, label, icon, match (URL-Substring fürs Active-Highlighting),
     *            optional migrates.
     */
    protected function buildSections(): array
    {
        return [
            [
                'label' => 'Betreiben',
                'items' => [
                    ['route' => 'organization.dashboard', 'label' => 'Dashboard', 'icon' => 'chart-bar', 'match' => '/organization$|/organization/$'],
                    ['route' => 'organization.signals.index', 'label' => 'Signale', 'icon' => 'bell-alert', 'match' => '/signals'],
                    ['route' => 'organization.my-inquiries.index', 'label' => 'Meine Inquiries', 'icon' => 'inbox', 'match' => '/my-inquiries', 'migrates' => 'home'],
                    ['route' => 'organization.ops-room', 'label' => 'Ops-Room (VSM)', 'icon' => 'squares-2x2', 'match' => '/ops-room'],
                    ['route' => 'organization.pulse', 'label' => 'Pulse', 'icon' => 'signal', 'match' => '/pulse'],
                ],
            ],
            [
                'label' => 'Organisation',
                'items' => [
                    ['route' => 'organization.entities.index', 'label' => 'Organisationseinheiten', 'icon' => 'building-office', 'match' => '/entities'],
                    ['route' => 'organization.interlinks.index', 'label' => 'Interlinks', 'icon' => 'arrows-right-left', 'match' => '/interlinks'],
                    ['route' => 'organization.sla-contracts.index', 'label' => 'SLA-Verträge', 'icon' => 'shield-check', 'match' => '/sla-contracts'],
                    ['route' => 'organization.roles.index', 'label' => 'Rollen', 'icon' => 'user-group', 'match' => '/roles'],
                ],
            ],
            [
                'label' => 'Zeit & Kosten',
                'items' => [
                    ['route' => 'organization.time-entries.index', 'label' => 'Ist-Zeiten', 'icon' => 'clock', 'match' => '/time-entries'],
                    ['route' => 'organization.planned-times.index', 'label' => 'Geplante Zeiten', 'icon' => 'calendar', 'match' => '/planned-times'],
                ],
            ],
            [
                'label' => 'Maschinenraum',
                'note' => 'org-intern · Engine',
                'muted' => true,
                'items' => [
                    ['route' => 'organization.inference-runs.index', 'label' => 'Inference Runs', 'icon' => 'play', 'match' => '/inference-runs'],
                    ['route' => 'organization.memory.index', 'label' => 'Memory', 'icon' => 'circle-stack', 'match' => '/memory'],
                    ['route' => 'organization.environment-sources.index', 'label' => 'Umwelt-Quellen', 'icon' => 'globe-alt', 'match' => '/environment-sources'],
                    ['route' => 'organization.environment-snapshots.index', 'label' => 'Umwelt-Snapshots', 'icon' => 'camera', 'match' => '/environment-snapshots'],
                    ['route' => 'organization.inquiries.index', 'label' => 'Inquiries (Admin)', 'icon' => 'question-mark-circle', 'match' => '/inquiries'],
                ],
            ],
            [
                'label' => 'Reporting',
                'note' => '→ eigenes Modul',
                'muted' => true,
                'items' => [
                    ['route' => 'core.verbalization.factory', 'label' => 'Baukasten', 'icon' => 'squares-2x2', 'match' => '/verbalization/factory', 'migrates' => 'reporting'],
                    ['route' => 'organization.synthesis-reports.index', 'label' => 'Synthesis Reports', 'icon' => 'document-text', 'match' => '/synthesis-reports', 'migrates' => 'reporting'],
                    ['route' => 'organization.settings.synthesis-prompts.index', 'label' => 'Synthesis Prompts', 'icon' => 'document-text', 'match' => '/settings/synthesis-prompts', 'migrates' => 'reporting'],
                ],
            ],
            [
                'label' => 'Einstellungen',
                'items' => [
                    ['route' => 'organization.settings.entity-types.index', 'label' => 'Entity Types', 'icon' => 'cube', 'match' => '/settings/entity-types'],
                    ['route' => 'organization.settings.relation-types.index', 'label' => 'Relation Types', 'icon' => 'arrows-right-left', 'match' => '/settings/relation-types'],
                    ['route' => 'organization.settings.signal-definitions.index', 'label' => 'Signaldefinitionen', 'icon' => 'bell-alert', 'match' => '/settings/signal-definitions'],
                    ['route' => 'organization.settings.inference-prompts.index', 'label' => 'Inference Prompts', 'icon' => 'cpu-chip', 'match' => '/settings/inference-prompts'],
                ],
            ],
        ];
    }

    public function render()
    {
        // Fallback in case mount() wasn't called (e.g. layout @include without Livewire lifecycle)
        if (empty($this->sections)) {
            $this->sections = $this->buildSections();
        }

        return view('organization::livewire.sidebar', [
            'sections' => $this->sections,
        ])->layout('platform::layouts.app');
    }
}
