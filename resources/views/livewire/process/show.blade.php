<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Prozesse', 'href' => route('organization.processes.index')],
            ['label' => $process->name],
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
                confirm-text="Prozess wirklich löschen?"
            >
                @svg('heroicon-o-trash', 'w-4 h-4')
            </x-ui-confirm-button>
        </x-ui-page-actionbar>
        @php
            $tabItems = [
                ['value' => 'details', 'label' => 'Details'],
                ['value' => 'corefit', 'label' => 'COREFIT'],
                ['value' => 'steps', 'label' => 'Steps', 'count' => $this->steps->count()],
                ['value' => 'flows', 'label' => 'Flows', 'count' => $this->flows->count()],
                ['value' => 'triggers', 'label' => 'Triggers', 'count' => $this->triggers->count()],
                ['value' => 'outputs', 'label' => 'Outputs', 'count' => $this->outputs->count()],
                ['value' => 'improvements', 'label' => 'Verbesserungen', 'count' => $this->processImprovements->count()],
                ['value' => 'snapshots', 'label' => 'Snapshots', 'count' => $this->processSnapshots->count()],
            ];
        @endphp
        <div class="px-4 py-2 bg-[var(--ui-surface)] border-b border-[var(--ui-border)]/40">
            <x-ui-tab :tabs="$tabItems" model="activeTab" :showCounts="true" />
        </div>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Informationen" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Status</h3>
                    <div class="space-y-2">
                        @if($process->status === 'active')
                            <x-ui-badge variant="success" size="sm">Aktiv</x-ui-badge>
                        @elseif($process->status === 'draft')
                            <x-ui-badge variant="muted" size="sm">Entwurf</x-ui-badge>
                        @else
                            <x-ui-badge variant="danger" size="sm">Veraltet</x-ui-badge>
                        @endif
                        @if($process->is_active)
                            <x-ui-badge variant="info" size="sm">Aktiv geschaltet</x-ui-badge>
                        @endif
                    </div>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Details</h3>
                    <div class="space-y-3">
                        @if($process->code)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">Code</span>
                                <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $process->code }}</div>
                            </div>
                        @endif
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Version</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $process->version ?? 1 }}</div>
                        </div>
                        @if($process->ownerEntity)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">Owner</span>
                                <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $process->ownerEntity->name }}</div>
                            </div>
                        @endif
                        @if($process->vsmSystem)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">VSM System</span>
                                <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $process->vsmSystem->name }}</div>
                            </div>
                        @endif
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Erstellt</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $process->created_at->format('d.m.Y H:i') }}</div>
                        </div>
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Aktualisiert</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $process->updated_at->format('d.m.Y H:i') }}</div>
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
        {{-- ── Tab: Details ────────────────────────────────── --}}
        @if($activeTab === 'details')
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-1">Grunddaten</h2>
                <p class="text-xs text-[var(--ui-muted)] mb-4">Allgemeine Informationen zum Prozess. Name und Status sind Pflichtfelder.</p>
                <div class="space-y-4">
                    <x-ui-input-text name="name" label="Name" wire:model.live="form.name" required placeholder="Aussagekräftiger Prozessname" />
                    <x-ui-input-text name="code" label="Code" wire:model.live="form.code" placeholder="Optionales Kürzel, z.B. PRO-001" />
                    <x-ui-input-textarea name="description" label="Beschreibung" wire:model.live="form.description" placeholder="Kurze Zusammenfassung: Was macht dieser Prozess und warum existiert er?" />

                    <div class="grid grid-cols-2 gap-4">
                        <x-ui-input-select
                            name="status"
                            label="Status"
                            :options="[
                                ['value' => 'draft', 'label' => 'Entwurf'],
                                ['value' => 'active', 'label' => 'Aktiv'],
                                ['value' => 'deprecated', 'label' => 'Veraltet'],
                            ]"
                            wire:model.live="form.status"
                        />
                        <x-ui-input-text name="version" label="Version" type="number" wire:model.live="form.version" min="1" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-ui-input-select
                            name="owner_entity_id"
                            label="Owner (Entity)"
                            :options="$this->groupedEntityOptions"
                            nullable
                            nullLabel="– Kein Owner –"
                            wire:model.live="form.owner_entity_id"
                        />
                        <x-ui-input-select
                            name="vsm_system_id"
                            label="VSM System"
                            :options="$this->availableVsmSystems->map(fn($v) => ['value' => (string) $v->id, 'label' => $v->name])->toArray()"
                            nullable
                            nullLabel="– Kein VSM System –"
                            wire:model.live="form.vsm_system_id"
                        />
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" wire:model.live="form.is_active" id="is_active" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                        <label for="is_active" class="ml-2 text-sm text-[var(--ui-secondary)]">Aktiv geschaltet</label>
                    </div>
                </div>
            </div>
        @endif

        {{-- ── Tab: COREFIT ────────────────────────────────── --}}
        @if($activeTab === 'corefit')
            @php $metrics = $this->corefitMetrics; @endphp

            {{-- Einführung --}}
            <div class="bg-[var(--ui-info-5)] border border-[var(--ui-info-20)] rounded-lg p-4 mb-6">
                <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-1">Was ist COREFIT?</h3>
                <p class="text-sm text-[var(--ui-muted)]">
                    COREFIT klassifiziert jeden Prozessschritt nach seinem Wertbeitrag. Ziel: Core maximieren, Context reduzieren, No Fit eliminieren.
                </p>
                <div class="mt-2 flex flex-wrap gap-4 text-xs text-[var(--ui-muted)]">
                    <span><span class="inline-block w-2 h-2 rounded-full bg-[var(--ui-success)] mr-1"></span><strong>Core</strong> — Direkte Wertschöpfung für den Kunden</span>
                    <span><span class="inline-block w-2 h-2 rounded-full bg-[var(--ui-warning)] mr-1"></span><strong>Context</strong> — Notwendig, aber kein direkter Kundenwert (Admin, Compliance)</span>
                    <span><span class="inline-block w-2 h-2 rounded-full bg-[var(--ui-danger)] mr-1"></span><strong>No Fit</strong> — Kein Wertbeitrag, sollte eliminiert werden</span>
                </div>
            </div>

            {{-- Metriken-Kacheln --}}
            <div class="grid grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-lg border border-[var(--ui-border)] p-4">
                    <h3 class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Core-Steps</h3>
                    <p class="text-2xl font-bold text-[var(--ui-success)]">{{ $metrics['core']['count'] }}</p>
                    <p class="text-xs text-[var(--ui-muted)]">{{ $metrics['core']['percent'] }}% aller Schritte</p>
                </div>
                <div class="bg-white rounded-lg border border-[var(--ui-border)] p-4">
                    <h3 class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Context-Steps</h3>
                    <p class="text-2xl font-bold text-[var(--ui-warning)]">{{ $metrics['context']['count'] }}</p>
                    <p class="text-xs text-[var(--ui-muted)]">{{ $metrics['context']['percent'] }}% aller Schritte</p>
                </div>
                <div class="bg-white rounded-lg border border-[var(--ui-border)] p-4">
                    <h3 class="text-sm font-medium text-[var(--ui-secondary)] mb-1">No-Fit-Steps</h3>
                    <p class="text-2xl font-bold text-[var(--ui-danger)]">{{ $metrics['no_fit']['count'] }}</p>
                    <p class="text-xs text-[var(--ui-muted)]">{{ $metrics['no_fit']['percent'] }}% aller Schritte</p>
                </div>
                <div class="bg-white rounded-lg border border-[var(--ui-border)] p-4">
                    <h3 class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Gesamtkosten</h3>
                    <p class="text-2xl font-bold text-[var(--ui-info)]">{{ number_format($metrics['total_cost'], 2, ',', '.') }}</p>
                    <p class="text-xs text-[var(--ui-muted)]">EUR (basierend auf Stundensatz)</p>
                </div>
            </div>

            {{-- Durchlaufzeit & Effizienz --}}
            <div class="grid grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-lg border border-[var(--ui-border)] p-4">
                    <h3 class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Bearbeitungszeit</h3>
                    <p class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $metrics['total_duration'] }}</p>
                    <p class="text-xs text-[var(--ui-muted)]">Min. aktive Arbeit</p>
                </div>
                <div class="bg-white rounded-lg border border-[var(--ui-border)] p-4">
                    <h3 class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Wartezeit gesamt</h3>
                    <p class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $metrics['total_wait'] }}</p>
                    <p class="text-xs text-[var(--ui-muted)]">Min. Liegezeit</p>
                </div>
                <div class="bg-white rounded-lg border border-[var(--ui-border)] p-4">
                    <h3 class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Durchlaufzeit</h3>
                    <p class="text-2xl font-bold text-[var(--ui-info)]">{{ $metrics['lead_time'] }}</p>
                    <p class="text-xs text-[var(--ui-muted)]">Min. (Bearbeitung + Wartezeit)</p>
                </div>
                <div class="bg-white rounded-lg border border-[var(--ui-border)] p-4">
                    <h3 class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Prozesseffizienz</h3>
                    <p class="text-2xl font-bold {{ $metrics['efficiency'] >= 70 ? 'text-[var(--ui-success)]' : ($metrics['efficiency'] >= 40 ? 'text-[var(--ui-warning)]' : 'text-[var(--ui-danger)]') }}">{{ $metrics['efficiency'] }}%</p>
                    <p class="text-xs text-[var(--ui-muted)]">Anteil aktiver Arbeit an Durchlaufzeit</p>
                </div>
            </div>

            {{-- Zeitanalyse pro Klassifikation --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6 mb-6">
                <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-1">Zeitanalyse pro Klassifikation</h3>
                <p class="text-xs text-[var(--ui-muted)] mb-4">Bearbeitungszeit und Wartezeit aufgeschlüsselt nach Core, Context und No Fit.</p>
                <div class="grid grid-cols-3 gap-4">
                    <div class="border border-[var(--ui-border)]/40 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="inline-block w-2 h-2 rounded-full bg-[var(--ui-success)]"></span>
                            <h4 class="text-sm font-medium text-[var(--ui-secondary)]">Core</h4>
                        </div>
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span class="text-[var(--ui-muted)]">Bearbeitung</span>
                                <span class="font-medium text-[var(--ui-secondary)]">{{ $metrics['core']['minutes'] }} Min.</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-[var(--ui-muted)]">Wartezeit</span>
                                <span class="font-medium text-[var(--ui-secondary)]">{{ $metrics['core']['wait'] }} Min.</span>
                            </div>
                            <div class="flex justify-between border-t border-[var(--ui-border)]/40 pt-1">
                                <span class="text-[var(--ui-muted)]">Kosten</span>
                                <span class="font-medium text-[var(--ui-success)]">{{ number_format($metrics['core']['cost'], 2, ',', '.') }} EUR</span>
                            </div>
                        </div>
                    </div>
                    <div class="border border-[var(--ui-border)]/40 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="inline-block w-2 h-2 rounded-full bg-[var(--ui-warning)]"></span>
                            <h4 class="text-sm font-medium text-[var(--ui-secondary)]">Context</h4>
                        </div>
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span class="text-[var(--ui-muted)]">Bearbeitung</span>
                                <span class="font-medium text-[var(--ui-secondary)]">{{ $metrics['context']['minutes'] }} Min.</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-[var(--ui-muted)]">Wartezeit</span>
                                <span class="font-medium text-[var(--ui-secondary)]">{{ $metrics['context']['wait'] }} Min.</span>
                            </div>
                            <div class="flex justify-between border-t border-[var(--ui-border)]/40 pt-1">
                                <span class="text-[var(--ui-muted)]">Kosten</span>
                                <span class="font-medium text-[var(--ui-warning)]">{{ number_format($metrics['context']['cost'], 2, ',', '.') }} EUR</span>
                            </div>
                        </div>
                    </div>
                    <div class="border border-[var(--ui-border)]/40 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="inline-block w-2 h-2 rounded-full bg-[var(--ui-danger)]"></span>
                            <h4 class="text-sm font-medium text-[var(--ui-secondary)]">No Fit</h4>
                        </div>
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span class="text-[var(--ui-muted)]">Bearbeitung</span>
                                <span class="font-medium text-[var(--ui-secondary)]">{{ $metrics['no_fit']['minutes'] }} Min.</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-[var(--ui-muted)]">Wartezeit</span>
                                <span class="font-medium text-[var(--ui-secondary)]">{{ $metrics['no_fit']['wait'] }} Min.</span>
                            </div>
                            <div class="flex justify-between border-t border-[var(--ui-border)]/40 pt-1">
                                <span class="text-[var(--ui-muted)]">Kosten</span>
                                <span class="font-medium text-[var(--ui-danger)]">{{ number_format($metrics['no_fit']['cost'], 2, ',', '.') }} EUR</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Progress Bars --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6 mb-6">
                <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-1">COREFIT-Verteilung</h3>
                <p class="text-xs text-[var(--ui-muted)] mb-4">Anteil der Schritte je Klassifikation. Idealerweise: Core hoch, Context niedrig, No Fit bei 0%.</p>
                <div class="space-y-3">
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-[var(--ui-secondary)]">Core</span>
                            <span class="font-medium text-[var(--ui-secondary)]">{{ $metrics['core']['percent'] }}%</span>
                        </div>
                        <div class="w-full bg-[var(--ui-muted-20)] rounded-full h-2">
                            <div class="bg-[var(--ui-success)] h-2 rounded-full" style="width: {{ min(100, $metrics['core']['percent']) }}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-[var(--ui-secondary)]">Context</span>
                            <span class="font-medium text-[var(--ui-secondary)]">{{ $metrics['context']['percent'] }}%</span>
                        </div>
                        <div class="w-full bg-[var(--ui-muted-20)] rounded-full h-2">
                            <div class="bg-[var(--ui-warning)] h-2 rounded-full" style="width: {{ min(100, $metrics['context']['percent']) }}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-[var(--ui-secondary)]">No Fit</span>
                            <span class="font-medium text-[var(--ui-secondary)]">{{ $metrics['no_fit']['percent'] }}%</span>
                        </div>
                        <div class="w-full bg-[var(--ui-muted-20)] rounded-full h-2">
                            <div class="bg-[var(--ui-danger)] h-2 rounded-full" style="width: {{ min(100, $metrics['no_fit']['percent']) }}%"></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Automation-Metriken --}}
            @php $autoMetrics = $this->automationMetrics; @endphp

            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6 mb-6">
                <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-1">Automatisierungsgrad</h3>
                <p class="text-xs text-[var(--ui-muted)] mb-4">Verteilung der Prozessschritte nach Automatisierungsgrad. Ziel: manuellen Anteil reduzieren, LLM-Anteil steigern.</p>

                <div class="grid grid-cols-4 gap-4 mb-4">
                    <div class="border border-[var(--ui-border)]/40 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="inline-block w-2 h-2 rounded-full bg-[var(--ui-muted)]"></span>
                            <h4 class="text-sm font-medium text-[var(--ui-secondary)]">Human</h4>
                        </div>
                        <p class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $autoMetrics['human']['count'] }}</p>
                        <p class="text-xs text-[var(--ui-muted)]">{{ $autoMetrics['human']['percent'] }}% &middot; {{ $autoMetrics['human']['minutes'] }} Min.</p>
                    </div>
                    <div class="border border-[var(--ui-border)]/40 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="inline-block w-2 h-2 rounded-full bg-[var(--ui-info)]"></span>
                            <h4 class="text-sm font-medium text-[var(--ui-secondary)]">LLM-Assisted</h4>
                        </div>
                        <p class="text-2xl font-bold text-[var(--ui-info)]">{{ $autoMetrics['llm_assisted']['count'] }}</p>
                        <p class="text-xs text-[var(--ui-muted)]">{{ $autoMetrics['llm_assisted']['percent'] }}% &middot; {{ $autoMetrics['llm_assisted']['minutes'] }} Min.</p>
                    </div>
                    <div class="border border-[var(--ui-border)]/40 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="inline-block w-2 h-2 rounded-full bg-[var(--ui-success)]"></span>
                            <h4 class="text-sm font-medium text-[var(--ui-secondary)]">LLM-Autonomous</h4>
                        </div>
                        <p class="text-2xl font-bold text-[var(--ui-success)]">{{ $autoMetrics['llm_autonomous']['count'] }}</p>
                        <p class="text-xs text-[var(--ui-muted)]">{{ $autoMetrics['llm_autonomous']['percent'] }}% &middot; {{ $autoMetrics['llm_autonomous']['minutes'] }} Min.</p>
                    </div>
                    <div class="border border-[var(--ui-border)]/40 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="inline-block w-2 h-2 rounded-full bg-[var(--ui-warning)]"></span>
                            <h4 class="text-sm font-medium text-[var(--ui-secondary)]">Hybrid</h4>
                        </div>
                        <p class="text-2xl font-bold text-[var(--ui-warning)]">{{ $autoMetrics['hybrid']['count'] }}</p>
                        <p class="text-xs text-[var(--ui-muted)]">{{ $autoMetrics['hybrid']['percent'] }}% &middot; {{ $autoMetrics['hybrid']['minutes'] }} Min.</p>
                    </div>
                </div>

                <div class="space-y-3">
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-[var(--ui-secondary)]">Human</span>
                            <span class="font-medium text-[var(--ui-secondary)]">{{ $autoMetrics['human']['percent'] }}%</span>
                        </div>
                        <div class="w-full bg-[var(--ui-muted-20)] rounded-full h-2">
                            <div class="bg-[var(--ui-muted)] h-2 rounded-full" style="width: {{ min(100, $autoMetrics['human']['percent']) }}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-[var(--ui-secondary)]">LLM-Assisted</span>
                            <span class="font-medium text-[var(--ui-secondary)]">{{ $autoMetrics['llm_assisted']['percent'] }}%</span>
                        </div>
                        <div class="w-full bg-[var(--ui-muted-20)] rounded-full h-2">
                            <div class="bg-[var(--ui-info)] h-2 rounded-full" style="width: {{ min(100, $autoMetrics['llm_assisted']['percent']) }}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-[var(--ui-secondary)]">LLM-Autonomous</span>
                            <span class="font-medium text-[var(--ui-secondary)]">{{ $autoMetrics['llm_autonomous']['percent'] }}%</span>
                        </div>
                        <div class="w-full bg-[var(--ui-muted-20)] rounded-full h-2">
                            <div class="bg-[var(--ui-success)] h-2 rounded-full" style="width: {{ min(100, $autoMetrics['llm_autonomous']['percent']) }}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-[var(--ui-secondary)]">Hybrid</span>
                            <span class="font-medium text-[var(--ui-secondary)]">{{ $autoMetrics['hybrid']['percent'] }}%</span>
                        </div>
                        <div class="w-full bg-[var(--ui-muted-20)] rounded-full h-2">
                            <div class="bg-[var(--ui-warning)] h-2 rounded-full" style="width: {{ min(100, $autoMetrics['hybrid']['percent']) }}%"></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Stundensatz --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6 mb-6">
                <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-1">Kostenbasis</h3>
                <p class="text-xs text-[var(--ui-muted)] mb-4">Der Stundensatz wird mit der Dauer jedes Schritts multipliziert, um die Prozesskosten pro Klassifikation zu berechnen.</p>
                <div class="max-w-xs">
                    <x-ui-input-text name="hourly_rate" label="Stundensatz (EUR/h)" type="number" wire:model.live="form.hourly_rate" min="0" step="0.01" placeholder="z.B. 85.00" />
                </div>
            </div>

            {{-- Canvas Cards --}}
            @php
                $impByCategory = $this->improvementsByCategory;
                $canvasCards = [
                    ['field' => 'target_description', 'label' => 'Zielbild', 'description' => 'Beschreibung des optimalen Soll-Zustands dieses Prozesses.', 'placeholder' => 'Wie soll der Prozess idealerweise aussehen?', 'category' => null],
                    ['field' => 'value_proposition', 'label' => 'Kundennutzen & Wertbeitrag', 'description' => 'Welchen konkreten Mehrwert liefert dieser Prozess an interne/externe Kunden?', 'placeholder' => 'Welchen Wert liefert der Prozess?', 'category' => 'quality'],
                    ['field' => 'cost_analysis', 'label' => 'Kosten & Break-Even', 'description' => 'Analyse der laufenden Kosten, Investitionen und ab wann sich Verbesserungen rechnen.', 'placeholder' => 'Kosten, Aufwand, Break-Even-Analyse', 'category' => 'cost'],
                    ['field' => 'risk_assessment', 'label' => 'Risiko & Resilienz', 'description' => 'Wo liegen Ausfallrisiken, Single Points of Failure und Schwachstellen?', 'placeholder' => 'Risiken, Single Points of Failure, Resilienz', 'category' => 'risk'],
                    ['field' => 'improvement_levers', 'label' => 'Hebel & Lösungsdesign', 'description' => 'Die wirksamsten Stellschrauben zur Verbesserung von Durchlaufzeit und Effizienz.', 'placeholder' => 'Wo liegen die größten Verbesserungshebel?', 'category' => 'speed'],
                    ['field' => 'action_plan', 'label' => 'Maßnahmenplan', 'description' => 'Konkrete nächste Schritte mit Verantwortlichkeiten und Zeitrahmen.', 'placeholder' => 'Konkrete nächste Schritte', 'category' => null],
                    ['field' => 'standardization_notes', 'label' => 'Standardisierung & Kontrolle', 'description' => 'Definierte Standards, KPIs und Kontrollmechanismen zur nachhaltigen Absicherung.', 'placeholder' => 'Standards, KPIs, Kontrollmechanismen', 'category' => 'standardization'],
                ];
            @endphp

            <div class="space-y-4">
                @foreach($canvasCards as $card)
                    <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                        <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-1">{{ $card['label'] }}</h3>
                        <p class="text-xs text-[var(--ui-muted)] mb-3">{{ $card['description'] }}</p>
                        <x-ui-input-textarea
                            name="{{ $card['field'] }}"
                            wire:model.live="form.{{ $card['field'] }}"
                            rows="3"
                            placeholder="{{ $card['placeholder'] }}"
                        />
                        @if($card['category'] && isset($impByCategory[$card['category']]))
                            @php $catData = $impByCategory[$card['category']]; @endphp
                            @if($catData['total'] > 0)
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach($catData['statuses'] as $status => $count)
                                        @php
                                            $statusLabels = [
                                                'identified' => 'identifiziert',
                                                'planned' => 'geplant',
                                                'in_progress' => 'in Arbeit',
                                                'completed' => 'abgeschlossen',
                                                'rejected' => 'abgelehnt',
                                            ];
                                            $statusVariants = [
                                                'identified' => 'muted',
                                                'planned' => 'info',
                                                'in_progress' => 'warning',
                                                'completed' => 'success',
                                                'rejected' => 'danger',
                                            ];
                                        @endphp
                                        <x-ui-badge variant="{{ $statusVariants[$status] ?? 'muted' }}" size="sm">
                                            {{ $count }} {{ $statusLabels[$status] ?? $status }}
                                        </x-ui-badge>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        {{-- ── Tab: Steps ──────────────────────────────────── --}}
        @if($activeTab === 'steps')
            <div class="flex justify-end mb-4">
                <x-ui-button variant="primary" size="sm" wire:click="createStep">
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    <span>Neuer Schritt</span>
                </x-ui-button>
            </div>

            <x-ui-table compact="true">
                <x-ui-table-header>
                    <x-ui-table-header-cell compact="true">Pos</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Name</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Typ</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Dauer</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">CoreFit</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Automation</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true"></x-ui-table-header-cell>
                </x-ui-table-header>
                <x-ui-table-body>
                    @forelse($this->steps as $step)
                        <x-ui-table-row compact="true">
                            <x-ui-table-cell compact="true">
                                <span class="text-sm font-mono">{{ $step->position }}</span>
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <div class="font-medium">{{ $step->name }}</div>
                                @if($step->description)
                                    <div class="text-xs text-[var(--ui-muted)]">{{ \Illuminate\Support\Str::limit($step->description, 60) }}</div>
                                @endif
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <x-ui-badge variant="info" size="sm">{{ ucfirst($step->step_type ?? 'task') }}</x-ui-badge>
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                @if($step->duration_target_minutes)
                                    <span class="text-sm">{{ $step->duration_target_minutes }} min</span>
                                @else
                                    <span class="text-xs text-[var(--ui-muted)]">–</span>
                                @endif
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                @if($step->corefit_classification === 'core')
                                    <x-ui-badge variant="success" size="sm">Core</x-ui-badge>
                                @elseif($step->corefit_classification === 'context')
                                    <x-ui-badge variant="warning" size="sm">Context</x-ui-badge>
                                @else
                                    <x-ui-badge variant="danger" size="sm">No Fit</x-ui-badge>
                                @endif
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                @if($step->automation_level === 'llm_autonomous')
                                    <x-ui-badge variant="success" size="sm">LLM-Autonomous</x-ui-badge>
                                @elseif($step->automation_level === 'llm_assisted')
                                    <x-ui-badge variant="info" size="sm">LLM-Assisted</x-ui-badge>
                                @elseif($step->automation_level === 'hybrid')
                                    <x-ui-badge variant="warning" size="sm">Hybrid</x-ui-badge>
                                @else
                                    <x-ui-badge variant="muted" size="sm">Human</x-ui-badge>
                                @endif
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <div class="flex gap-1 justify-end">
                                    <x-ui-button size="xs" variant="secondary-outline" wire:click="editStep({{ $step->id }})">
                                        @svg('heroicon-o-pencil-square', 'w-4 h-4')
                                    </x-ui-button>
                                    <x-ui-confirm-button size="xs" variant="danger-outline" wire:click="deleteStep({{ $step->id }})" confirm-text="Schritt wirklich löschen?">
                                        @svg('heroicon-o-trash', 'w-4 h-4')
                                    </x-ui-confirm-button>
                                </div>
                            </x-ui-table-cell>
                        </x-ui-table-row>
                    @empty
                        <x-ui-table-row compact="true">
                            <x-ui-table-cell compact="true" colspan="7">
                                <div class="text-center text-[var(--ui-muted)] py-6">Keine Schritte vorhanden.</div>
                            </x-ui-table-cell>
                        </x-ui-table-row>
                    @endforelse
                </x-ui-table-body>
            </x-ui-table>
        @endif

        {{-- ── Tab: Flows ──────────────────────────────────── --}}
        @if($activeTab === 'flows')
            <div class="flex justify-end mb-4">
                <x-ui-button variant="primary" size="sm" wire:click="createFlow">
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    <span>Neuer Flow</span>
                </x-ui-button>
            </div>

            <x-ui-table compact="true">
                <x-ui-table-header>
                    <x-ui-table-header-cell compact="true">Von</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Nach</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Bedingung</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Standard</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true"></x-ui-table-header-cell>
                </x-ui-table-header>
                <x-ui-table-body>
                    @forelse($this->flows as $flow)
                        <x-ui-table-row compact="true">
                            <x-ui-table-cell compact="true">
                                <span class="text-sm font-medium">{{ $flow->fromStep?->name ?? '–' }}</span>
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <span class="text-sm font-medium">{{ $flow->toStep?->name ?? '–' }}</span>
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <span class="text-sm">{{ $flow->condition_label ?? '–' }}</span>
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                @if($flow->is_default)
                                    <x-ui-badge variant="info" size="sm">Standard</x-ui-badge>
                                @endif
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <div class="flex gap-1 justify-end">
                                    <x-ui-button size="xs" variant="secondary-outline" wire:click="editFlow({{ $flow->id }})">
                                        @svg('heroicon-o-pencil-square', 'w-4 h-4')
                                    </x-ui-button>
                                    <x-ui-confirm-button size="xs" variant="danger-outline" wire:click="deleteFlow({{ $flow->id }})" confirm-text="Flow wirklich löschen?">
                                        @svg('heroicon-o-trash', 'w-4 h-4')
                                    </x-ui-confirm-button>
                                </div>
                            </x-ui-table-cell>
                        </x-ui-table-row>
                    @empty
                        <x-ui-table-row compact="true">
                            <x-ui-table-cell compact="true" colspan="5">
                                <div class="text-center text-[var(--ui-muted)] py-6">Keine Flows vorhanden.</div>
                            </x-ui-table-cell>
                        </x-ui-table-row>
                    @endforelse
                </x-ui-table-body>
            </x-ui-table>
        @endif

        {{-- ── Tab: Triggers ───────────────────────────────── --}}
        @if($activeTab === 'triggers')
            <div class="flex justify-end mb-4">
                <x-ui-button variant="primary" size="sm" wire:click="createTrigger">
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    <span>Neuer Trigger</span>
                </x-ui-button>
            </div>

            <x-ui-table compact="true">
                <x-ui-table-header>
                    <x-ui-table-header-cell compact="true">Label</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Typ</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Quelle</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true"></x-ui-table-header-cell>
                </x-ui-table-header>
                <x-ui-table-body>
                    @forelse($this->triggers as $trigger)
                        <x-ui-table-row compact="true">
                            <x-ui-table-cell compact="true">
                                <div class="font-medium">{{ $trigger->label }}</div>
                                @if($trigger->description)
                                    <div class="text-xs text-[var(--ui-muted)]">{{ \Illuminate\Support\Str::limit($trigger->description, 60) }}</div>
                                @endif
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <x-ui-badge variant="info" size="sm">{{ ucfirst(str_replace('_', ' ', $trigger->trigger_type)) }}</x-ui-badge>
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                @if($trigger->entityType)
                                    <span class="text-sm">
                                        <x-ui-badge variant="muted" size="sm">Typ</x-ui-badge>
                                        {{ $trigger->entityType->name }}
                                    </span>
                                @elseif($trigger->entity)
                                    <span class="text-sm">{{ $trigger->entity->name }}</span>
                                @elseif($trigger->sourceProcess)
                                    <span class="text-sm">{{ $trigger->sourceProcess->name }}</span>
                                @elseif($trigger->interlink)
                                    <span class="text-sm">{{ $trigger->interlink->name }}</span>
                                @elseif($trigger->schedule_expression)
                                    <code class="text-xs">{{ $trigger->schedule_expression }}</code>
                                @else
                                    <span class="text-xs text-[var(--ui-muted)]">–</span>
                                @endif
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <div class="flex gap-1 justify-end">
                                    <x-ui-button size="xs" variant="secondary-outline" wire:click="editTrigger({{ $trigger->id }})">
                                        @svg('heroicon-o-pencil-square', 'w-4 h-4')
                                    </x-ui-button>
                                    <x-ui-confirm-button size="xs" variant="danger-outline" wire:click="deleteTrigger({{ $trigger->id }})" confirm-text="Trigger wirklich löschen?">
                                        @svg('heroicon-o-trash', 'w-4 h-4')
                                    </x-ui-confirm-button>
                                </div>
                            </x-ui-table-cell>
                        </x-ui-table-row>
                    @empty
                        <x-ui-table-row compact="true">
                            <x-ui-table-cell compact="true" colspan="4">
                                <div class="text-center text-[var(--ui-muted)] py-6">Keine Triggers vorhanden.</div>
                            </x-ui-table-cell>
                        </x-ui-table-row>
                    @endforelse
                </x-ui-table-body>
            </x-ui-table>
        @endif

        {{-- ── Tab: Outputs ────────────────────────────────── --}}
        @if($activeTab === 'outputs')
            <div class="flex justify-end mb-4">
                <x-ui-button variant="primary" size="sm" wire:click="createOutput">
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    <span>Neuer Output</span>
                </x-ui-button>
            </div>

            <x-ui-table compact="true">
                <x-ui-table-header>
                    <x-ui-table-header-cell compact="true">Label</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Typ</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Ziel</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true"></x-ui-table-header-cell>
                </x-ui-table-header>
                <x-ui-table-body>
                    @forelse($this->outputs as $output)
                        <x-ui-table-row compact="true">
                            <x-ui-table-cell compact="true">
                                <div class="font-medium">{{ $output->label }}</div>
                                @if($output->description)
                                    <div class="text-xs text-[var(--ui-muted)]">{{ \Illuminate\Support\Str::limit($output->description, 60) }}</div>
                                @endif
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <x-ui-badge variant="info" size="sm">{{ ucfirst(str_replace('_', ' ', $output->output_type)) }}</x-ui-badge>
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                @if($output->entity)
                                    <span class="text-sm">{{ $output->entity->name }}</span>
                                @elseif($output->targetProcess)
                                    <span class="text-sm">{{ $output->targetProcess->name }}</span>
                                @elseif($output->interlink)
                                    <span class="text-sm">{{ $output->interlink->name }}</span>
                                @else
                                    <span class="text-xs text-[var(--ui-muted)]">–</span>
                                @endif
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <div class="flex gap-1 justify-end">
                                    <x-ui-button size="xs" variant="secondary-outline" wire:click="editOutput({{ $output->id }})">
                                        @svg('heroicon-o-pencil-square', 'w-4 h-4')
                                    </x-ui-button>
                                    <x-ui-confirm-button size="xs" variant="danger-outline" wire:click="deleteOutput({{ $output->id }})" confirm-text="Output wirklich löschen?">
                                        @svg('heroicon-o-trash', 'w-4 h-4')
                                    </x-ui-confirm-button>
                                </div>
                            </x-ui-table-cell>
                        </x-ui-table-row>
                    @empty
                        <x-ui-table-row compact="true">
                            <x-ui-table-cell compact="true" colspan="4">
                                <div class="text-center text-[var(--ui-muted)] py-6">Keine Outputs vorhanden.</div>
                            </x-ui-table-cell>
                        </x-ui-table-row>
                    @endforelse
                </x-ui-table-body>
            </x-ui-table>
        @endif

        {{-- ── Tab: Verbesserungen ──────────────────────────── --}}
        @if($activeTab === 'improvements')
            <div class="flex justify-end mb-4">
                <x-ui-button variant="primary" size="sm" wire:click="createImprovement">
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    <span>Neue Verbesserung</span>
                </x-ui-button>
            </div>

            <x-ui-table compact="true">
                <x-ui-table-header>
                    <x-ui-table-header-cell compact="true">Titel</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Kategorie</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Priorität</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true"></x-ui-table-header-cell>
                </x-ui-table-header>
                <x-ui-table-body>
                    @forelse($this->processImprovements as $imp)
                        <x-ui-table-row compact="true">
                            <x-ui-table-cell compact="true">
                                <div class="font-medium">{{ $imp->title }}</div>
                                @if($imp->description)
                                    <div class="text-xs text-[var(--ui-muted)]">{{ \Illuminate\Support\Str::limit($imp->description, 60) }}</div>
                                @endif
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <x-ui-badge variant="info" size="sm">{{ ucfirst($imp->category) }}</x-ui-badge>
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                @if($imp->priority === 'critical')
                                    <x-ui-badge variant="danger" size="sm">Kritisch</x-ui-badge>
                                @elseif($imp->priority === 'high')
                                    <x-ui-badge variant="warning" size="sm">Hoch</x-ui-badge>
                                @elseif($imp->priority === 'medium')
                                    <x-ui-badge variant="info" size="sm">Mittel</x-ui-badge>
                                @else
                                    <x-ui-badge variant="muted" size="sm">Niedrig</x-ui-badge>
                                @endif
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                @if($imp->status === 'completed')
                                    <x-ui-badge variant="success" size="sm">Abgeschlossen</x-ui-badge>
                                @elseif($imp->status === 'in_progress')
                                    <x-ui-badge variant="warning" size="sm">In Arbeit</x-ui-badge>
                                @elseif($imp->status === 'planned')
                                    <x-ui-badge variant="info" size="sm">Geplant</x-ui-badge>
                                @elseif($imp->status === 'rejected')
                                    <x-ui-badge variant="danger" size="sm">Abgelehnt</x-ui-badge>
                                @else
                                    <x-ui-badge variant="muted" size="sm">Identifiziert</x-ui-badge>
                                @endif
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <div class="flex gap-1 justify-end">
                                    <x-ui-button size="xs" variant="secondary-outline" wire:click="editImprovement({{ $imp->id }})">
                                        @svg('heroicon-o-pencil-square', 'w-4 h-4')
                                    </x-ui-button>
                                    <x-ui-confirm-button size="xs" variant="danger-outline" wire:click="deleteImprovement({{ $imp->id }})" confirm-text="Verbesserung wirklich löschen?">
                                        @svg('heroicon-o-trash', 'w-4 h-4')
                                    </x-ui-confirm-button>
                                </div>
                            </x-ui-table-cell>
                        </x-ui-table-row>
                    @empty
                        <x-ui-table-row compact="true">
                            <x-ui-table-cell compact="true" colspan="5">
                                <div class="text-center text-[var(--ui-muted)] py-6">Keine Verbesserungen vorhanden.</div>
                            </x-ui-table-cell>
                        </x-ui-table-row>
                    @endforelse
                </x-ui-table-body>
            </x-ui-table>
        @endif

        {{-- ── Tab: Snapshots ────────────────────────────────── --}}
        @if($activeTab === 'snapshots')
            <div class="flex justify-end mb-4">
                <x-ui-button variant="primary" size="sm" wire:click="createSnapshot">
                    @svg('heroicon-o-camera', 'w-4 h-4')
                    <span>Snapshot erstellen</span>
                </x-ui-button>
            </div>

            <x-ui-table compact="true">
                <x-ui-table-header>
                    <x-ui-table-header-cell compact="true">Version</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Label</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Steps</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Dauer</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Erstellt</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true"></x-ui-table-header-cell>
                </x-ui-table-header>
                <x-ui-table-body>
                    @forelse($this->processSnapshots as $snap)
                        <x-ui-table-row compact="true">
                            <x-ui-table-cell compact="true">
                                <span class="text-sm font-mono font-bold">v{{ $snap->version }}</span>
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <span class="text-sm">{{ $snap->label ?? '–' }}</span>
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <span class="text-sm">{{ $snap->metrics['total_steps'] ?? 0 }}</span>
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <span class="text-sm">{{ $snap->metrics['total_duration'] ?? 0 }} min</span>
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <span class="text-sm">{{ $snap->created_at?->format('d.m.Y H:i') }}</span>
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <div class="flex gap-1 justify-end">
                                    <x-ui-confirm-button size="xs" variant="danger-outline" wire:click="deleteSnapshot({{ $snap->id }})" confirm-text="Snapshot wirklich löschen?">
                                        @svg('heroicon-o-trash', 'w-4 h-4')
                                    </x-ui-confirm-button>
                                </div>
                            </x-ui-table-cell>
                        </x-ui-table-row>
                    @empty
                        <x-ui-table-row compact="true">
                            <x-ui-table-cell compact="true" colspan="6">
                                <div class="text-center text-[var(--ui-muted)] py-6">Keine Snapshots vorhanden.</div>
                            </x-ui-table-cell>
                        </x-ui-table-row>
                    @endforelse
                </x-ui-table-body>
            </x-ui-table>
        @endif
    </x-ui-page-container>

    {{-- ── Step Modal ──────────────────────────────────────── --}}
    <x-ui-modal wire:model="stepModalShow" size="lg">
        <x-slot name="header">
            {{ $editingStepId ? 'Schritt bearbeiten' : 'Neuer Schritt' }}
        </x-slot>

        <form wire:submit.prevent="storeStep" class="space-y-4">
            <div class="grid grid-cols-4 gap-4">
                <div class="col-span-1">
                    <x-ui-input-text name="position" label="Position" type="number" wire:model.live="stepForm.position" required min="1" placeholder="#" />
                </div>
                <div class="col-span-3">
                    <x-ui-input-text name="step_name" label="Name" wire:model.live="stepForm.name" required placeholder="Was passiert in diesem Schritt?" />
                </div>
            </div>

            <x-ui-input-textarea name="step_description" label="Beschreibung" wire:model.live="stepForm.description" rows="2" placeholder="Detailbeschreibung: Wer macht was, mit welchem Ergebnis?" />

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <x-ui-input-select
                        name="step_type"
                        label="Schritttyp"
                        :options="[
                            ['value' => 'task', 'label' => 'Task'],
                            ['value' => 'decision', 'label' => 'Decision'],
                            ['value' => 'event', 'label' => 'Event'],
                            ['value' => 'subprocess', 'label' => 'Subprocess'],
                        ]"
                        wire:model.live="stepForm.step_type"
                    />
                    <p class="text-xs text-[var(--ui-muted)] mt-1">Task = Arbeitsschritt, Decision = Entscheidung, Event = Ereignis, Subprocess = eingebetteter Teilprozess</p>
                </div>
                <div>
                    <x-ui-input-select
                        name="corefit_classification"
                        label="COREFIT Klassifikation"
                        :options="[
                            ['value' => 'core', 'label' => 'Core'],
                            ['value' => 'context', 'label' => 'Context'],
                            ['value' => 'no_fit', 'label' => 'No Fit'],
                        ]"
                        wire:model.live="stepForm.corefit_classification"
                    />
                    <p class="text-xs text-[var(--ui-muted)] mt-1">Core = Wertschöpfend, Context = Notwendig aber nicht wertschöpfend, No Fit = Eliminieren</p>
                </div>
            </div>

            <div>
                <x-ui-input-select
                    name="automation_level"
                    label="Automatisierungsgrad"
                    :options="[
                        ['value' => 'human', 'label' => 'Human'],
                        ['value' => 'llm_assisted', 'label' => 'LLM-Assisted'],
                        ['value' => 'llm_autonomous', 'label' => 'LLM-Autonomous'],
                        ['value' => 'hybrid', 'label' => 'Hybrid'],
                    ]"
                    wire:model.live="stepForm.automation_level"
                />
                <p class="text-xs text-[var(--ui-muted)] mt-1">Human = Mensch, LLM-Assisted = KI-unterstützt, LLM-Autonomous = KI-autonom, Hybrid = Mensch + KI gemeinsam</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <x-ui-input-text name="duration_target_minutes" label="Dauer (Min.)" type="number" wire:model.live="stepForm.duration_target_minutes" min="0" placeholder="Aktive Bearbeitungszeit" />
                <x-ui-input-text name="wait_target_minutes" label="Wartezeit (Min.)" type="number" wire:model.live="stepForm.wait_target_minutes" min="0" placeholder="Liegezeit bis zum nächsten Schritt" />
            </div>

            <div class="flex items-center">
                <input type="checkbox" wire:model.live="stepForm.is_active" id="step_is_active" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                <label for="step_is_active" class="ml-2 text-sm text-[var(--ui-secondary)]">Aktiv</label>
            </div>
        </form>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button type="button" variant="secondary-outline" wire:click="$set('stepModalShow', false)">Abbrechen</x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="storeStep">
                    @svg('heroicon-o-check', 'w-4 h-4 mr-2')
                    {{ $editingStepId ? 'Speichern' : 'Erstellen' }}
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>

    {{-- ── Flow Modal ──────────────────────────────────────── --}}
    <x-ui-modal wire:model="flowModalShow" size="lg">
        <x-slot name="header">
            {{ $editingFlowId ? 'Flow bearbeiten' : 'Neuer Flow' }}
        </x-slot>

        <form wire:submit.prevent="storeFlow" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <x-ui-input-select
                    name="from_step_id"
                    label="Von Schritt"
                    :options="$this->steps->map(fn($s) => ['value' => (string) $s->id, 'label' => $s->position . '. ' . $s->name])->toArray()"
                    nullable
                    nullLabel="– Auswählen –"
                    wire:model.live="flowForm.from_step_id"
                    required
                />
                <x-ui-input-select
                    name="to_step_id"
                    label="Nach Schritt"
                    :options="$this->steps->map(fn($s) => ['value' => (string) $s->id, 'label' => $s->position . '. ' . $s->name])->toArray()"
                    nullable
                    nullLabel="– Auswählen –"
                    wire:model.live="flowForm.to_step_id"
                    required
                />
            </div>

            <x-ui-input-text name="condition_label" label="Bedingung (optional)" wire:model.live="flowForm.condition_label" placeholder="z.B. Ja / Nein" />

            <div class="flex items-center">
                <input type="checkbox" wire:model.live="flowForm.is_default" id="flow_is_default" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                <label for="flow_is_default" class="ml-2 text-sm text-[var(--ui-secondary)]">Standard-Flow</label>
            </div>
        </form>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button type="button" variant="secondary-outline" wire:click="$set('flowModalShow', false)">Abbrechen</x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="storeFlow">
                    @svg('heroicon-o-check', 'w-4 h-4 mr-2')
                    {{ $editingFlowId ? 'Speichern' : 'Erstellen' }}
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>

    {{-- ── Trigger Modal ───────────────────────────────────── --}}
    <x-ui-modal wire:model="triggerModalShow" size="lg">
        <x-slot name="header">
            {{ $editingTriggerId ? 'Trigger bearbeiten' : 'Neuer Trigger' }}
        </x-slot>

        <form wire:submit.prevent="storeTrigger" class="space-y-4">
            <x-ui-input-text name="trigger_label" label="Label" wire:model.live="triggerForm.label" required />
            <x-ui-input-textarea name="trigger_description" label="Beschreibung" wire:model.live="triggerForm.description" rows="2" />

            <x-ui-input-select
                name="trigger_type"
                label="Trigger-Typ"
                :options="[
                    ['value' => 'manual', 'label' => 'Manuell'],
                    ['value' => 'scheduled', 'label' => 'Geplant'],
                    ['value' => 'event', 'label' => 'Event'],
                    ['value' => 'process_output', 'label' => 'Prozess-Output'],
                    ['value' => 'interlink', 'label' => 'Interlink'],
                ]"
                wire:model.live="triggerForm.trigger_type"
            />

            @if($triggerForm['trigger_type'] === 'scheduled')
                <x-ui-input-text name="schedule_expression" label="Schedule-Ausdruck (Cron)" wire:model.live="triggerForm.schedule_expression" placeholder="z.B. 0 8 * * MON" />
            @endif

            @if($triggerForm['trigger_type'] === 'event')
                <x-ui-input-select
                    name="entity_scope"
                    label="Quell-Zuordnung"
                    :options="[
                        ['value' => 'none', 'label' => 'Keine'],
                        ['value' => 'entity_type', 'label' => 'Entity-Typ (generisch)'],
                        ['value' => 'entity', 'label' => 'Konkrete Entity'],
                    ]"
                    wire:model.live="triggerForm.entity_scope"
                />
                <p class="text-xs text-[var(--ui-muted)] -mt-2">Entity-Typ = alle Entitäten dieses Typs lösen den Trigger aus. Konkrete Entity = nur eine bestimmte Entität.</p>

                @if($triggerForm['entity_scope'] === 'entity_type')
                    <x-ui-input-select
                        name="trigger_entity_type_id"
                        label="Entity-Typ"
                        :options="$this->availableEntityTypes->map(fn($t) => ['value' => (string) $t->id, 'label' => $t->name])->toArray()"
                        nullable
                        nullLabel="– Auswählen –"
                        wire:model.live="triggerForm.entity_type_id"
                        required
                    />
                @endif

                @if($triggerForm['entity_scope'] === 'entity')
                    <x-ui-input-select
                        name="trigger_entity_id"
                        label="Quell-Entity"
                        :options="$this->groupedEntityOptions"
                        nullable
                        nullLabel="– Auswählen –"
                        wire:model.live="triggerForm.entity_id"
                        required
                    />
                @endif
            @endif

            @if($triggerForm['trigger_type'] === 'process_output')
                <x-ui-input-select
                    name="source_process_id"
                    label="Quell-Prozess"
                    :options="$this->availableProcesses->map(fn($p) => ['value' => (string) $p->id, 'label' => $p->name])->toArray()"
                    nullable
                    nullLabel="– Auswählen –"
                    wire:model.live="triggerForm.source_process_id"
                />
            @endif
        </form>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button type="button" variant="secondary-outline" wire:click="$set('triggerModalShow', false)">Abbrechen</x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="storeTrigger">
                    @svg('heroicon-o-check', 'w-4 h-4 mr-2')
                    {{ $editingTriggerId ? 'Speichern' : 'Erstellen' }}
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>

    {{-- ── Output Modal ────────────────────────────────────── --}}
    <x-ui-modal wire:model="outputModalShow" size="lg">
        <x-slot name="header">
            {{ $editingOutputId ? 'Output bearbeiten' : 'Neuer Output' }}
        </x-slot>

        <form wire:submit.prevent="storeOutput" class="space-y-4">
            <x-ui-input-text name="output_label" label="Label" wire:model.live="outputForm.label" required />
            <x-ui-input-textarea name="output_description" label="Beschreibung" wire:model.live="outputForm.description" rows="2" />

            <x-ui-input-select
                name="output_type"
                label="Output-Typ"
                :options="[
                    ['value' => 'document', 'label' => 'Dokument'],
                    ['value' => 'data', 'label' => 'Daten'],
                    ['value' => 'notification', 'label' => 'Benachrichtigung'],
                    ['value' => 'process_trigger', 'label' => 'Prozess-Trigger'],
                    ['value' => 'interlink', 'label' => 'Interlink'],
                ]"
                wire:model.live="outputForm.output_type"
            />

            @if($outputForm['output_type'] === 'process_trigger')
                <x-ui-input-select
                    name="target_process_id"
                    label="Ziel-Prozess"
                    :options="$this->availableProcesses->map(fn($p) => ['value' => (string) $p->id, 'label' => $p->name])->toArray()"
                    nullable
                    nullLabel="– Auswählen –"
                    wire:model.live="outputForm.target_process_id"
                />
            @endif

            @if(in_array($outputForm['output_type'], ['document', 'data', 'notification']))
                <x-ui-input-select
                    name="output_entity_id"
                    label="Ziel-Entity (optional)"
                    :options="$this->groupedEntityOptions"
                    nullable
                    nullLabel="– Auswählen –"
                    wire:model.live="outputForm.entity_id"
                />
            @endif
        </form>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button type="button" variant="secondary-outline" wire:click="$set('outputModalShow', false)">Abbrechen</x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="storeOutput">
                    @svg('heroicon-o-check', 'w-4 h-4 mr-2')
                    {{ $editingOutputId ? 'Speichern' : 'Erstellen' }}
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>

    {{-- ── Snapshot Modal ──────────────────────────────────── --}}
    <x-ui-modal wire:model="snapshotModalShow" size="md">
        <x-slot name="header">
            Snapshot erstellen
        </x-slot>

        <form wire:submit.prevent="storeSnapshot" class="space-y-4">
            <x-ui-input-text name="snapshot_label" label="Label (optional)" wire:model.live="snapshotLabel" placeholder="z.B. Baseline, Nach Optimierung" />
            <p class="text-sm text-[var(--ui-muted)]">
                Ein Snapshot friert den aktuellen Zustand des Prozesses ein (inkl. Steps, Flows, Triggers, Outputs und strategische Felder).
            </p>
        </form>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button type="button" variant="secondary-outline" wire:click="$set('snapshotModalShow', false)">Abbrechen</x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="storeSnapshot">
                    @svg('heroicon-o-camera', 'w-4 h-4 mr-2')
                    Snapshot erstellen
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>

    {{-- ── Improvement Modal ───────────────────────────────── --}}
    <x-ui-modal wire:model="improvementModalShow" size="lg">
        <x-slot name="header">
            {{ $editingImprovementId ? 'Verbesserung bearbeiten' : 'Neue Verbesserung' }}
        </x-slot>

        <form wire:submit.prevent="storeImprovement" class="space-y-4">
            <x-ui-input-text name="imp_title" label="Titel" wire:model.live="improvementForm.title" required placeholder="Kurzer, aussagekräftiger Titel der Verbesserung" />
            <x-ui-input-textarea name="imp_description" label="Beschreibung" wire:model.live="improvementForm.description" rows="2" placeholder="Was genau soll verbessert werden und warum?" />

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <x-ui-input-select
                        name="imp_category"
                        label="Kategorie"
                        :options="[
                            ['value' => 'cost', 'label' => 'Kosten'],
                            ['value' => 'quality', 'label' => 'Qualität'],
                            ['value' => 'speed', 'label' => 'Geschwindigkeit'],
                            ['value' => 'risk', 'label' => 'Risiko'],
                            ['value' => 'standardization', 'label' => 'Standardisierung'],
                        ]"
                        wire:model.live="improvementForm.category"
                    />
                    <p class="text-xs text-[var(--ui-muted)] mt-1">In welchem Bereich wirkt die Verbesserung?</p>
                </div>
                <div>
                    <x-ui-input-select
                        name="imp_priority"
                        label="Priorität"
                        :options="[
                            ['value' => 'low', 'label' => 'Niedrig'],
                            ['value' => 'medium', 'label' => 'Mittel'],
                            ['value' => 'high', 'label' => 'Hoch'],
                            ['value' => 'critical', 'label' => 'Kritisch'],
                        ]"
                        wire:model.live="improvementForm.priority"
                    />
                    <p class="text-xs text-[var(--ui-muted)] mt-1">Wie dringend ist die Umsetzung?</p>
                </div>
                <div>
                    <x-ui-input-select
                        name="imp_status"
                        label="Status"
                        :options="[
                            ['value' => 'identified', 'label' => 'Identifiziert'],
                            ['value' => 'planned', 'label' => 'Geplant'],
                            ['value' => 'in_progress', 'label' => 'In Arbeit'],
                            ['value' => 'completed', 'label' => 'Abgeschlossen'],
                            ['value' => 'rejected', 'label' => 'Abgelehnt'],
                        ]"
                        wire:model.live="improvementForm.status"
                    />
                    <p class="text-xs text-[var(--ui-muted)] mt-1">Aktueller Stand der Umsetzung</p>
                </div>
            </div>

            <x-ui-input-textarea name="expected_outcome" label="Erwartetes Ergebnis" wire:model.live="improvementForm.expected_outcome" rows="2" placeholder="Welche messbare Verbesserung wird erwartet?" />
            <x-ui-input-textarea name="actual_outcome" label="Tatsächliches Ergebnis" wire:model.live="improvementForm.actual_outcome" rows="2" placeholder="Was wurde tatsächlich erreicht? (nach Abschluss ausfüllen)" />
        </form>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button type="button" variant="secondary-outline" wire:click="$set('improvementModalShow', false)">Abbrechen</x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="storeImprovement">
                    @svg('heroicon-o-check', 'w-4 h-4 mr-2')
                    {{ $editingImprovementId ? 'Speichern' : 'Erstellen' }}
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>
</x-ui-page>
