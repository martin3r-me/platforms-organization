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
     * Sidebar navigation structure.
     * Each section has a label and a list of items.
     * Each item: route, label, icon, match (URL substring used for active highlighting).
     */
    protected function buildSections(): array
    {
        return [
            [
                'label' => 'Arbeiten',
                'items' => [
                    ['route' => 'organization.dashboard', 'label' => 'Dashboard', 'icon' => 'chart-bar', 'match' => '/organization$|/organization/$'],
                    ['route' => 'organization.signals.index', 'label' => 'Signale', 'icon' => 'bell-alert', 'match' => '/signals'],
                    ['route' => 'organization.my-inquiries.index', 'label' => 'Meine Inquiries', 'icon' => 'inbox', 'match' => '/my-inquiries'],
                    ['route' => 'organization.entities.index', 'label' => 'Organisationseinheiten', 'icon' => 'building-office', 'match' => '/entities'],
                ],
            ],
            [
                'label' => 'Struktur',
                'items' => [
                    ['route' => 'organization.interlinks.index', 'label' => 'Interlinks', 'icon' => 'arrows-right-left', 'match' => '/interlinks'],
                    ['route' => 'organization.sla-contracts.index', 'label' => 'SLA-Verträge', 'icon' => 'shield-check', 'match' => '/sla-contracts'],
                    ['route' => 'organization.job-profiles.index', 'label' => 'Jobprofile', 'icon' => 'identification', 'match' => '/job-profiles'],
                    ['route' => 'organization.roles.index', 'label' => 'Rollen', 'icon' => 'user-group', 'match' => '/roles'],
                    ['route' => 'organization.skills.index', 'label' => 'Skills', 'icon' => 'academic-cap', 'match' => '/skills'],
                ],
            ],
            [
                'label' => 'Zeit & Kosten',
                'items' => [
                    ['route' => 'organization.time-entries.index', 'label' => 'Ist-Zeiten', 'icon' => 'clock', 'match' => '/time-entries'],
                    ['route' => 'organization.planned-times.index', 'label' => 'Geplante Zeiten', 'icon' => 'calendar', 'match' => '/planned-times'],
                    ['route' => 'organization.cost-centers.index', 'label' => 'Kostenstellen', 'icon' => 'currency-dollar', 'match' => '/cost-centers'],
                ],
            ],
            [
                'label' => 'Umwelt & Inference',
                'items' => [
                    ['route' => 'organization.environment-sources.index', 'label' => 'Umwelt-Quellen', 'icon' => 'globe-alt', 'match' => '/environment-sources'],
                    ['route' => 'organization.environment-snapshots.index', 'label' => 'Umwelt-Snapshots', 'icon' => 'camera', 'match' => '/environment-snapshots'],
                    ['route' => 'organization.memory.index', 'label' => 'Memory', 'icon' => 'circle-stack', 'match' => '/memory'],
                    ['route' => 'organization.inference-runs.index', 'label' => 'Inference Runs', 'icon' => 'play', 'match' => '/inference-runs'],
                    ['route' => 'organization.inquiries.index', 'label' => 'Inquiries (Admin)', 'icon' => 'question-mark-circle', 'match' => '/inquiries'],
                    ['route' => 'organization.synthesis-reports.index', 'label' => 'Synthesis Reports', 'icon' => 'document-text', 'match' => '/synthesis-reports'],
                ],
            ],
            [
                'label' => 'Einstellungen',
                'items' => [
                    ['route' => 'organization.settings.entity-types.index', 'label' => 'Entity Types', 'icon' => 'cube', 'match' => '/settings/entity-types'],
                    ['route' => 'organization.settings.relation-types.index', 'label' => 'Relation Types', 'icon' => 'arrows-right-left', 'match' => '/settings/relation-types'],
                    ['route' => 'organization.settings.inference-prompts.index', 'label' => 'Inference Prompts', 'icon' => 'cpu-chip', 'match' => '/settings/inference-prompts'],
                    ['route' => 'organization.settings.synthesis-prompts.index', 'label' => 'Synthesis Prompts', 'icon' => 'document-text', 'match' => '/settings/synthesis-prompts'],
                    ['route' => 'organization.settings.signal-definitions.index', 'label' => 'Signaldefinitionen', 'icon' => 'bell-alert', 'match' => '/settings/signal-definitions'],
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
