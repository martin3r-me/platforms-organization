<div class="space-y-6">
    {{-- Add Entity Form --}}
    <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
        <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Entity hinzufuegen</h3>
        <div class="flex items-end gap-4">
            <div class="flex-1">
                <label class="block text-xs text-[var(--ui-muted)] mb-1">Entity</label>
                <select wire:model="addEntityId" class="w-full rounded-md border-[var(--ui-border)] text-sm">
                    <option value="">-- Entity waehlen --</option>
                    @foreach($this->availableEntities as $entity)
                        <option value="{{ $entity->id }}">{{ $entity->name }} ({{ $entity->type->name ?? '' }})</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1">
                <label class="block text-xs text-[var(--ui-muted)] mb-1">Parent (optional)</label>
                <select wire:model="addParentEntityId" class="w-full rounded-md border-[var(--ui-border)] text-sm">
                    <option value="">-- Root (kein Parent) --</option>
                    @foreach($this->entitiesInPerspective as $entity)
                        <option value="{{ $entity->id }}">{{ $entity->name }}</option>
                    @endforeach
                </select>
            </div>
            <x-ui-button variant="primary" size="sm" wire:click="addEntity">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Hinzufuegen</span>
            </x-ui-button>
        </div>
    </div>

    {{-- Hierarchy Table --}}
    <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
        <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">
            Hierarchie ({{ $this->hierarchyEntries->count() }} Entities)
        </h3>

        @if($this->hierarchyEntries->isEmpty())
            <p class="text-sm text-[var(--ui-muted)]">Noch keine Entities in dieser Perspektive.</p>
        @else
            <div class="divide-y divide-[var(--ui-border)]">
                @foreach($this->hierarchyEntries as $entry)
                    <div class="py-3 flex items-center justify-between gap-4" wire:key="h-{{ $entry->id }}">
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-[var(--ui-secondary)] truncate">
                                {{ $entry->entity->name ?? '—' }}
                            </div>
                            <div class="text-xs text-[var(--ui-muted)]">
                                {{ $entry->entity->type->name ?? '' }}
                            </div>
                        </div>
                        <div class="flex-1">
                            <select
                                wire:change="updateParent({{ $entry->id }}, $event.target.value)"
                                class="w-full rounded-md border-[var(--ui-border)] text-sm"
                            >
                                <option value="" @if(!$entry->parent_entity_id) selected @endif>-- Root --</option>
                                @foreach($this->entitiesInPerspective as $option)
                                    @if($option->id !== $entry->entity_id)
                                        <option value="{{ $option->id }}" @if($entry->parent_entity_id == $option->id) selected @endif>
                                            {{ $option->name }}
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <button
                            wire:click="removeEntity({{ $entry->id }})"
                            wire:confirm="Entity aus Perspektive entfernen?"
                            class="text-[var(--ui-muted)] hover:text-red-500 transition"
                        >
                            @svg('heroicon-o-trash', 'w-4 h-4')
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
