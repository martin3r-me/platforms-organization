@php
    $reportTypeOptions = [
        'weekly' => 'Wöchentlich',
        'monthly' => 'Monatlich',
        'quarterly' => 'Quartalsweise',
    ];
@endphp
<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$definition->name" icon="heroicon-o-chat-bubble-left-right" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Einstellungen" width="w-64" :defaultOpen="true" side="left">
            @include('organization::livewire.settings.partials.nav', ['aktiv' => 'synthesis-prompts'])
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organisation', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Synthese-Prompts', 'href' => route('organization.settings.synthesis-prompts.index')],
            ['label' => $definition->name],
        ]">
            <div class="flex items-center gap-2">
                <x-nx-button variant="primary" size="sm" wire:click="save">
                    @svg('heroicon-o-check', 'w-4 h-4')
                    <span>Speichern</span>
                </x-nx-button>
            </div>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
        <div class="space-y-5">
            {{-- Kopf: Identität + Zustand --}}
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="m-0 text-2xl font-semibold tracking-tight text-[color:var(--nx-text)]">{{ $definition->name }}</h1>
                <x-nx-badge>{{ $reportTypeOptions[$definition->report_type] ?? $definition->report_type }}</x-nx-badge>
                <x-nx-badge :variant="$definition->is_active ? 'success' : 'neutral'" dot>{{ $definition->is_active ? 'Aktiv' : 'Inaktiv' }}</x-nx-badge>
                <span class="ml-auto text-xs text-[color:var(--nx-faint)]">angelegt {{ $definition->created_at?->format('d.m.Y') }}</span>
            </div>

            @if ($savedMessage)
                <x-nx-callout variant="success" icon="heroicon-o-check-circle">{{ $savedMessage }}</x-nx-callout>
            @endif

            <x-nx-panel title="Grunddaten">
                <div class="space-y-3 p-4">
                    <x-nx-input-text name="name" label="Name" wire:model="name" errorKey="name" required />
                    <x-nx-input-text name="description" label="Beschreibung" wire:model="description" errorKey="description" placeholder="Kurze Notiz, wofür dieser Prompt verwendet wird (optional)" />
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <x-nx-input-select name="report_type" label="Report-Typ" wire:model="report_type" errorKey="report_type" :options="$reportTypeOptions" required />
                        <div class="flex items-end pb-2">
                            <x-nx-input-checkbox label="Prompt ist aktiv" wire:model="is_active" />
                        </div>
                    </div>
                </div>
            </x-nx-panel>

            <x-nx-panel title="LLM-Einstellungen">
                <div class="grid grid-cols-1 gap-3 p-4 md:grid-cols-3">
                    <x-nx-input-text name="model" label="Model" hint="leer = Default" wire:model="model" errorKey="model" class="font-mono" placeholder="claude-sonnet-4-6" />
                    <x-nx-input-number name="max_signals" label="Max. Signale" wire:model="max_signals" errorKey="max_signals" min="1" max="1000" required />
                    <x-nx-input-number name="max_tokens" label="Max. Tokens" wire:model="max_tokens" errorKey="max_tokens" min="512" max="32768" step="512" required />
                </div>
            </x-nx-panel>

            <x-nx-panel title="System-Prompt" subtitle="Definiert Rolle und Aufgabe des LLM (z. B. „Du bist ein strategischer Analyst…“). Markdown im Output.">
                <x-slot name="actions">
                    <x-nx-button variant="ghost" size="sm" type="button" wire:click="resetToDefault('system_prompt')">
                        @svg('heroicon-o-arrow-uturn-left', 'w-3.5 h-3.5')
                        <span>Auf Default zurücksetzen</span>
                    </x-nx-button>
                </x-slot>
                <div class="p-4">
                    <x-nx-input-textarea name="system_prompt" wire:model="system_prompt" errorKey="system_prompt" rows="16" class="font-mono" />
                </div>
            </x-nx-panel>

            <x-nx-panel title="User-Message-Template" subtitle="Die Nachricht an das LLM">
                <x-slot name="actions">
                    <x-nx-button variant="ghost" size="sm" type="button" wire:click="resetToDefault('user_message_template')">
                        @svg('heroicon-o-arrow-uturn-left', 'w-3.5 h-3.5')
                        <span>Auf Default zurücksetzen</span>
                    </x-nx-button>
                </x-slot>
                <div class="space-y-3 p-4">
                    <x-nx-callout variant="neutral" icon="heroicon-o-information-circle">
                        Verfügbare Platzhalter:
                        <code class="font-mono text-xs">{report_type}</code>
                        <code class="font-mono text-xs">{period_start}</code>
                        <code class="font-mono text-xs">{period_end}</code>
                        <code class="font-mono text-xs">{signals_count}</code>
                        <code class="font-mono text-xs">{signals_json}</code>
                    </x-nx-callout>
                    <x-nx-input-textarea name="user_message_template" wire:model="user_message_template" errorKey="user_message_template" rows="16" class="font-mono" />
                </div>
            </x-nx-panel>
        </div>
    </x-ui-page-container>
</x-ui-page>
