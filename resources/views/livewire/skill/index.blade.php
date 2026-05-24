<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Skills'],
        ]">
            <x-ui-button variant="secondary-outline" size="sm" wire:click="$toggle('showMatrix')">
                @if($showMatrix)
                    @svg('heroicon-o-list-bullet', 'w-4 h-4')
                    <span>Katalog</span>
                @else
                    @svg('heroicon-o-table-cells', 'w-4 h-4')
                    <span>Matrix</span>
                @endif
            </x-ui-button>
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

            @if(!$showMatrix)
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
                                <th class="text-center py-3 px-4">Personen</th>
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
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-medium bg-[var(--ui-muted-5)] text-[var(--ui-secondary)]">{{ $item->persons_count }}</span>
                                    </td>
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
                                    <td colspan="{{ $activeTab === 'skills' ? 7 : 6 }}" class="py-12 text-center text-[var(--ui-muted)]">
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
            @else
                {{-- ═══ Matrix-Ansicht ═══ --}}
                @php
                    $persons = $this->personEntities;
                    $matrixData = $this->matrix;
                @endphp
                @if($persons->isEmpty())
                    <div class="bg-white rounded-lg border border-[var(--ui-border)] p-12 text-center">
                        @svg('heroicon-o-user-group', 'w-8 h-8 text-[var(--ui-muted)] mx-auto mb-2')
                        <p class="text-sm text-[var(--ui-muted)]">Keine Person-Entities vorhanden.</p>
                    </div>
                @elseif(empty($matrixData))
                    <div class="bg-white rounded-lg border border-[var(--ui-border)] p-12 text-center">
                        @svg('heroicon-o-academic-cap', 'w-8 h-8 text-[var(--ui-muted)] mx-auto mb-2')
                        <p class="text-sm text-[var(--ui-muted)]">Keine {{ $activeTab === 'skills' ? 'Skills' : 'Soft Skills' }} vorhanden.</p>
                    </div>
                @else
                    <div class="bg-white rounded-lg border border-[var(--ui-border)] overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-[var(--ui-border)]">
                                    <th class="text-left py-3 px-4 text-xs text-[var(--ui-muted)] uppercase sticky left-0 bg-white z-10 min-w-[200px]">
                                        {{ $activeTab === 'skills' ? 'Skill' : 'Soft Skill' }}
                                    </th>
                                    @foreach($persons as $person)
                                        <th class="text-center py-3 px-3 text-xs text-[var(--ui-muted)] min-w-[100px]">
                                            <div class="truncate max-w-[100px]" title="{{ $person->name }}">{{ $person->name }}</div>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($matrixData as $row)
                                    <tr class="border-b border-[var(--ui-border)]/50 hover:bg-[var(--ui-muted-5)]">
                                        <td class="py-2 px-4 font-medium text-[var(--ui-secondary)] sticky left-0 bg-white z-10">
                                            <div class="flex items-center gap-2">
                                                <span>{{ $row['skill']->name }}</span>
                                                @if($activeTab === 'skills' && $row['skill']->category)
                                                    <x-ui-badge variant="secondary" size="sm">{{ ucfirst($row['skill']->category) }}</x-ui-badge>
                                                @endif
                                            </div>
                                        </td>
                                        @foreach($row['cells'] as $cell)
                                            <td class="py-2 px-3 text-center">
                                                <button
                                                    wire:click="openAssignModal({{ $row['skill']->id }}, {{ $cell['person_id'] }})"
                                                    class="w-full flex items-center justify-center min-h-[28px] rounded transition-colors hover:bg-[var(--ui-primary-5)] cursor-pointer"
                                                >
                                                    @if($cell['level'])
                                                        @php
                                                            $levelConfig = [
                                                                'basic' => ['variant' => 'muted', 'label' => 'B'],
                                                                'advanced' => ['variant' => 'info', 'label' => 'A'],
                                                                'expert' => ['variant' => 'success', 'label' => 'E'],
                                                            ];
                                                            $lc = $levelConfig[$cell['level']] ?? $levelConfig['basic'];
                                                        @endphp
                                                        <x-ui-badge variant="{{ $lc['variant'] }}" size="sm">{{ $lc['label'] }}</x-ui-badge>
                                                    @else
                                                        <span class="text-[var(--ui-border)]">—</span>
                                                    @endif
                                                </button>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Legende --}}
                    <div class="mt-3 flex items-center gap-4 text-xs text-[var(--ui-muted)]">
                        <span class="flex items-center gap-1"><x-ui-badge variant="muted" size="sm">B</x-ui-badge> Basic</span>
                        <span class="flex items-center gap-1"><x-ui-badge variant="info" size="sm">A</x-ui-badge> Advanced</span>
                        <span class="flex items-center gap-1"><x-ui-badge variant="success" size="sm">E</x-ui-badge> Expert</span>
                    </div>
                @endif
            @endif
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

        {{-- Assignment Modal --}}
        @if($showAssignModal)
            <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center" wire:click.self="$set('showAssignModal', false)">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-sm p-6">
                    <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Skill-Level zuordnen</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Level</label>
                            <select wire:model="assignLevel" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="basic">Basic</option>
                                <option value="advanced">Advanced</option>
                                <option value="expert">Expert</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-between mt-6">
                        <x-ui-button variant="secondary-ghost" size="sm" wire:click="removeAssignment">
                            @svg('heroicon-o-trash', 'w-4 h-4')
                            <span>Entfernen</span>
                        </x-ui-button>
                        <div class="flex gap-2">
                            <x-ui-button variant="secondary-ghost" size="sm" wire:click="$set('showAssignModal', false)">
                                Abbrechen
                            </x-ui-button>
                            <x-ui-button variant="primary" size="sm" wire:click="saveAssignment">
                                @svg('heroicon-o-check', 'w-4 h-4')
                                <span>Speichern</span>
                            </x-ui-button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </x-ui-page-container>
</x-ui-page>
