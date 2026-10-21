<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Inference-Prompts" icon="heroicon-o-sparkles" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Einstellungen" width="w-64" :defaultOpen="true" side="left">
            @include('organization::livewire.settings.partials.nav', ['aktiv' => 'inference-prompts'])
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organisation', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Inference-Prompts'],
        ]">
            <x-slot name="left">
                <div class="flex items-center gap-3">
                    <div class="w-56">
                        <x-nx-input-text name="search" size="sm" placeholder="Suchen…" wire:model.live.debounce.300ms="search" />
                    </div>
                    <div class="w-48">
                        <x-nx-input-select
                            name="vsmSystemFilter"
                            size="sm"
                            wire:model.live="vsmSystemFilter"
                            :options="\Platform\Organization\Livewire\Settings\InferencePrompt\Show::VSM_OPTIONS"
                            nullable
                            nullLabel="Alle Systeme"
                        />
                    </div>
                    <x-nx-input-checkbox label="Inaktive" wire:model.live="showInactive" />
                </div>
            </x-slot>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
        <x-nx-card flush>
            @if ($this->inferencePrompts->isEmpty())
                <x-nx-empty icon="heroicon-o-sparkles">
                    Keine Inference-Prompts gefunden.
                </x-nx-empty>
            @else
                <x-nx-table>
                    <x-nx-table-header>
                        <x-nx-table-header-cell>Name</x-nx-table-header-cell>
                        <x-nx-table-header-cell>VSM-System</x-nx-table-header-cell>
                        <x-nx-table-header-cell>Dimension</x-nx-table-header-cell>
                        <x-nx-table-header-cell>Severity</x-nx-table-header-cell>
                        <x-nx-table-header-cell>Scope</x-nx-table-header-cell>
                        <x-nx-table-header-cell>Letzte Auswertung</x-nx-table-header-cell>
                        <x-nx-table-header-cell align="right">Status</x-nx-table-header-cell>
                    </x-nx-table-header>
                    <x-nx-table-body>
                        @foreach ($this->inferencePrompts as $prompt)
                            <x-nx-table-row wire:key="ip-{{ $prompt->id }}" clickable :href="route('organization.settings.inference-prompts.show', $prompt)">
                                <x-nx-table-cell>
                                    <div class="font-medium text-[color:var(--nx-text)]">{{ $prompt->name }}</div>
                                    @if ($prompt->description)
                                        <div class="text-xs text-[color:var(--nx-faint)]">{{ \Illuminate\Support\Str::limit($prompt->description, 80) }}</div>
                                    @endif
                                </x-nx-table-cell>
                                <x-nx-table-cell>
                                    <x-nx-badge>{{ \Platform\Organization\Livewire\Settings\InferencePrompt\Show::VSM_OPTIONS[$prompt->vsm_system] ?? ($prompt->vsm_system ?? '–') }}</x-nx-badge>
                                </x-nx-table-cell>
                                <x-nx-table-cell>
                                    <span class="text-sm text-[color:var(--nx-muted)]">{{ $prompt->dimension ?? '–' }}</span>
                                </x-nx-table-cell>
                                <x-nx-table-cell>
                                    <x-nx-badge :variant="match ($prompt->default_severity) { 'critical' => 'danger', 'warning' => 'warning', default => 'info' }">
                                        {{ ucfirst($prompt->default_severity ?? 'info') }}
                                    </x-nx-badge>
                                </x-nx-table-cell>
                                <x-nx-table-cell>
                                    <span class="text-sm text-[color:var(--nx-muted)]">{{ \Platform\Organization\Livewire\Settings\InferencePrompt\Show::SCOPE_OPTIONS[$prompt->scope_type ?? 'all'] ?? $prompt->scope_type }}</span>
                                </x-nx-table-cell>
                                <x-nx-table-cell>
                                    <span class="text-sm tabular-nums text-[color:var(--nx-muted)]">
                                        {{ $prompt->last_evaluated_at ? $prompt->last_evaluated_at->format('d.m.Y H:i') : '–' }}
                                    </span>
                                </x-nx-table-cell>
                                <x-nx-table-cell align="right">
                                    <x-nx-badge :variant="$prompt->is_active ? 'success' : 'neutral'" dot>
                                        {{ $prompt->is_active ? 'Aktiv' : 'Inaktiv' }}
                                    </x-nx-badge>
                                </x-nx-table-cell>
                            </x-nx-table-row>
                        @endforeach
                    </x-nx-table-body>
                </x-nx-table>
            @endif
        </x-nx-card>
    </x-ui-page-container>
</x-ui-page>
