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
        <!-- Tabs -->
        <div class="flex items-center gap-1 mb-6 border-b border-[var(--ui-border)]/60">
            @foreach(['details' => 'Details', 'steps' => 'Steps', 'flows' => 'Flows', 'triggers' => 'Triggers', 'outputs' => 'Outputs'] as $tab => $label)
                <button
                    wire:click="$set('activeTab', '{{ $tab }}')"
                    class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors {{ $activeTab === $tab ? 'border-[var(--ui-primary)] text-[var(--ui-primary)]' : 'border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]' }}"
                >
                    {{ $label }}
                    @if($tab === 'steps')
                        <span class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-[var(--ui-muted-5)] text-[var(--ui-muted)]">{{ $this->steps->count() }}</span>
                    @elseif($tab === 'flows')
                        <span class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-[var(--ui-muted-5)] text-[var(--ui-muted)]">{{ $this->flows->count() }}</span>
                    @elseif($tab === 'triggers')
                        <span class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-[var(--ui-muted-5)] text-[var(--ui-muted)]">{{ $this->triggers->count() }}</span>
                    @elseif($tab === 'outputs')
                        <span class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-[var(--ui-muted-5)] text-[var(--ui-muted)]">{{ $this->outputs->count() }}</span>
                    @endif
                </button>
            @endforeach
        </div>

        {{-- ── Tab: Details ────────────────────────────────── --}}
        @if($activeTab === 'details')
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Grunddaten</h2>
                <div class="space-y-4">
                    <x-ui-input-text name="name" label="Name" wire:model.live="form.name" required />
                    <x-ui-input-text name="code" label="Code" wire:model.live="form.code" />
                    <x-ui-input-textarea name="description" label="Beschreibung" wire:model.live="form.description" />

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-[var(--ui-secondary)] mb-2">Status</label>
                            <select wire:model.live="form.status" class="w-full rounded-md border-gray-300 shadow-sm">
                                <option value="draft">Entwurf</option>
                                <option value="active">Aktiv</option>
                                <option value="deprecated">Veraltet</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-[var(--ui-secondary)] mb-2">Version</label>
                            <x-ui-input-text name="version" type="number" wire:model.live="form.version" min="1" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-[var(--ui-secondary)] mb-2">Owner (Entity)</label>
                            <select wire:model.live="form.owner_entity_id" class="w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">– Kein Owner –</option>
                                @foreach($this->availableEntities as $entity)
                                    <option value="{{ $entity->id }}">{{ $entity->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-[var(--ui-secondary)] mb-2">VSM System</label>
                            <select wire:model.live="form.vsm_system_id" class="w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">– Kein VSM System –</option>
                                @foreach($this->availableVsmSystems as $vsm)
                                    <option value="{{ $vsm->id }}">{{ $vsm->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" wire:model.live="form.is_active" id="is_active" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                        <label for="is_active" class="ml-2 text-sm text-[var(--ui-secondary)]">Aktiv geschaltet</label>
                    </div>
                </div>
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
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Schritttyp</label>
                    <select wire:model.live="stepForm.step_type" class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="task">Task</option>
                        <option value="decision">Decision</option>
                        <option value="event">Event</option>
                        <option value="subprocess">Subprocess</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">CoreFit Klassifikation</label>
                    <select wire:model.live="stepForm.corefit_classification" class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="core">Core</option>
                        <option value="context">Context</option>
                        <option value="no_fit">No Fit</option>
                    </select>
                </div>
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
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Von Schritt</label>
                    <select wire:model.live="flowForm.from_step_id" class="w-full rounded-md border-gray-300 shadow-sm" required>
                        <option value="">– Auswählen –</option>
                        @foreach($this->steps as $step)
                            <option value="{{ $step->id }}">{{ $step->position }}. {{ $step->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nach Schritt</label>
                    <select wire:model.live="flowForm.to_step_id" class="w-full rounded-md border-gray-300 shadow-sm" required>
                        <option value="">– Auswählen –</option>
                        @foreach($this->steps as $step)
                            <option value="{{ $step->id }}">{{ $step->position }}. {{ $step->name }}</option>
                        @endforeach
                    </select>
                </div>
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

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Trigger-Typ</label>
                <select wire:model.live="triggerForm.trigger_type" class="w-full rounded-md border-gray-300 shadow-sm">
                    <option value="manual">Manuell</option>
                    <option value="scheduled">Geplant</option>
                    <option value="event">Event</option>
                    <option value="process_output">Prozess-Output</option>
                    <option value="interlink">Interlink</option>
                </select>
            </div>

            @if($triggerForm['trigger_type'] === 'scheduled')
                <x-ui-input-text name="schedule_expression" label="Schedule-Ausdruck (Cron)" wire:model.live="triggerForm.schedule_expression" placeholder="z.B. 0 8 * * MON" />
            @endif

            @if($triggerForm['trigger_type'] === 'event')
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Quell-Entity</label>
                    <select wire:model.live="triggerForm.entity_id" class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">– Auswählen –</option>
                        @foreach($this->availableEntities as $entity)
                            <option value="{{ $entity->id }}">{{ $entity->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if($triggerForm['trigger_type'] === 'process_output')
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Quell-Prozess</label>
                    <select wire:model.live="triggerForm.source_process_id" class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">– Auswählen –</option>
                        @foreach($this->availableProcesses as $proc)
                            <option value="{{ $proc->id }}">{{ $proc->name }}</option>
                        @endforeach
                    </select>
                </div>
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

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Output-Typ</label>
                <select wire:model.live="outputForm.output_type" class="w-full rounded-md border-gray-300 shadow-sm">
                    <option value="document">Dokument</option>
                    <option value="data">Daten</option>
                    <option value="notification">Benachrichtigung</option>
                    <option value="process_trigger">Prozess-Trigger</option>
                    <option value="interlink">Interlink</option>
                </select>
            </div>

            @if($outputForm['output_type'] === 'process_trigger')
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Ziel-Prozess</label>
                    <select wire:model.live="outputForm.target_process_id" class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">– Auswählen –</option>
                        @foreach($this->availableProcesses as $proc)
                            <option value="{{ $proc->id }}">{{ $proc->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if(in_array($outputForm['output_type'], ['document', 'data', 'notification']))
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Ziel-Entity (optional)</label>
                    <select wire:model.live="outputForm.entity_id" class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">– Auswählen –</option>
                        @foreach($this->availableEntities as $entity)
                            <option value="{{ $entity->id }}">{{ $entity->name }}</option>
                        @endforeach
                    </select>
                </div>
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
</x-ui-page>
