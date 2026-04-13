<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Prozesse'],
        ]">
            <x-ui-button variant="primary" size="sm" wire:click="create">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neuer Prozess</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Suche</h3>
                    <x-ui-input-text name="search" wire:model.live="search" placeholder="Name, Code, Beschreibung..." class="w-full" size="sm" />
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Status</h3>
                    <select wire:model.live="statusFilter" class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">Alle</option>
                        <option value="draft">Entwurf</option>
                        <option value="active">Aktiv</option>
                        <option value="deprecated">Veraltet</option>
                    </select>
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
                <x-ui-table-header-cell compact="true">Name / Code</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Owner</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Steps</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">LLM-Quote</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true"></x-ui-table-header-cell>
            </x-ui-table-header>

            <x-ui-table-body>
                @forelse($this->processes as $process)
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true">
                            <a href="{{ route('organization.processes.show', $process) }}" class="font-semibold text-[var(--ui-primary)] hover:underline" wire:navigate>
                                {{ $process->name }}
                            </a>
                            @if($process->code)
                                <code class="ml-2 text-xs text-[var(--ui-muted)]">{{ $process->code }}</code>
                            @endif
                            @if($process->description)
                                <div class="text-xs text-[var(--ui-muted)]">{{ \Illuminate\Support\Str::limit($process->description, 80) }}</div>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            @if($process->status === 'active')
                                <x-ui-badge variant="success">Aktiv</x-ui-badge>
                            @elseif($process->status === 'draft')
                                <x-ui-badge variant="muted">Entwurf</x-ui-badge>
                            @else
                                <x-ui-badge variant="danger">Veraltet</x-ui-badge>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="text-sm">{{ $process->ownerEntity?->name ?? '–' }}</span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="text-sm">{{ $process->steps_count }}</span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            @php
                                $totalSteps = $process->steps->count();
                                $llmSteps = $process->steps->whereIn('automation_level', ['llm_assisted', 'llm_autonomous', 'hybrid'])->count();
                                $llmQuote = $totalSteps > 0 ? round(($llmSteps / $totalSteps) * 100) : 0;
                            @endphp
                            @if($totalSteps > 0)
                                <div class="flex items-center gap-2">
                                    <div class="w-16 bg-[var(--ui-muted-20)] rounded-full h-1.5">
                                        <div class="h-1.5 rounded-full {{ $llmQuote >= 70 ? 'bg-[var(--ui-success)]' : ($llmQuote >= 30 ? 'bg-[var(--ui-info)]' : 'bg-[var(--ui-muted)]') }}" style="width: {{ $llmQuote }}%"></div>
                                    </div>
                                    <span class="text-xs font-medium text-[var(--ui-secondary)]">{{ $llmQuote }}%</span>
                                </div>
                            @else
                                <span class="text-xs text-[var(--ui-muted)]">–</span>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <div class="flex gap-1 justify-end">
                                <x-ui-button size="xs" variant="secondary-outline" wire:click="edit({{ $process->id }})">
                                    @svg('heroicon-o-pencil-square', 'w-4 h-4')
                                </x-ui-button>
                                <x-ui-confirm-button size="xs" variant="danger-outline" wire:click="delete({{ $process->id }})" confirm-text="Prozess wirklich löschen?">
                                    @svg('heroicon-o-trash', 'w-4 h-4')
                                </x-ui-confirm-button>
                            </div>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @empty
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true" colspan="6">
                            <div class="text-center text-[var(--ui-muted)] py-6">Keine Prozesse gefunden.</div>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @endforelse
            </x-ui-table-body>
        </x-ui-table>
    </x-ui-page-container>

    <!-- Create/Edit Modal -->
    <x-ui-modal wire:model="modalShow" size="lg">
        <x-slot name="header">
            {{ $editingId ? 'Prozess bearbeiten' : 'Neuen Prozess erstellen' }}
        </x-slot>

        <form wire:submit.prevent="store" class="space-y-4">
            <x-ui-input-text name="name" label="Name" wire:model.live="form.name" required placeholder="z.B. Onboarding neuer Mitarbeiter" />

            <x-ui-input-text name="code" label="Code (optional)" wire:model.live="form.code" placeholder="z.B. PROC-001" />

            <x-ui-input-textarea name="description" label="Beschreibung" wire:model.live="form.description" rows="3" />

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select wire:model.live="form.status" class="w-full rounded-md border-gray-300 shadow-sm">
                    <option value="draft">Entwurf</option>
                    <option value="active">Aktiv</option>
                    <option value="deprecated">Veraltet</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Owner (Entity)</label>
                    <select wire:model.live="form.owner_entity_id" class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">– Kein Owner –</option>
                        @foreach($this->availableEntities as $entity)
                            <option value="{{ $entity->id }}">{{ $entity->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">VSM System</label>
                    <select wire:model.live="form.vsm_system_id" class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">– Kein VSM System –</option>
                        @foreach($this->availableVsmSystems as $vsm)
                            <option value="{{ $vsm->id }}">{{ $vsm->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </form>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button type="button" variant="secondary-outline" wire:click="$set('modalShow', false)">
                    Abbrechen
                </x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="store">
                    @svg('heroicon-o-check', 'w-4 h-4 mr-2')
                    {{ $editingId ? 'Speichern' : 'Erstellen' }}
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>
</x-ui-page>
