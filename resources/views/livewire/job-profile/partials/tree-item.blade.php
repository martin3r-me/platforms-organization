<div style="padding-left: {{ ($depth + 1) * 24 }}px;">
    <div class="flex items-center gap-3 py-2 px-3 hover:bg-[var(--ui-muted-5)] rounded transition-colors group">
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
                @svg('heroicon-o-identification', 'w-3.5 h-3.5 text-[var(--ui-muted)] flex-shrink-0')
                <a href="{{ route('organization.job-profiles.show', $item) }}" wire:navigate class="text-sm font-medium text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] hover:underline truncate">{{ $item->name }}</a>
                @if($item->level)
                    <x-ui-badge variant="secondary" size="sm">{{ ucfirst($item->level) }}</x-ui-badge>
                @endif
                @if($item->job_family)
                    <x-ui-badge variant="info" size="sm">{{ $item->job_family }}</x-ui-badge>
                @endif
                <x-ui-badge variant="{{ $item->status === 'active' ? 'success' : ($item->status === 'archived' ? 'muted' : 'info') }}" size="sm">
                    {{ ucfirst($item->status) }}
                </x-ui-badge>
            </div>
            @if($item->description)
                <div class="text-xs text-[var(--ui-muted)] ml-5.5 truncate">{{ \Illuminate\Support\Str::limit($item->description, 80) }}</div>
            @endif
        </div>

        <button type="button" wire:click="toggleAssignments({{ $item->id }})" class="text-xs text-[var(--ui-primary)] hover:underline cursor-pointer flex-shrink-0">
            {{ $item->assignments_count }} Person(en)
            @if($expandedProfileId === $item->id)
                @svg('heroicon-o-chevron-up', 'w-3 h-3 inline')
            @else
                @svg('heroicon-o-chevron-down', 'w-3 h-3 inline')
            @endif
        </button>

        <span class="text-xs text-[var(--ui-muted)] flex-shrink-0">
            {{ $item->effective_from?->format('d.m.Y') ?? '—' }}
            @if($item->effective_to)
                – {{ $item->effective_to->format('d.m.Y') }}
            @endif
        </span>

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
            <x-ui-button size="xs" variant="danger-outline" wire:click="delete({{ $item->id }})" wire:confirm="JobProfile wirklich löschen?">
                @svg('heroicon-o-trash', 'w-3.5 h-3.5')
            </x-ui-button>
        </div>
    </div>

    {{-- Expandable assignments panel --}}
    @if($expandedProfileId === $item->id)
        <div class="ml-6 mb-2 px-3 py-3 bg-[var(--ui-bg-secondary)] rounded-lg border border-[var(--ui-border)]/40">
            @if($item->assignments->isNotEmpty())
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
                        @foreach($item->assignments as $a)
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

            <div class="flex items-end gap-2 flex-wrap border-t border-[var(--ui-border)] pt-3">
                <div class="w-48">
                    <x-ui-input-select name="assignForm.person_entity_id" label="Person" :options="$this->groupedPersonOptions" wire:model="assignForm.person_entity_id" nullable nullLabel="— Person wählen —" size="sm" />
                </div>
                <div class="w-20">
                    <x-ui-input-text name="assignForm.percentage" label="%" wire:model="assignForm.percentage" size="sm" type="number" />
                </div>
                <div class="flex items-center gap-1 pb-1">
                    <input type="checkbox" wire:model="assignForm.is_primary" id="assign_primary_{{ $item->id }}" class="rounded border-gray-300">
                    <label for="assign_primary_{{ $item->id }}" class="text-xs">Primär</label>
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
        </div>
    @endif
</div>
