<div style="padding-left: {{ ($depth + 1) * 24 }}px;">
    <div class="flex items-center gap-3 py-2 px-3 hover:bg-[var(--ui-muted-5)] rounded transition-colors group">
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
                @svg('heroicon-o-arrows-right-left', 'w-3.5 h-3.5 text-[var(--ui-muted)] flex-shrink-0')
                <a href="{{ route('organization.interlinks.show', $item) }}" class="text-sm font-medium text-[var(--ui-primary)] hover:underline truncate" wire:navigate>
                    {{ $item->name }}
                </a>
                @if($item->category)
                    <x-ui-badge variant="secondary" size="sm">{{ $item->category->name }}</x-ui-badge>
                @endif
                @if($item->type)
                    <x-ui-badge variant="secondary" size="sm">{{ $item->type->name }}</x-ui-badge>
                @endif
                @if($item->is_bidirectional)
                    @svg('heroicon-o-arrows-right-left', 'w-3.5 h-3.5 text-[var(--ui-primary)] flex-shrink-0')
                @endif
                @if($item->is_active)
                    <x-ui-badge variant="success" size="sm">Aktiv</x-ui-badge>
                @else
                    <x-ui-badge variant="danger" size="sm">Inaktiv</x-ui-badge>
                @endif
            </div>
            @if($item->description)
                <div class="text-xs text-[var(--ui-muted)] ml-5.5 truncate">{{ \Illuminate\Support\Str::limit($item->description, 80) }}</div>
            @endif
        </div>

        <div class="flex gap-1 flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
            <a href="{{ route('organization.interlinks.show', $item) }}">
                <x-ui-button size="xs" variant="secondary-outline">
                    @svg('heroicon-o-pencil-square', 'w-3.5 h-3.5')
                </x-ui-button>
            </a>
            <x-ui-confirm-button size="xs" variant="danger-outline" wire:click="deleteInterlink({{ $item->id }})" confirm-text="Interlink wirklich löschen?">
                @svg('heroicon-o-trash', 'w-3.5 h-3.5')
            </x-ui-confirm-button>
        </div>
    </div>
</div>
