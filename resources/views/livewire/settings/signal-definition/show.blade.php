<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Settings'],
            ['label' => 'Signaldefinitionen', 'href' => route('organization.settings.signal-definitions.index')],
            ['label' => $signalDefinition->name],
        ]">
            @if($this->isDirty)
                <x-ui-button variant="secondary-ghost" size="sm" wire:click="loadForm">
                    @svg('heroicon-o-x-mark', 'w-4 h-4')
                    <span>Abbrechen</span>
                </x-ui-button>
                <x-ui-button variant="primary" size="sm" wire:click="save">
                    @svg('heroicon-o-check', 'w-4 h-4')
                    <span>Speichern</span>
                </x-ui-button>
            @endif
            <x-ui-confirm-button
                variant="danger-outline"
                size="sm"
                wire:click="delete"
                confirm-text="Signal-Definition wirklich löschen?"
            >
                @svg('heroicon-o-trash', 'w-4 h-4')
            </x-ui-confirm-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Informationen" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Status</h3>
                    <div class="space-y-3">
                        @if($signalDefinition->is_active)
                            <x-ui-badge variant="success" size="sm">Aktiv</x-ui-badge>
                        @else
                            <x-ui-badge variant="danger" size="sm">Inaktiv</x-ui-badge>
                        @endif
                    </div>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Details</h3>
                    <div class="space-y-3">
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Pattern-Typ</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">
                                <x-ui-badge variant="info">{{ $signalDefinition->pattern_type }}</x-ui-badge>
                            </div>
                        </div>
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Severity</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">
                                <x-ui-badge variant="{{ match($signalDefinition->severity) { 'critical' => 'danger', 'warning' => 'warning', default => 'info' } }}">
                                    {{ $signalDefinition->severity }}
                                </x-ui-badge>
                            </div>
                        </div>
                        @if($signalDefinition->description)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">Beschreibung</span>
                                <div class="text-sm text-[var(--ui-secondary)]">{{ $signalDefinition->description }}</div>
                            </div>
                        @endif
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Erstellt</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $signalDefinition->created_at->format('d.m.Y H:i') }}</div>
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
        <div class="space-y-6">
            <!-- Grunddaten -->
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Grunddaten</h2>
                <div class="space-y-4">
                    <x-ui-input-text name="name" label="Name" wire:model.live="form.name" required />
                    <x-ui-input-textarea name="description" label="Beschreibung" wire:model.live="form.description" rows="2" />

                    <div>
                        <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Pattern-Typ</label>
                        <select wire:model.live="form.pattern_type" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
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
                            <x-ui-input-text name="conditionValue" label="Wert" wire:model.live="conditionValue" />
                        </div>
                    @elseif($form['pattern_type'] === 'trend')
                        <div class="bg-[var(--ui-muted-5)] p-4 rounded-lg space-y-3">
                            <h4 class="text-sm font-semibold text-[var(--ui-secondary)]">Trend-Bedingung</h4>
                            <x-ui-input-text name="conditionMetric" label="Metrik" wire:model.live="conditionMetric" />
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
                            <x-ui-input-text name="conditionMetricA" label="Metrik A" wire:model.live="conditionMetricA" />
                            <x-ui-input-text name="conditionMetricB" label="Metrik B" wire:model.live="conditionMetricB" />
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
                            <x-ui-input-text name="conditionNumerator" label="Zähler" wire:model.live="conditionNumerator" />
                            <x-ui-input-text name="conditionDenominator" label="Nenner" wire:model.live="conditionDenominator" />
                            <div>
                                <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Operator</label>
                                <select wire:model.live="conditionOperator" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="<"><</option>
                                    <option value="<="><=</option>
                                    <option value=">">></option>
                                    <option value=">=">>=</option>
                                </select>
                            </div>
                            <x-ui-input-text name="conditionValue" label="Schwellenwert" wire:model.live="conditionValue" />
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
                        <x-ui-input-text name="scopeValueInput" label="Scope-Werte (kommagetrennt)" wire:model.live="scopeValueInput" />
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
                        <input type="checkbox" wire:model.live="form.is_active" id="is_active" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                        <label for="is_active" class="ml-2 text-sm text-[var(--ui-secondary)]">Aktiv</label>
                    </div>
                </div>
            </div>

            <!-- Letzte Signale -->
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Letzte Signale</h2>
                @if($this->recentSignals->isEmpty())
                    <p class="text-sm text-[var(--ui-muted)]">Keine Signale vorhanden.</p>
                @else
                    <x-ui-table compact="true">
                        <x-ui-table-header>
                            <x-ui-table-header-cell compact="true">Entity</x-ui-table-header-cell>
                            <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
                            <x-ui-table-header-cell compact="true">Severity</x-ui-table-header-cell>
                            <x-ui-table-header-cell compact="true">Nachricht</x-ui-table-header-cell>
                            <x-ui-table-header-cell compact="true">Erstellt</x-ui-table-header-cell>
                        </x-ui-table-header>
                        <x-ui-table-body>
                            @foreach($this->recentSignals as $signal)
                                <x-ui-table-row compact="true">
                                    <x-ui-table-cell compact="true">
                                        <span class="text-sm font-medium">{{ $signal->entity?->name ?? '-' }}</span>
                                    </x-ui-table-cell>
                                    <x-ui-table-cell compact="true">
                                        <x-ui-badge variant="{{ match($signal->status) { 'open' => 'warning', 'acknowledged' => 'info', 'resolved' => 'success', 'dismissed' => 'muted', default => 'muted' } }}">
                                            {{ $signal->status }}
                                        </x-ui-badge>
                                    </x-ui-table-cell>
                                    <x-ui-table-cell compact="true">
                                        <x-ui-badge variant="{{ match($signal->severity) { 'critical' => 'danger', 'warning' => 'warning', default => 'info' } }}">
                                            {{ $signal->severity }}
                                        </x-ui-badge>
                                    </x-ui-table-cell>
                                    <x-ui-table-cell compact="true">
                                        <span class="text-sm text-[var(--ui-secondary)]">{{ Str::limit($signal->message, 80) }}</span>
                                    </x-ui-table-cell>
                                    <x-ui-table-cell compact="true">
                                        <span class="text-xs text-[var(--ui-muted)]">{{ $signal->created_at->format('d.m. H:i') }}</span>
                                    </x-ui-table-cell>
                                </x-ui-table-row>
                            @endforeach
                        </x-ui-table-body>
                    </x-ui-table>
                @endif
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>
