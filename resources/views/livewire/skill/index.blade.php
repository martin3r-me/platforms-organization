<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Skills'],
        ]">
            <x-ui-button variant="primary" size="sm" wire:click="openCreate">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neu</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
        <div x-data="{ tab: @entangle('activeTab') }">
            {{-- Tab Navigation --}}
            <div class="border-b border-[var(--ui-border)] mb-6">
                <nav class="flex gap-1 -mb-px">
                    <button
                        @click="tab = 'skills'"
                        :class="tab === 'skills'
                            ? 'border-b-2 border-[var(--ui-primary)] text-[var(--ui-primary)] font-semibold'
                            : 'border-b-2 border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:border-[var(--ui-border)]'"
                        class="px-4 py-2.5 text-sm transition-colors"
                    >
                        @svg('heroicon-o-academic-cap', 'w-4 h-4 inline-block mr-1.5 -mt-0.5')
                        Skills
                    </button>
                    <button
                        @click="tab = 'soft_skills'"
                        :class="tab === 'soft_skills'
                            ? 'border-b-2 border-[var(--ui-primary)] text-[var(--ui-primary)] font-semibold'
                            : 'border-b-2 border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:border-[var(--ui-border)]'"
                        class="px-4 py-2.5 text-sm transition-colors"
                    >
                        @svg('heroicon-o-heart', 'w-4 h-4 inline-block mr-1.5 -mt-0.5')
                        Soft Skills
                    </button>
                </nav>
            </div>

            {{-- Filters --}}
            <div class="flex items-center gap-3 mb-6">
                <div class="flex-1 max-w-sm">
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Suchen..."
                        class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
                </div>
                @if($activeTab === 'skills')
                    <select wire:model.live="categoryFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">Alle Kategorien</option>
                        <option value="technical">Technical</option>
                        <option value="methodical">Methodical</option>
                        <option value="domain">Domain</option>
                    </select>
                @endif
            </div>

            {{-- ═══ Katalog-Ansicht ═══ --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)]">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs text-[var(--ui-muted)] uppercase border-b border-[var(--ui-border)]">
                                <th class="text-left py-3 px-4">Name</th>
                                @if($activeTab === 'skills')
                                    <th class="text-left py-3 px-4">Kategorie</th>
                                @endif
                                <th class="text-left py-3 px-4">Beschreibung</th>
                                <th class="text-center py-3 px-4">JobProfiles</th>
                                <th class="text-center py-3 px-4">Status</th>
                                <th class="text-right py-3 px-4">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $items = $activeTab === 'skills' ? $this->skills : $this->softSkills; @endphp
                            @forelse($items as $item)
                                <tr class="border-b border-[var(--ui-border)]/50 hover:bg-[var(--ui-muted-5)] transition-colors {{ !$item->is_active ? 'opacity-50' : '' }}">
                                    <td class="py-3 px-4 font-medium text-[var(--ui-secondary)]">{{ $item->name }}</td>
                                    @if($activeTab === 'skills')
                                        <td class="py-3 px-4">
                                            <x-ui-badge variant="secondary" size="sm">{{ ucfirst($item->category) }}</x-ui-badge>
                                        </td>
                                    @endif
                                    <td class="py-3 px-4 text-[var(--ui-muted)] text-xs max-w-xs truncate">{{ $item->description ?? '—' }}</td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-medium bg-[var(--ui-muted-5)] text-[var(--ui-secondary)]">{{ $item->job_profiles_count }}</span>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <button wire:click="toggleActive({{ $item->id }})" class="cursor-pointer">
                                            @if($item->is_active)
                                                <x-ui-badge variant="success" size="sm">Aktiv</x-ui-badge>
                                            @else
                                                <x-ui-badge variant="muted" size="sm">Inaktiv</x-ui-badge>
                                            @endif
                                        </button>
                                    </td>
                                    <td class="py-3 px-4 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <button wire:click="openEdit({{ $item->id }})" class="p-1 text-[var(--ui-muted)] hover:text-[var(--ui-primary)] transition-colors">
                                                @svg('heroicon-o-pencil', 'w-4 h-4')
                                            </button>
                                            <button wire:click="deleteItem({{ $item->id }})" wire:confirm="Wirklich löschen?" class="p-1 text-[var(--ui-muted)] hover:text-red-500 transition-colors">
                                                @svg('heroicon-o-trash', 'w-4 h-4')
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $activeTab === 'skills' ? 6 : 5 }}" class="py-12 text-center text-[var(--ui-muted)]">
                                        <div class="flex flex-col items-center gap-2">
                                            @svg('heroicon-o-academic-cap', 'w-8 h-8 text-[var(--ui-muted)]')
                                            <span>Keine {{ $activeTab === 'skills' ? 'Skills' : 'Soft Skills' }} vorhanden.</span>
                                            <x-ui-button variant="primary" size="sm" wire:click="openCreate">
                                                @svg('heroicon-o-plus', 'w-4 h-4') Erstellen
                                            </x-ui-button>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
        </div>

        {{-- Create/Edit Modal --}}
        @if($showCreateModal)
            <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center" wire:click.self="$set('showCreateModal', false)">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
                    <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">
                        {{ $editingId ? ($activeTab === 'skills' ? 'Skill bearbeiten' : 'Soft Skill bearbeiten') : ($activeTab === 'skills' ? 'Neuer Skill' : 'Neuer Soft Skill') }}
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                            <input type="text" wire:model="form.name" class="w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="Skill-Name" />
                            @error('form.name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        @if($activeTab === 'skills')
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Kategorie</label>
                                <select wire:model="form.category" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="technical">Technical</option>
                                    <option value="methodical">Methodical</option>
                                    <option value="domain">Domain</option>
                                </select>
                            </div>
                        @endif
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Beschreibung</label>
                            <textarea wire:model="form.description" rows="3" class="w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="Optional"></textarea>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 mt-6">
                        <x-ui-button variant="secondary-ghost" size="sm" wire:click="$set('showCreateModal', false)">
                            Abbrechen
                        </x-ui-button>
                        <x-ui-button variant="primary" size="sm" wire:click="saveItem">
                            @svg('heroicon-o-check', 'w-4 h-4')
                            <span>{{ $editingId ? 'Speichern' : 'Erstellen' }}</span>
                        </x-ui-button>
                    </div>
                </div>
            </div>
        @endif
    </x-ui-page-container>
</x-ui-page>
