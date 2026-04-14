<div style="padding-left: {{ ($depth + 1) * 24 }}px;">
    <div class="flex items-center gap-3 py-2 px-3 hover:bg-[var(--ui-muted-5)] rounded transition-colors group">
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
                @svg('heroicon-o-user-circle', 'w-3.5 h-3.5 text-[var(--ui-muted)] flex-shrink-0')
                <span class="text-sm font-medium text-[var(--ui-secondary)] truncate">{{ $item->name }}</span>
                @if($item->slug)
                    <code class="text-[10px] text-[var(--ui-muted)] flex-shrink-0">{{ $item->slug }}</code>
                @endif
                <x-ui-badge variant="{{ $item->status === 'active' ? 'success' : 'muted' }}" size="sm">
                    {{ ucfirst($item->status) }}
                </x-ui-badge>
            </div>
            @if($item->description)
                <div class="text-xs text-[var(--ui-muted)] ml-5.5 truncate">{{ \Illuminate\Support\Str::limit($item->description, 80) }}</div>
            @endif
        </div>

        <button type="button" wire:click="toggleAssignments({{ $item->id }})" class="text-xs text-[var(--ui-primary)] hover:underline cursor-pointer flex-shrink-0">
            {{ $item->assignments_count }} Zuweisung(en)
            @if($expandedRoleId === $item->id)
                @svg('heroicon-o-chevron-up', 'w-3 h-3 inline')
            @else
                @svg('heroicon-o-chevron-down', 'w-3 h-3 inline')
            @endif
        </button>

        <div class="flex gap-1 flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
            <x-ui-button size="xs" variant="secondary-outline" wire:click="edit({{ $item->id }})">
                @svg('heroicon-o-pencil-square', 'w-3.5 h-3.5')
            </x-ui-button>
            @if($item->status === 'archived')
                <x-ui-button size="xs" variant="secondary-outline" wire:click="unarchive({{ $item->id }})" title="Reaktivieren">
                    @svg('heroicon-o-arrow-uturn-left', 'w-3.5 h-3.5')
                </x-ui-button>
            @else
                <x-ui-button size="xs" variant="secondary-outline" wire:click="archive({{ $item->id }})" title="Archivieren">
                    @svg('heroicon-o-archive-box', 'w-3.5 h-3.5')
                </x-ui-button>
            @endif
            <x-ui-button size="xs" variant="danger-outline" wire:click="delete({{ $item->id }})" wire:confirm="Rolle wirklich löschen?">
                @svg('heroicon-o-trash', 'w-3.5 h-3.5')
            </x-ui-button>
        </div>
    </div>

    {{-- Expandable assignments panel --}}
    @if($expandedRoleId === $item->id)
        <div class="ml-6 mb-2 px-3 py-3 bg-[var(--ui-bg-secondary)] rounded-lg border border-[var(--ui-border)]/40">
            @if($item->assignments->isNotEmpty())
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
                        @foreach($item->assignments as $a)
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
        </div>
    @endif
</div>
