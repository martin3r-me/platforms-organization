{{-- Eine Rolle in der Owner-Liste. Aufgeklappt: Zuweisungen + Formular für neue. --}}
@php
    $open = $expandedRoleId === $item->id;
    $vsmLabel = $item->vsm_system ? (\Platform\Organization\Models\OrganizationRole::VSM_LABELS[$item->vsm_system] ?? $item->vsm_system) : null;
@endphp
<div wire:key="role-{{ $item->id }}" class="border-b border-[color:var(--nx-line)]">
    <div class="flex items-center gap-3 py-2 pr-3 hover:bg-[color:var(--nx-hover)]" style="padding-left: {{ 1 + ($depth + 1) * 1.25 }}rem">
        <button type="button" wire:click="edit({{ $item->id }})" class="min-w-0 flex-1 text-left">
            <div class="flex flex-wrap items-center gap-2">
                <span class="truncate text-sm font-medium text-[color:var(--nx-text)]">{{ $item->name }}</span>
                @if ($item->slug)
                    <span class="font-mono text-xs text-[color:var(--nx-faint)]">{{ $item->slug }}</span>
                @endif
                @if ($item->status !== 'active')
                    <x-nx-badge>Archiviert</x-nx-badge>
                @endif
                @if ($vsmLabel)
                    <span class="rounded-[4px] bg-[rgba(25,113,194,.12)] px-1.5 py-0.5 text-[10px] font-medium text-[color:var(--nx-info)]" title="VSM-Funktion: {{ $vsmLabel }}">{{ strtoupper(str_replace('_', '', $item->vsm_system)) }}</span>
                @endif
                @foreach ($item->capabilities ?? [] as $cap)
                    <x-nx-badge variant="accent">{{ $cap }}</x-nx-badge>
                @endforeach
            </div>
            @if ($item->description)
                <div class="mt-0.5 truncate text-xs text-[color:var(--nx-muted)]">{{ \Illuminate\Support\Str::limit($item->description, 100) }}</div>
            @endif
        </button>

        <button type="button" wire:click="toggleAssignments({{ $item->id }})"
            class="inline-flex shrink-0 items-center gap-1 rounded-[6px] px-2 py-1 text-xs text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)] hover:text-[color:var(--nx-text)]">
            {{ $item->assignments_count }} {{ $item->assignments_count === 1 ? 'Zuweisung' : 'Zuweisungen' }}
            @svg($open ? 'heroicon-o-chevron-up' : 'heroicon-o-chevron-down', 'w-3 h-3')
        </button>

        <x-nx-dropdown>
            <x-nx-dropdown-item wire:click="edit({{ $item->id }})">
                @svg('heroicon-o-pencil-square', 'w-4 h-4')
                Bearbeiten
            </x-nx-dropdown-item>
            @if ($item->status === 'archived')
                <x-nx-dropdown-item wire:click="unarchive({{ $item->id }})">
                    @svg('heroicon-o-arrow-uturn-left', 'w-4 h-4')
                    Reaktivieren
                </x-nx-dropdown-item>
            @else
                <x-nx-dropdown-item wire:click="archive({{ $item->id }})">
                    @svg('heroicon-o-archive-box', 'w-4 h-4')
                    Archivieren
                </x-nx-dropdown-item>
            @endif
            <x-nx-dropdown-divider />
            <x-nx-dropdown-item variant="danger" wire:click="delete({{ $item->id }})" wire:confirm="Rolle „{{ $item->name }}“ wirklich löschen?">
                @svg('heroicon-o-trash', 'w-4 h-4')
                Löschen
            </x-nx-dropdown-item>
        </x-nx-dropdown>
    </div>

    @if ($open)
        @php
            $assignments = $this->expandedAssignments;
        @endphp
        <div class="mb-3 mr-3 rounded-[8px] border border-[color:var(--nx-line)] bg-[color:var(--nx-bg)]" style="margin-left: {{ 1 + ($depth + 1) * 1.25 }}rem">
            @if ($assignments->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs text-[color:var(--nx-muted)]">
                                <th class="border-b border-[color:var(--nx-line)] px-3 py-2 text-left font-medium">Person</th>
                                <th class="border-b border-[color:var(--nx-line)] px-3 py-2 text-left font-medium">Kontext</th>
                                <th class="border-b border-[color:var(--nx-line)] px-3 py-2 text-right font-medium">%</th>
                                <th class="border-b border-[color:var(--nx-line)] px-3 py-2 text-left font-medium">Gültig</th>
                                <th class="border-b border-[color:var(--nx-line)] px-3 py-2 text-left font-medium">Notiz</th>
                                <th class="border-b border-[color:var(--nx-line)] px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($assignments as $a)
                                <tr wire:key="ra-{{ $a->id }}" class="border-b border-[color:var(--nx-line)] last:border-0">
                                    <td class="px-3 py-1.5 text-[color:var(--nx-text)]">{{ $a->person?->name ?? '–' }}</td>
                                    <td class="px-3 py-1.5 text-[color:var(--nx-muted)]">{{ $a->context?->name ?? '–' }}</td>
                                    <td class="px-3 py-1.5 text-right tabular-nums text-[color:var(--nx-muted)]">{{ $a->percentage ? $a->percentage . ' %' : '–' }}</td>
                                    <td class="whitespace-nowrap px-3 py-1.5 text-xs text-[color:var(--nx-muted)]">
                                        {{ $a->valid_from?->format('d.m.Y') ?? '–' }} – {{ $a->valid_to?->format('d.m.Y') ?? 'offen' }}
                                    </td>
                                    <td class="max-w-xs truncate px-3 py-1.5 text-xs text-[color:var(--nx-muted)]">{{ $a->note }}</td>
                                    <td class="px-3 py-1.5 text-right">
                                        <button type="button" wire:click="deleteRoleAssignment({{ $a->id }})" wire:confirm="Zuweisung wirklich entfernen?"
                                            class="rounded-[6px] p-1 text-[color:var(--nx-faint)] hover:bg-[rgba(224,49,49,.08)] hover:text-[color:var(--nx-danger)]" title="Entfernen">
                                            @svg('heroicon-o-x-mark', 'w-4 h-4')
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="m-0 px-3 py-3 text-sm text-[color:var(--nx-muted)]">Noch keine Zuweisungen.</p>
            @endif

            <div class="flex flex-wrap items-end gap-2 border-t border-[color:var(--nx-line)] p-3">
                <div class="w-48">
                    <x-nx-input-select name="roleAssignForm.person_entity_id" label="Person" size="sm" wire:model="roleAssignForm.person_entity_id"
                        :options="$this->groupedPersonOptions" nullable nullLabel="Person wählen" />
                </div>
                <div class="w-56">
                    <x-nx-input-select name="roleAssignForm.context_entity_id" label="Kontext" size="sm" wire:model="roleAssignForm.context_entity_id"
                        :options="$this->groupedEntityOptions" nullable nullLabel="Optional" />
                </div>
                <div class="w-20">
                    <x-nx-input-number name="roleAssignForm.percentage" label="%" size="sm" wire:model="roleAssignForm.percentage" min="0" max="100" />
                </div>
                <div class="w-36">
                    <x-nx-input-date name="roleAssignForm.valid_from" label="Von" size="sm" wire:model="roleAssignForm.valid_from" />
                </div>
                <div class="w-36">
                    <x-nx-input-date name="roleAssignForm.valid_to" label="Bis" size="sm" wire:model="roleAssignForm.valid_to" />
                </div>
                <div class="min-w-[8rem] flex-1">
                    <x-nx-input-text name="roleAssignForm.note" label="Notiz" size="sm" wire:model="roleAssignForm.note" />
                </div>
                <x-nx-button variant="primary" size="sm" wire:click="storeRoleAssignment">
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    <span>Zuweisen</span>
                </x-nx-button>
            </div>
        </div>
    @endif
</div>
