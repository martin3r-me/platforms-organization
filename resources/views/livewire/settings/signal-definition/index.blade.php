<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Settings'],
            ['label' => 'Signaldefinitionen'],
        ]">
            <x-ui-button variant="primary" size="sm" wire:click="create">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neue Definition</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Suche</h3>
                    <x-ui-input-text name="search" wire:model.live="search" placeholder="Suche Signaldefinitionen..." class="w-full" size="sm" />
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Filter</h3>
                    <div class="space-y-3">
                        <div class="flex items-center">
                            <input type="checkbox" wire:model.live="showInactive" id="showInactive" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                            <label for="showInactive" class="ml-2 text-sm text-[var(--ui-secondary)]">Inaktive anzeigen</label>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-6 text-sm text-[var(--ui-muted)]">Keine Aktivitäten verfügbar</div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container>
        <x-ui-table compact="true">
            <x-ui-table-header>
                <x-ui-table-header-cell compact="true">Name</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Pattern</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Severity</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Frequenz</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Aktionen</x-ui-table-header-cell>
            </x-ui-table-header>

            <x-ui-table-body>
                @foreach($this->signalDefinitions as $definition)
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true">
                            <a href="{{ route('organization.settings.signal-definitions.show', $definition) }}" class="link font-medium">
                                {{ $definition->name }}
                            </a>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <x-ui-badge variant="info">{{ $definition->pattern_type }}</x-ui-badge>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <x-ui-badge variant="{{ match($definition->severity) { 'critical' => 'danger', 'warning' => 'warning', default => 'info' } }}">
                                {{ $definition->severity }}
                            </x-ui-badge>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="text-sm text-[var(--ui-secondary)]">{{ $definition->frequency }}</span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <x-ui-badge variant="{{ $definition->is_active ? 'success' : 'muted' }}">
                                {{ $definition->is_active ? 'Aktiv' : 'Inaktiv' }}
                            </x-ui-badge>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <a href="{{ route('organization.settings.signal-definitions.show', $definition) }}" class="text-[var(--ui-primary)] hover:underline text-sm">
                                Bearbeiten
                            </a>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @endforeach
            </x-ui-table-body>
        </x-ui-table>
    </x-ui-page-container>

    <!-- Create Modal -->
    <x-ui-modal wire:model="modalShow" size="lg">
        <x-slot name="header">
            Neue Signal-Definition erstellen
        </x-slot>

        <div class="space-y-4">
            <form wire:submit.prevent="store" class="space-y-4">
                <x-ui-input-text name="name" label="Name" wire:model.live="form.name" required placeholder="Name der Signal-Definition" />
                <x-ui-input-textarea name="description" label="Beschreibung" wire:model.live="form.description" placeholder="Optionale Beschreibung" rows="2" />

                <div>
                    <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Pattern-Typ</label>
                    <select wire:model.live="form.pattern_type" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 text-sm">
                        <option value="threshold">Threshold (Schwellenwert)</option>
                        <option value="trend">Trend (Richtung)</option>
                        <option value="cross_dimension">Cross-Dimension (Divergenz)</option>
                        <option value="ratio">Ratio (Verhältnis)</option>
                    </select>
                </div>

                <!-- Dynamic condition fields -->
                @if($form['pattern_type'] === 'threshold')
                    <div class="bg-[var(--ui-muted-5)] p-4 rounded-lg space-y-3">
                        <h4 class="text-sm font-semibold text-[var(--ui-secondary)]">Threshold-Bedingung</h4>
                        <x-ui-input-text name="conditionMetric" label="Metrik" wire:model.live="conditionMetric" placeholder="z.B. items_total" />
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Operator</label>
                            <select wire:model.live="conditionOperator" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value=">">></option>
                                <option value=">=">>=</option>
                                <option value="<"><</option>
                                <option value="<="><=</option>
                                <option value="==">==</option>
                                <option value="!=">!=</option>
                            </select>
                        </div>
                        <x-ui-input-text name="conditionValue" label="Wert" wire:model.live="conditionValue" placeholder="z.B. 100" />
                    </div>
                @elseif($form['pattern_type'] === 'trend')
                    <div class="bg-[var(--ui-muted-5)] p-4 rounded-lg space-y-3">
                        <h4 class="text-sm font-semibold text-[var(--ui-secondary)]">Trend-Bedingung</h4>
                        <x-ui-input-text name="conditionMetric" label="Metrik" wire:model.live="conditionMetric" placeholder="z.B. time_total_minutes" />
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Richtung</label>
                            <select wire:model.live="conditionDirection" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="increasing">Steigend</option>
                                <option value="decreasing">Fallend</option>
                            </select>
                        </div>
                        <x-ui-input-number name="conditionPeriods" label="Perioden" wire:model.live="conditionPeriods" min="2" max="10" />
                        <x-ui-input-number name="conditionMinChange" label="Min. Änderung (%)" wire:model.live="conditionMinChange" min="1" max="100" />
                    </div>
                @elseif($form['pattern_type'] === 'cross_dimension')
                    <div class="bg-[var(--ui-muted-5)] p-4 rounded-lg space-y-3">
                        <h4 class="text-sm font-semibold text-[var(--ui-secondary)]">Cross-Dimension-Bedingung</h4>
                        <x-ui-input-text name="conditionMetricA" label="Metrik A" wire:model.live="conditionMetricA" placeholder="z.B. revenue_total" />
                        <x-ui-input-text name="conditionMetricB" label="Metrik B" wire:model.live="conditionMetricB" placeholder="z.B. costs_total" />
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Beziehung</label>
                            <select wire:model.live="conditionRelationship" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="diverging">Divergierend</option>
                                <option value="converging">Konvergierend</option>
                            </select>
                        </div>
                    </div>
                @elseif($form['pattern_type'] === 'ratio')
                    <div class="bg-[var(--ui-muted-5)] p-4 rounded-lg space-y-3">
                        <h4 class="text-sm font-semibold text-[var(--ui-secondary)]">Ratio-Bedingung</h4>
                        <x-ui-input-text name="conditionNumerator" label="Zähler" wire:model.live="conditionNumerator" placeholder="z.B. items_done" />
                        <x-ui-input-text name="conditionDenominator" label="Nenner" wire:model.live="conditionDenominator" placeholder="z.B. items_total" />
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Operator</label>
                            <select wire:model.live="conditionOperator" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="<"><</option>
                                <option value="<="><=</option>
                                <option value=">">></option>
                                <option value=">=">>=</option>
                            </select>
                        </div>
                        <x-ui-input-text name="conditionValue" label="Schwellenwert" wire:model.live="conditionValue" placeholder="z.B. 0.5" />
                    </div>
                @endif

                <div>
                    <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Scope</label>
                    <select wire:model.live="form.scope_type" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="all">Alle Entities</option>
                        <option value="entity_type">Nach Entity-Typ</option>
                        <option value="entity_ids">Bestimmte Entity-IDs</option>
                        <option value="subtree">Subtree (ab Root-Entity)</option>
                    </select>
                </div>

                @if($form['scope_type'] !== 'all')
                    <x-ui-input-text name="scopeValueInput" label="Scope-Werte (kommagetrennt)" wire:model.live="scopeValueInput" placeholder="{{ match($form['scope_type']) { 'entity_type' => 'z.B. department, team', 'entity_ids' => 'z.B. 1, 2, 3', 'subtree' => 'Root-Entity-ID', default => '' } }}" />
                @endif

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Frequenz</label>
                        <select wire:model.live="form.frequency" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="every_snapshot">Jeder Snapshot</option>
                            <option value="daily">Täglich</option>
                            <option value="weekly">Wöchentlich</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Severity</label>
                        <select wire:model.live="form.severity" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="info">Info</option>
                            <option value="warning">Warning</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center">
                    <input type="checkbox" wire:model.live="form.is_active" id="create_is_active" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                    <label for="create_is_active" class="ml-2 text-sm text-[var(--ui-secondary)]">Aktiv</label>
                </div>
            </form>
        </div>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button type="button" variant="secondary-outline" wire:click="$set('modalShow', false)">
                    Abbrechen
                </x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="store">
                    @svg('heroicon-o-plus', 'w-4 h-4 mr-2')
                    Erstellen
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>
</x-ui-page>
