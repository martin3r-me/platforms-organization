<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'JobProfiles'],
        ]">
            <x-ui-button variant="primary" size="sm" wire:click="create">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neues JobProfile</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Suche</h3>
                    <x-ui-input-text name="search" wire:model.live="search" placeholder="Name, Beschreibung, Inhalt..." class="w-full" size="sm" />
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Status</h3>
                    <select wire:model.live="statusFilter" class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="active">Aktiv</option>
                        <option value="draft">Entwurf</option>
                        <option value="archived">Archiviert</option>
                        <option value="">Alle</option>
                    </select>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Level</h3>
                    <select wire:model.live="levelFilter" class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">Alle</option>
                        <option value="junior">Junior</option>
                        <option value="mid">Mid</option>
                        <option value="senior">Senior</option>
                        <option value="lead">Lead</option>
                        <option value="principal">Principal</option>
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
                <x-ui-table-header-cell compact="true">Name</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Level</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Zuweisungen</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Gültig</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true"></x-ui-table-header-cell>
            </x-ui-table-header>

            <x-ui-table-body>
                @forelse($this->jobProfiles as $jp)
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true">
                            <div class="font-medium">{{ $jp->name }}</div>
                            @if($jp->description)
                                <div class="text-xs text-[var(--ui-muted)]">{{ \Illuminate\Support\Str::limit($jp->description, 80) }}</div>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            @if($jp->level)
                                <x-ui-badge variant="secondary">{{ ucfirst($jp->level) }}</x-ui-badge>
                            @else
                                <span class="text-[var(--ui-muted)]">—</span>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <x-ui-badge variant="{{ $jp->status === 'active' ? 'success' : ($jp->status === 'archived' ? 'muted' : 'info') }}">
                                {{ ucfirst($jp->status) }}
                            </x-ui-badge>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <button type="button" wire:click="toggleAssignments({{ $jp->id }})" class="text-sm text-[var(--ui-primary)] hover:underline cursor-pointer">
                                {{ $jp->assignments_count }} Person(en)
                                @if($expandedProfileId === $jp->id)
                                    @svg('heroicon-o-chevron-up', 'w-3 h-3 inline')
                                @else
                                    @svg('heroicon-o-chevron-down', 'w-3 h-3 inline')
                                @endif
                            </button>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <div class="text-xs text-[var(--ui-muted)]">
                                {{ $jp->effective_from?->format('d.m.Y') ?? '—' }}
                                @if($jp->effective_to)
                                    – {{ $jp->effective_to->format('d.m.Y') }}
                                @endif
                            </div>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <div class="flex gap-1 justify-end">
                                <x-ui-button size="xs" variant="secondary-outline" wire:click="edit({{ $jp->id }})">
                                    @svg('heroicon-o-pencil-square', 'w-4 h-4')
                                </x-ui-button>
                                @if($jp->status === 'archived')
                                    <x-ui-button size="xs" variant="secondary-outline" wire:click="unarchive({{ $jp->id }})" title="Reaktivieren">
                                        @svg('heroicon-o-arrow-uturn-left', 'w-4 h-4')
                                    </x-ui-button>
                                @else
                                    <x-ui-button size="xs" variant="secondary-outline" wire:click="archive({{ $jp->id }})" title="Archivieren">
                                        @svg('heroicon-o-archive-box', 'w-4 h-4')
                                    </x-ui-button>
                                @endif
                                <x-ui-button size="xs" variant="danger-outline" wire:click="delete({{ $jp->id }})" wire:confirm="JobProfile wirklich löschen?">
                                    @svg('heroicon-o-trash', 'w-4 h-4')
                                </x-ui-button>
                            </div>
                        </x-ui-table-cell>
                    </x-ui-table-row>

                    @if($expandedProfileId === $jp->id)
                        <tr>
                            <td colspan="6" class="px-4 py-3 bg-[var(--ui-bg-secondary)]">
                                {{-- Existing assignments --}}
                                @if($jp->assignments->isNotEmpty())
                                    <table class="w-full text-sm mb-3">
                                        <thead>
                                            <tr class="text-xs text-[var(--ui-muted)] uppercase">
                                                <th class="text-left py-1 px-2">Person</th>
                                                <th class="text-left py-1 px-2">%</th>
                                                <th class="text-left py-1 px-2">Primär</th>
                                                <th class="text-left py-1 px-2">Gültig ab</th>
                                                <th class="text-left py-1 px-2">Gültig bis</th>
                                                <th class="text-left py-1 px-2">Notiz</th>
                                                <th class="py-1 px-2"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($jp->assignments as $a)
                                                <tr class="border-t border-[var(--ui-border)]">
                                                    <td class="py-1.5 px-2">{{ $a->person?->name ?? '—' }}</td>
                                                    <td class="py-1.5 px-2">{{ $a->percentage ?? '—' }}%</td>
                                                    <td class="py-1.5 px-2">
                                                        @if($a->is_primary)
                                                            @svg('heroicon-o-check', 'w-4 h-4 text-green-500')
                                                        @else
                                                            <span class="text-[var(--ui-muted)]">—</span>
                                                        @endif
                                                    </td>
                                                    <td class="py-1.5 px-2">{{ $a->valid_from?->format('d.m.Y') ?? '—' }}</td>
                                                    <td class="py-1.5 px-2">{{ $a->valid_to?->format('d.m.Y') ?? '—' }}</td>
                                                    <td class="py-1.5 px-2 text-xs text-[var(--ui-muted)]">{{ $a->note ?? '' }}</td>
                                                    <td class="py-1.5 px-2 text-right">
                                                        <button type="button" wire:click="deleteAssignment({{ $a->id }})" wire:confirm="Zuweisung wirklich entfernen?" class="text-red-500 hover:text-red-700">
                                                            @svg('heroicon-o-x-mark', 'w-4 h-4')
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @else
                                    <p class="text-sm text-[var(--ui-muted)] mb-3">Keine Zuweisungen vorhanden.</p>
                                @endif

                                {{-- Add new assignment form --}}
                                <div class="flex items-end gap-2 flex-wrap border-t border-[var(--ui-border)] pt-3">
                                    <div class="w-48">
                                        <x-ui-input-select name="assignForm.person_entity_id" label="Person" :options="$this->groupedPersonOptions" wire:model="assignForm.person_entity_id" nullable nullLabel="— Person wählen —" size="sm" />
                                    </div>
                                    <div class="w-20">
                                        <x-ui-input-text name="assignForm.percentage" label="%" wire:model="assignForm.percentage" size="sm" type="number" />
                                    </div>
                                    <div class="flex items-center gap-1 pb-1">
                                        <input type="checkbox" wire:model="assignForm.is_primary" id="assign_primary_{{ $jp->id }}" class="rounded border-gray-300">
                                        <label for="assign_primary_{{ $jp->id }}" class="text-xs">Primär</label>
                                    </div>
                                    <div class="w-32">
                                        <x-ui-input-text name="assignForm.valid_from" label="Von" wire:model="assignForm.valid_from" size="sm" type="date" />
                                    </div>
                                    <div class="w-32">
                                        <x-ui-input-text name="assignForm.valid_to" label="Bis" wire:model="assignForm.valid_to" size="sm" type="date" />
                                    </div>
                                    <div class="flex-1 min-w-[120px]">
                                        <x-ui-input-text name="assignForm.note" label="Notiz" wire:model="assignForm.note" size="sm" />
                                    </div>
                                    <div class="pb-0.5">
                                        <x-ui-button size="sm" variant="primary" wire:click="storeAssignment">
                                            @svg('heroicon-o-plus', 'w-4 h-4')
                                        </x-ui-button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true" colspan="6">
                            <div class="text-center text-[var(--ui-muted)] py-6">Keine JobProfiles gefunden.</div>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @endforelse
            </x-ui-table-body>
        </x-ui-table>
    </x-ui-page-container>

    <!-- Create/Edit Modal -->
    <x-ui-modal wire:model="modalShow" size="lg">
        <x-slot name="header">
            {{ $editingId ? 'JobProfile bearbeiten' : 'Neues JobProfile erstellen' }}
        </x-slot>

        <form wire:submit.prevent="store" class="space-y-4">
            <x-ui-input-text name="name" label="Name" wire:model.live="form.name" required placeholder="z.B. Senior Backend Engineer" />

            <x-ui-input-textarea name="description" label="Kurzbeschreibung" wire:model.live="form.description" rows="2" />

            <x-ui-input-textarea name="content" label="Ausführliches Profil (Markdown)" wire:model.live="form.content" rows="6" />

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Level</label>
                    <select wire:model.live="form.level" class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">—</option>
                        <option value="junior">Junior</option>
                        <option value="mid">Mid</option>
                        <option value="senior">Senior</option>
                        <option value="lead">Lead</option>
                        <option value="principal">Principal</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select wire:model.live="form.status" class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="active">Aktiv</option>
                        <option value="draft">Entwurf</option>
                        <option value="archived">Archiviert</option>
                    </select>
                </div>
            </div>

            <x-ui-input-text name="skills" label="Skills (komma-getrennt)" wire:model.live="form.skills" placeholder="PHP, Laravel, PostgreSQL" />
            <x-ui-input-text name="responsibilities" label="Verantwortlichkeiten (komma-getrennt)" wire:model.live="form.responsibilities" placeholder="Code Reviews, Architektur, Mentoring" />

            <div class="grid grid-cols-2 gap-4">
                <x-ui-input-text type="date" name="effective_from" label="Gültig ab" wire:model.live="form.effective_from" />
                <x-ui-input-text type="date" name="effective_to" label="Gültig bis" wire:model.live="form.effective_to" />
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
