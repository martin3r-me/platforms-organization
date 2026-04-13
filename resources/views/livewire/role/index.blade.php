<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Rollen'],
        ]">
            <x-ui-button variant="primary" size="sm" wire:click="create">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neue Rolle</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Suche</h3>
                    <x-ui-input-text name="search" wire:model.live="search" placeholder="Name, Slug, Beschreibung..." class="w-full" size="sm" />
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Status</h3>
                    <select wire:model.live="statusFilter" class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="active">Aktiv</option>
                        <option value="archived">Archiviert</option>
                        <option value="">Alle</option>
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
                <x-ui-table-header-cell compact="true">Slug</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Zuweisungen</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true"></x-ui-table-header-cell>
            </x-ui-table-header>

            <x-ui-table-body>
                @forelse($this->roles as $role)
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true">
                            <div class="font-medium">{{ $role->name }}</div>
                            @if($role->description)
                                <div class="text-xs text-[var(--ui-muted)]">{{ \Illuminate\Support\Str::limit($role->description, 80) }}</div>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <code class="text-xs text-[var(--ui-muted)]">{{ $role->slug }}</code>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <x-ui-badge variant="{{ $role->status === 'active' ? 'success' : 'muted' }}">
                                {{ ucfirst($role->status) }}
                            </x-ui-badge>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <button type="button" wire:click="toggleAssignments({{ $role->id }})" class="text-sm text-[var(--ui-primary)] hover:underline cursor-pointer">
                                {{ $role->assignments_count }} Zuweisung(en)
                                @if($expandedRoleId === $role->id)
                                    @svg('heroicon-o-chevron-up', 'w-3 h-3 inline')
                                @else
                                    @svg('heroicon-o-chevron-down', 'w-3 h-3 inline')
                                @endif
                            </button>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <div class="flex gap-1 justify-end">
                                <x-ui-button size="xs" variant="secondary-outline" wire:click="edit({{ $role->id }})">
                                    @svg('heroicon-o-pencil-square', 'w-4 h-4')
                                </x-ui-button>
                                @if($role->status === 'archived')
                                    <x-ui-button size="xs" variant="secondary-outline" wire:click="unarchive({{ $role->id }})" title="Reaktivieren">
                                        @svg('heroicon-o-arrow-uturn-left', 'w-4 h-4')
                                    </x-ui-button>
                                @else
                                    <x-ui-button size="xs" variant="secondary-outline" wire:click="archive({{ $role->id }})" title="Archivieren">
                                        @svg('heroicon-o-archive-box', 'w-4 h-4')
                                    </x-ui-button>
                                @endif
                                <x-ui-button size="xs" variant="danger-outline" wire:click="delete({{ $role->id }})" wire:confirm="Rolle wirklich löschen?">
                                    @svg('heroicon-o-trash', 'w-4 h-4')
                                </x-ui-button>
                            </div>
                        </x-ui-table-cell>
                    </x-ui-table-row>

                    @if($expandedRoleId === $role->id)
                        <tr>
                            <td colspan="5" class="px-4 py-3 bg-[var(--ui-bg-secondary)]">
                                {{-- Existing role assignments --}}
                                @if($role->assignments->isNotEmpty())
                                    <table class="w-full text-sm mb-3">
                                        <thead>
                                            <tr class="text-xs text-[var(--ui-muted)] uppercase">
                                                <th class="text-left py-1 px-2">Person</th>
                                                <th class="text-left py-1 px-2">Kontext</th>
                                                <th class="text-left py-1 px-2">%</th>
                                                <th class="text-left py-1 px-2">Gültig ab</th>
                                                <th class="text-left py-1 px-2">Gültig bis</th>
                                                <th class="text-left py-1 px-2">Notiz</th>
                                                <th class="py-1 px-2"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($role->assignments as $a)
                                                <tr class="border-t border-[var(--ui-border)]">
                                                    <td class="py-1.5 px-2">{{ $a->person?->name ?? '—' }}</td>
                                                    <td class="py-1.5 px-2">{{ $a->context?->name ?? '—' }}</td>
                                                    <td class="py-1.5 px-2">{{ $a->percentage ? $a->percentage.'%' : '—' }}</td>
                                                    <td class="py-1.5 px-2">{{ $a->valid_from?->format('d.m.Y') ?? '—' }}</td>
                                                    <td class="py-1.5 px-2">{{ $a->valid_to?->format('d.m.Y') ?? '—' }}</td>
                                                    <td class="py-1.5 px-2 text-xs text-[var(--ui-muted)]">{{ $a->note ?? '' }}</td>
                                                    <td class="py-1.5 px-2 text-right">
                                                        <button type="button" wire:click="deleteRoleAssignment({{ $a->id }})" wire:confirm="Zuweisung wirklich entfernen?" class="text-red-500 hover:text-red-700">
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

                                {{-- Add new role assignment form --}}
                                <div class="flex items-end gap-2 flex-wrap border-t border-[var(--ui-border)] pt-3">
                                    <div class="w-48">
                                        <x-ui-input-select name="roleAssignForm.person_entity_id" label="Person" :options="$this->groupedPersonOptions" wire:model="roleAssignForm.person_entity_id" nullable nullLabel="— Person wählen —" size="sm" />
                                    </div>
                                    <div class="w-52">
                                        <x-ui-input-select name="roleAssignForm.context_entity_id" label="Kontext" :options="$this->groupedEntityOptions" wire:model="roleAssignForm.context_entity_id" nullable nullLabel="— Optional —" size="sm" />
                                    </div>
                                    <div class="w-20">
                                        <x-ui-input-text name="roleAssignForm.percentage" label="%" wire:model="roleAssignForm.percentage" size="sm" type="number" />
                                    </div>
                                    <div class="w-32">
                                        <x-ui-input-text name="roleAssignForm.valid_from" label="Von" wire:model="roleAssignForm.valid_from" size="sm" type="date" />
                                    </div>
                                    <div class="w-32">
                                        <x-ui-input-text name="roleAssignForm.valid_to" label="Bis" wire:model="roleAssignForm.valid_to" size="sm" type="date" />
                                    </div>
                                    <div class="flex-1 min-w-[120px]">
                                        <x-ui-input-text name="roleAssignForm.note" label="Notiz" wire:model="roleAssignForm.note" size="sm" />
                                    </div>
                                    <div class="pb-0.5">
                                        <x-ui-button size="sm" variant="primary" wire:click="storeRoleAssignment">
                                            @svg('heroicon-o-plus', 'w-4 h-4')
                                        </x-ui-button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true" colspan="5">
                            <div class="text-center text-[var(--ui-muted)] py-6">Keine Rollen gefunden.</div>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @endforelse
            </x-ui-table-body>
        </x-ui-table>
    </x-ui-page-container>

    <!-- Create/Edit Modal -->
    <x-ui-modal wire:model="modalShow" size="lg">
        <x-slot name="header">
            {{ $editingId ? 'Rolle bearbeiten' : 'Neue Rolle erstellen' }}
        </x-slot>

        <form wire:submit.prevent="store" class="space-y-4">
            <x-ui-input-text name="name" label="Name" wire:model.live="form.name" required placeholder="z.B. Projektleiter" />

            <x-ui-input-text name="slug" label="Slug (optional, wird automatisch erzeugt)" wire:model.live="form.slug" placeholder="project-lead" />

            <x-ui-input-textarea name="description" label="Beschreibung" wire:model.live="form.description" rows="3" />

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select wire:model.live="form.status" class="w-full rounded-md border-gray-300 shadow-sm">
                    <option value="active">Aktiv</option>
                    <option value="archived">Archiviert</option>
                </select>
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
