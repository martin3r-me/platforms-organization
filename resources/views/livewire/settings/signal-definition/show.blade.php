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
    $severityOptions = [
        ['value' => 'info', 'label' => 'Info'],
        ['value' => 'warning', 'label' => 'Warning'],
        ['value' => 'critical', 'label' => 'Critical'],
    ];
    $severityVariant = fn ($s) => match ($s) { 'critical', 'algedonic' => 'danger', 'warning' => 'warning', default => 'info' };
    $signalStatusVariant = fn ($s) => match ($s) { 'open' => 'warning', 'acknowledged' => 'info', 'resolved' => 'success', default => 'neutral' };
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$signalDefinition->name" icon="heroicon-o-adjustments-horizontal" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Einstellungen" width="w-64" :defaultOpen="true" side="left">
            @include('organization::livewire.settings.partials.nav', ['aktiv' => 'signal-definitions'])
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organisation', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Signal-Definitionen', 'href' => route('organization.settings.signal-definitions.index')],
            ['label' => $signalDefinition->name],
        ]">
            <div class="flex items-center gap-2">
                @if ($this->isDirty)
                    <x-nx-button variant="primary" size="sm" wire:click="save">
                        @svg('heroicon-o-check', 'w-4 h-4')
                        <span>Speichern</span>
                    </x-nx-button>
                @endif
                <x-nx-dropdown>
                    @if ($this->isDirty)
                        <x-nx-dropdown-item wire:click="loadForm">
                            @svg('heroicon-o-arrow-uturn-left', 'w-4 h-4')
                            Änderungen verwerfen
                        </x-nx-dropdown-item>
                        <x-nx-dropdown-divider />
                    @endif
                    <x-nx-dropdown-item variant="danger" wire:click="delete" wire:confirm="Signal-Definition „{{ $signalDefinition->name }}“ wirklich löschen?">
                        @svg('heroicon-o-trash', 'w-4 h-4')
                        Löschen
                    </x-nx-dropdown-item>
                </x-nx-dropdown>
            </div>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
        <div class="space-y-5">
            {{-- Kopf: Identität + Zustand --}}
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="m-0 text-2xl font-semibold tracking-tight text-[color:var(--nx-text)]">{{ $signalDefinition->name }}</h1>
                <x-nx-badge>{{ $patternLabels[$signalDefinition->pattern_type] ?? $signalDefinition->pattern_type }}</x-nx-badge>
                <x-nx-badge :variant="$severityVariant($signalDefinition->severity)">{{ ucfirst($signalDefinition->severity) }}</x-nx-badge>
                <x-nx-badge :variant="$signalDefinition->is_active ? 'success' : 'neutral'" dot>{{ $signalDefinition->is_active ? 'Aktiv' : 'Inaktiv' }}</x-nx-badge>
                <span class="ml-auto text-xs text-[color:var(--nx-faint)]">angelegt {{ $signalDefinition->created_at->format('d.m.Y') }}</span>
            </div>

            <x-nx-panel title="Grunddaten">
                <div class="space-y-3 p-4">
                    <x-nx-input-text name="name" label="Name" wire:model.live="form.name" errorKey="form.name" required />
                    <x-nx-input-textarea name="description" label="Beschreibung" wire:model.live="form.description" errorKey="form.description" rows="2" />
                </div>
            </x-nx-panel>

            <x-nx-panel title="Bedingung" subtitle="Wann das Signal ausgelöst wird">
                <div class="space-y-3 p-4">
                    <x-nx-input-select name="pattern_type" label="Pattern-Typ" :options="$patternOptions" wire:model.live="form.pattern_type" errorKey="form.pattern_type" />

                    @if ($form['pattern_type'] === 'threshold')
                        <div class="grid grid-cols-3 gap-3">
                            <x-nx-input-text name="conditionMetric" label="Metrik" wire:model.live="conditionMetric" placeholder="z. B. items_total" />
                            <x-nx-input-select name="conditionOperator" label="Operator" :options="$thresholdOperators" wire:model.live="conditionOperator" />
                            <x-nx-input-text name="conditionValue" label="Wert" wire:model.live="conditionValue" />
                        </div>
                    @elseif ($form['pattern_type'] === 'trend')
                        <div class="grid grid-cols-2 gap-3">
                            <x-nx-input-text name="conditionMetric" label="Metrik" wire:model.live="conditionMetric" />
                            <x-nx-input-select name="conditionDirection" label="Richtung" :options="$directionOptions" wire:model.live="conditionDirection" />
                            <x-nx-input-number name="conditionPeriods" label="Perioden" wire:model.live="conditionPeriods" min="2" max="10" />
                            <x-nx-input-number name="conditionMinChange" label="Min. Änderung (%)" wire:model.live="conditionMinChange" min="1" max="100" />
                        </div>
                    @elseif ($form['pattern_type'] === 'cross_dimension')
                        <div class="grid grid-cols-3 gap-3">
                            <x-nx-input-text name="conditionMetricA" label="Metrik A" wire:model.live="conditionMetricA" />
                            <x-nx-input-text name="conditionMetricB" label="Metrik B" wire:model.live="conditionMetricB" />
                            <x-nx-input-select name="conditionRelationship" label="Beziehung" :options="$relationshipOptions" wire:model.live="conditionRelationship" />
                        </div>
                    @elseif ($form['pattern_type'] === 'ratio')
                        <div class="grid grid-cols-2 gap-3">
                            <x-nx-input-text name="conditionNumerator" label="Zähler" wire:model.live="conditionNumerator" />
                            <x-nx-input-text name="conditionDenominator" label="Nenner" wire:model.live="conditionDenominator" />
                            <x-nx-input-select name="conditionOperator" label="Operator" :options="$ratioOperators" wire:model.live="conditionOperator" />
                            <x-nx-input-text name="conditionValue" label="Schwellenwert" wire:model.live="conditionValue" />
                        </div>
                    @endif
                </div>
            </x-nx-panel>

            <x-nx-panel title="Geltungsbereich & Auswertung">
                <div class="space-y-3 p-4">
                    <x-nx-input-select name="scope_type" label="Scope" :options="$scopeOptions" wire:model.live="form.scope_type" errorKey="form.scope_type" />

                    @if ($form['scope_type'] !== 'all')
                        <x-nx-input-text name="scopeValueInput" label="Scope-Werte (kommagetrennt)" wire:model.live="scopeValueInput" />
                    @endif

                    <div class="grid grid-cols-2 gap-3">
                        <x-nx-input-select name="frequency" label="Frequenz" :options="$frequencyOptions" wire:model.live="form.frequency" errorKey="form.frequency" />
                        <x-nx-input-select name="severity" label="Severity" :options="$severityOptions" wire:model.live="form.severity" errorKey="form.severity" />
                    </div>

                    <div class="flex flex-col items-start gap-2.5 border-t border-[color:var(--nx-line)] pt-3">
                        <x-nx-input-checkbox label="Aktiv" wire:model.live="form.is_active" />
                    </div>
                </div>
            </x-nx-panel>

            <x-nx-panel title="Letzte Signale" subtitle="Die zehn jüngsten Auslösungen" flush>
                @if ($this->recentSignals->isEmpty())
                    <x-nx-empty icon="heroicon-o-signal">Keine Signale vorhanden.</x-nx-empty>
                @else
                    <x-nx-table>
                        <x-nx-table-header>
                            <x-nx-table-header-cell>Entity</x-nx-table-header-cell>
                            <x-nx-table-header-cell>Status</x-nx-table-header-cell>
                            <x-nx-table-header-cell>Severity</x-nx-table-header-cell>
                            <x-nx-table-header-cell>Nachricht</x-nx-table-header-cell>
                            <x-nx-table-header-cell align="right">Erstellt</x-nx-table-header-cell>
                        </x-nx-table-header>
                        <x-nx-table-body>
                            @foreach ($this->recentSignals as $signal)
                                <x-nx-table-row wire:key="sig-{{ $signal->id }}">
                                    <x-nx-table-cell>
                                        <span class="font-medium text-[color:var(--nx-text)]">{{ $signal->entity?->name ?? '–' }}</span>
                                    </x-nx-table-cell>
                                    <x-nx-table-cell>
                                        <x-nx-badge :variant="$signalStatusVariant($signal->status)" dot>{{ $signal->status }}</x-nx-badge>
                                    </x-nx-table-cell>
                                    <x-nx-table-cell>
                                        <x-nx-badge :variant="$severityVariant($signal->severity)">{{ ucfirst($signal->severity) }}</x-nx-badge>
                                    </x-nx-table-cell>
                                    <x-nx-table-cell>
                                        <span class="text-sm text-[color:var(--nx-muted)]">{{ Str::limit($signal->message, 80) }}</span>
                                    </x-nx-table-cell>
                                    <x-nx-table-cell align="right">
                                        <span class="text-xs text-[color:var(--nx-faint)]">{{ $signal->created_at->format('d.m. H:i') }}</span>
                                    </x-nx-table-cell>
                                </x-nx-table-row>
                            @endforeach
                        </x-nx-table-body>
                    </x-nx-table>
                @endif
            </x-nx-panel>
        </div>
    </x-ui-page-container>
</x-ui-page>
