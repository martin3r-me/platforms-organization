@php
    $patternOptions = [
        ['value' => 'threshold', 'label' => 'Threshold (Schwellenwert)'],
        ['value' => 'trend', 'label' => 'Trend (Richtung)'],
        ['value' => 'cross_dimension', 'label' => 'Cross-Dimension (Divergenz)'],
        ['value' => 'ratio', 'label' => 'Ratio (Verhältnis)'],
    ];
    $patternLabels = ['threshold' => 'Threshold', 'trend' => 'Trend', 'cross_dimension' => 'Cross-Dimension', 'ratio' => 'Ratio'];
    $thresholdOperators = collect(['>', '>=', '<', '<=', '==', '!='])->map(fn ($o) => ['value' => $o, 'label' => $o])->all();
    $ratioOperators = collect(['<', '<=', '>', '>='])->map(fn ($o) => ['value' => $o, 'label' => $o])->all();
    $directionOptions = [['value' => 'increasing', 'label' => 'Steigend'], ['value' => 'decreasing', 'label' => 'Fallend']];
    $relationshipOptions = [['value' => 'diverging', 'label' => 'Divergierend'], ['value' => 'converging', 'label' => 'Konvergierend']];
    $scopeOptions = [
        ['value' => 'all', 'label' => 'Alle Entities'],
        ['value' => 'entity_type', 'label' => 'Nach Entity-Typ'],
        ['value' => 'entity_ids', 'label' => 'Bestimmte Entity-IDs'],
        ['value' => 'subtree', 'label' => 'Subtree (ab Root-Entity)'],
    ];
    $frequencyOptions = [
        ['value' => 'every_snapshot', 'label' => 'Jeder Snapshot'],
        ['value' => 'daily', 'label' => 'Täglich'],
        ['value' => 'weekly', 'label' => 'Wöchentlich'],
    ];
    $frequencyLabels = collect($frequencyOptions)->pluck('label', 'value')->all();
    $severityOptions = [
        ['value' => 'info', 'label' => 'Info'],
        ['value' => 'warning', 'label' => 'Warning'],
        ['value' => 'critical', 'label' => 'Critical'],
    ];
    $severityVariant = fn ($s) => match ($s) { 'critical', 'algedonic' => 'danger', 'warning' => 'warning', default => 'info' };
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Signal-Definitionen" icon="heroicon-o-adjustments-horizontal" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Einstellungen" width="w-64" :defaultOpen="true" side="left">
            @include('organization::livewire.settings.partials.nav', ['aktiv' => 'signal-definitions'])
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organisation', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Signal-Definitionen'],
        ]">
            <x-slot name="left">
                <div class="flex items-center gap-3">
                    <div class="w-56">
                        <x-nx-input-text name="search" size="sm" placeholder="Suchen…" wire:model.live="search" />
                    </div>
                    <x-nx-input-checkbox label="Inaktive" wire:model.live="showInactive" />
                </div>
            </x-slot>
            <x-nx-button variant="primary" size="sm" wire:click="create">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neue Definition</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
        <x-nx-card flush>
            @if ($this->signalDefinitions->isEmpty())
                <x-nx-empty icon="heroicon-o-adjustments-horizontal">
                    Keine Signal-Definitionen gefunden.
                    <x-slot name="action">
                        <x-nx-button size="sm" wire:click="create">Definition anlegen</x-nx-button>
                    </x-slot>
                </x-nx-empty>
            @else
                <x-nx-table>
                    <x-nx-table-header>
                        <x-nx-table-header-cell>Name</x-nx-table-header-cell>
                        <x-nx-table-header-cell>Pattern</x-nx-table-header-cell>
                        <x-nx-table-header-cell>Severity</x-nx-table-header-cell>
                        <x-nx-table-header-cell>Frequenz</x-nx-table-header-cell>
                        <x-nx-table-header-cell align="right">Status</x-nx-table-header-cell>
                    </x-nx-table-header>
                    <x-nx-table-body>
                        @foreach ($this->signalDefinitions as $definition)
                            <x-nx-table-row wire:key="sd-{{ $definition->id }}" clickable :href="route('organization.settings.signal-definitions.show', $definition)">
                                <x-nx-table-cell>
                                    <div class="font-medium text-[color:var(--nx-text)]">{{ $definition->name }}</div>
                                    @if ($definition->description)
                                        <div class="text-xs text-[color:var(--nx-faint)]">{{ Str::limit($definition->description, 80) }}</div>
                                    @endif
                                </x-nx-table-cell>
                                <x-nx-table-cell>
                                    <x-nx-badge>{{ $patternLabels[$definition->pattern_type] ?? $definition->pattern_type }}</x-nx-badge>
                                </x-nx-table-cell>
                                <x-nx-table-cell>
                                    <x-nx-badge :variant="$severityVariant($definition->severity)">{{ ucfirst($definition->severity) }}</x-nx-badge>
                                </x-nx-table-cell>
                                <x-nx-table-cell>
                                    <span class="text-sm text-[color:var(--nx-muted)]">{{ $frequencyLabels[$definition->frequency] ?? $definition->frequency }}</span>
                                </x-nx-table-cell>
                                <x-nx-table-cell align="right">
                                    <x-nx-badge :variant="$definition->is_active ? 'success' : 'neutral'" dot>
                                        {{ $definition->is_active ? 'Aktiv' : 'Inaktiv' }}
                                    </x-nx-badge>
                                </x-nx-table-cell>
                            </x-nx-table-row>
                        @endforeach
                    </x-nx-table-body>
                </x-nx-table>
            @endif
        </x-nx-card>
    </x-ui-page-container>

    <x-nx-modal size="lg" wire:model="modalShow">
        <x-slot name="header">Neue Signal-Definition</x-slot>

        <form wire:submit.prevent="store" class="space-y-4">
            <x-nx-input-text name="name" label="Name" wire:model.live="form.name" errorKey="form.name" required placeholder="Name der Signal-Definition" />
            <x-nx-input-textarea name="description" label="Beschreibung" wire:model.live="form.description" errorKey="form.description" rows="2" placeholder="Optional" />

            <x-nx-input-select name="pattern_type" label="Pattern-Typ" :options="$patternOptions" wire:model.live="form.pattern_type" errorKey="form.pattern_type" />

            {{-- Bedingung je Pattern-Typ --}}
            @if ($form['pattern_type'] === 'threshold')
                <div class="space-y-3 rounded-[8px] border border-[color:var(--nx-line)] bg-[color:var(--nx-hover)] p-4">
                    <h4 class="m-0 text-sm font-semibold text-[color:var(--nx-text)]">Threshold-Bedingung</h4>
                    <x-nx-input-text name="conditionMetric" label="Metrik" wire:model.live="conditionMetric" placeholder="z. B. items_total" />
                    <x-nx-input-select name="conditionOperator" label="Operator" :options="$thresholdOperators" wire:model.live="conditionOperator" />
                    <x-nx-input-text name="conditionValue" label="Wert" wire:model.live="conditionValue" placeholder="z. B. 100" />
                </div>
            @elseif ($form['pattern_type'] === 'trend')
                <div class="space-y-3 rounded-[8px] border border-[color:var(--nx-line)] bg-[color:var(--nx-hover)] p-4">
                    <h4 class="m-0 text-sm font-semibold text-[color:var(--nx-text)]">Trend-Bedingung</h4>
                    <x-nx-input-text name="conditionMetric" label="Metrik" wire:model.live="conditionMetric" placeholder="z. B. time_total_minutes" />
                    <x-nx-input-select name="conditionDirection" label="Richtung" :options="$directionOptions" wire:model.live="conditionDirection" />
                    <div class="grid grid-cols-2 gap-3">
                        <x-nx-input-number name="conditionPeriods" label="Perioden" wire:model.live="conditionPeriods" min="2" max="10" />
                        <x-nx-input-number name="conditionMinChange" label="Min. Änderung (%)" wire:model.live="conditionMinChange" min="1" max="100" />
                    </div>
                </div>
            @elseif ($form['pattern_type'] === 'cross_dimension')
                <div class="space-y-3 rounded-[8px] border border-[color:var(--nx-line)] bg-[color:var(--nx-hover)] p-4">
                    <h4 class="m-0 text-sm font-semibold text-[color:var(--nx-text)]">Cross-Dimension-Bedingung</h4>
                    <div class="grid grid-cols-2 gap-3">
                        <x-nx-input-text name="conditionMetricA" label="Metrik A" wire:model.live="conditionMetricA" placeholder="z. B. revenue_total" />
                        <x-nx-input-text name="conditionMetricB" label="Metrik B" wire:model.live="conditionMetricB" placeholder="z. B. costs_total" />
                    </div>
                    <x-nx-input-select name="conditionRelationship" label="Beziehung" :options="$relationshipOptions" wire:model.live="conditionRelationship" />
                </div>
            @elseif ($form['pattern_type'] === 'ratio')
                <div class="space-y-3 rounded-[8px] border border-[color:var(--nx-line)] bg-[color:var(--nx-hover)] p-4">
                    <h4 class="m-0 text-sm font-semibold text-[color:var(--nx-text)]">Ratio-Bedingung</h4>
                    <div class="grid grid-cols-2 gap-3">
                        <x-nx-input-text name="conditionNumerator" label="Zähler" wire:model.live="conditionNumerator" placeholder="z. B. items_done" />
                        <x-nx-input-text name="conditionDenominator" label="Nenner" wire:model.live="conditionDenominator" placeholder="z. B. items_total" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <x-nx-input-select name="conditionOperator" label="Operator" :options="$ratioOperators" wire:model.live="conditionOperator" />
                        <x-nx-input-text name="conditionValue" label="Schwellenwert" wire:model.live="conditionValue" placeholder="z. B. 0.5" />
                    </div>
                </div>
            @endif

            <x-nx-input-select name="scope_type" label="Scope" :options="$scopeOptions" wire:model.live="form.scope_type" errorKey="form.scope_type" />

            @if ($form['scope_type'] !== 'all')
                <x-nx-input-text name="scopeValueInput" label="Scope-Werte (kommagetrennt)" wire:model.live="scopeValueInput"
                    placeholder="{{ match ($form['scope_type']) { 'entity_type' => 'z. B. department, team', 'entity_ids' => 'z. B. 1, 2, 3', 'subtree' => 'Root-Entity-ID', default => '' } }}" />
            @endif

            <div class="grid grid-cols-2 gap-3">
                <x-nx-input-select name="frequency" label="Frequenz" :options="$frequencyOptions" wire:model.live="form.frequency" errorKey="form.frequency" />
                <x-nx-input-select name="severity" label="Severity" :options="$severityOptions" wire:model.live="form.severity" errorKey="form.severity" />
            </div>

            <div class="flex flex-col items-start gap-2.5 border-t border-[color:var(--nx-line)] pt-3">
                <x-nx-input-checkbox label="Aktiv" wire:model.live="form.is_active" />
            </div>
        </form>

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-nx-button variant="ghost" wire:click="$set('modalShow', false)">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="store">Anlegen</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>
</x-ui-page>
