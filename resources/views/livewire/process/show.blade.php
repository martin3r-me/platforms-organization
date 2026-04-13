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
        {{-- Tabs --}}
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
        <x-ui-tab :tabs="$tabItems" model="activeTab" :showCounts="true" />

        {{-- ── Tab: Details ────────────────────────────────── --}}
        @if($activeTab === 'details')
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Grunddaten</h2>
                <div class="space-y-4">
                    <x-ui-input-text name="name" label="Name" wire:model.live="form.name" required />
                    <x-ui-input-text name="code" label="Code" wire:model.live="form.code" />
                    <x-ui-input-textarea name="description" label="Beschreibung" wire:model.live="form.description" />

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
                            :options="$this->availableEntities->map(fn($e) => ['value' => (string) $e->id, 'label' => $e->name])->toArray()"
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

            {{-- Metriken-Kacheln --}}
            <x-ui-stats-grid :cols="4" :gap="4">
                <x-ui-dashboard-tile
                    title="Core-Steps"
                    :count="$metrics['core']['count']"
                    :description="$metrics['core']['percent'] . '%'"
                    icon="check-badge"
                    variant="success"
                    size="sm"
                />
                <x-ui-dashboard-tile
                    title="Context-Steps"
                    :count="$metrics['context']['count']"
                    :description="$metrics['context']['percent'] . '%'"
                    icon="puzzle-piece"
                    variant="warning"
                    size="sm"
                />
                <x-ui-dashboard-tile
                    title="No-Fit-Steps"
                    :count="$metrics['no_fit']['count']"
                    :description="$metrics['no_fit']['percent'] . '%'"
                    icon="x-circle"
                    variant="danger"
                    size="sm"
                />
                <x-ui-dashboard-tile
                    title="Gesamtkosten"
                    :count="$metrics['total_cost']"
                    description="EUR"
                    icon="currency-euro"
                    variant="info"
                    size="sm"
                />
            </x-ui-stats-grid>

            {{-- Zeitanalyse --}}
            <x-ui-stats-grid :cols="3" :gap="4">
                <x-ui-dashboard-tile
                    title="Core-Zeit"
                    :count="$metrics['core']['minutes']"
                    :description="'Min. / ' . number_format($metrics['core']['cost'], 2, ',', '.') . ' EUR'"
                    icon="clock"
                    variant="success"
                    size="sm"
                />
                <x-ui-dashboard-tile
                    title="Context-Zeit"
                    :count="$metrics['context']['minutes']"
                    :description="'Min. / ' . number_format($metrics['context']['cost'], 2, ',', '.') . ' EUR'"
                    icon="clock"
                    variant="warning"
                    size="sm"
                />
                <x-ui-dashboard-tile
                    title="Wartezeit"
                    :count="$metrics['total_wait']"
                    description="Min."
                    icon="pause-circle"
                    variant="neutral"
                    size="sm"
                />
            </x-ui-stats-grid>

            {{-- Progress Bars --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6 mb-6">
                <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-4">COREFIT-Verteilung</h3>
                <div class="space-y-3">
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-[var(--ui-secondary)]">Core</span>
                            <span class="font-medium text-[var(--ui-secondary)]">{{ $metrics['core']['percent'] }}%</span>
                        </div>
                        <x-ui-progress-bar :value="$metrics['core']['percent']" variant="success" height="sm" />
                    </div>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-[var(--ui-secondary)]">Context</span>
                            <span class="font-medium text-[var(--ui-secondary)]">{{ $metrics['context']['percent'] }}%</span>
                        </div>
                        <x-ui-progress-bar :value="$metrics['context']['percent']" variant="warning" height="sm" />
                    </div>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-[var(--ui-secondary)]">No Fit</span>
                            <span class="font-medium text-[var(--ui-secondary)]">{{ $metrics['no_fit']['percent'] }}%</span>
                        </div>
                        <x-ui-progress-bar :value="$metrics['no_fit']['percent']" variant="danger" height="sm" />
                    </div>
                </div>
            </div>

            {{-- Stundensatz --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6 mb-6">
                <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-4">Stundensatz</h3>
                <div class="max-w-xs">
                    <x-ui-input-text name="hourly_rate" label="Stundensatz (EUR/h)" type="number" wire:model.live="form.hourly_rate" min="0" step="0.01" placeholder="z.B. 85.00" />
                </div>
            </div>

            {{-- Canvas Cards --}}
            @php
                $impByCategory = $this->improvementsByCategory;
                $canvasCards = [
                    ['field' => 'target_description', 'label' => 'Zielbild', 'placeholder' => 'Wie soll der Prozess idealerweise aussehen?', 'category' => null],
                    ['field' => 'value_proposition', 'label' => 'Kundennutzen & Wertbeitrag', 'placeholder' => 'Welchen Wert liefert der Prozess?', 'category' => 'quality'],
                    ['field' => 'cost_analysis', 'label' => 'Kosten & Break-Even', 'placeholder' => 'Kosten, Aufwand, Break-Even-Analyse', 'category' => 'cost'],
                    ['field' => 'risk_assessment', 'label' => 'Risiko & Resilienz', 'placeholder' => 'Risiken, Single Points of Failure, Resilienz', 'category' => 'risk'],
                    ['field' => 'improvement_levers', 'label' => 'Hebel & Lösungsdesign', 'placeholder' => 'Wo liegen die größten Verbesserungshebel?', 'category' => 'speed'],
                    ['field' => 'action_plan', 'label' => 'Maßnahmenplan', 'placeholder' => 'Konkrete nächste Schritte', 'category' => null],
                    ['field' => 'standardization_notes', 'label' => 'Standardisierung & Kontrolle', 'placeholder' => 'Standards, KPIs, Kontrollmechanismen', 'category' => 'standardization'],
                ];
            @endphp

            <div class="space-y-4">
                @foreach($canvasCards as $card)
                    <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                        <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-3">{{ $card['label'] }}</h3>
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
                            <x-ui-table-cell compact="true" colspan="6">
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
                                @if($trigger->entity)
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
                    <x-ui-input-text name="position" label="Position" type="number" wire:model.live="stepForm.position" required min="1" />
                </div>
                <div class="col-span-3">
                    <x-ui-input-text name="step_name" label="Name" wire:model.live="stepForm.name" required />
                </div>
            </div>

            <x-ui-input-textarea name="step_description" label="Beschreibung" wire:model.live="stepForm.description" rows="2" />

            <div class="grid grid-cols-2 gap-4">
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
                <x-ui-input-select
                    name="corefit_classification"
                    label="CoreFit Klassifikation"
                    :options="[
                        ['value' => 'core', 'label' => 'Core'],
                        ['value' => 'context', 'label' => 'Context'],
                        ['value' => 'no_fit', 'label' => 'No Fit'],
                    ]"
                    wire:model.live="stepForm.corefit_classification"
                />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <x-ui-input-text name="duration_target_minutes" label="Dauer (Min.)" type="number" wire:model.live="stepForm.duration_target_minutes" min="0" />
                <x-ui-input-text name="wait_target_minutes" label="Wartezeit (Min.)" type="number" wire:model.live="stepForm.wait_target_minutes" min="0" />
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
                    name="trigger_entity_id"
                    label="Quell-Entity"
                    :options="$this->availableEntities->map(fn($e) => ['value' => (string) $e->id, 'label' => $e->name])->toArray()"
                    nullable
                    nullLabel="– Auswählen –"
                    wire:model.live="triggerForm.entity_id"
                />
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
                    :options="$this->availableEntities->map(fn($e) => ['value' => (string) $e->id, 'label' => $e->name])->toArray()"
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
            <x-ui-input-text name="imp_title" label="Titel" wire:model.live="improvementForm.title" required />
            <x-ui-input-textarea name="imp_description" label="Beschreibung" wire:model.live="improvementForm.description" rows="2" />

            <div class="grid grid-cols-3 gap-4">
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
            </div>

            <x-ui-input-textarea name="expected_outcome" label="Erwartetes Ergebnis" wire:model.live="improvementForm.expected_outcome" rows="2" />
            <x-ui-input-textarea name="actual_outcome" label="Tatsächliches Ergebnis" wire:model.live="improvementForm.actual_outcome" rows="2" />
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
