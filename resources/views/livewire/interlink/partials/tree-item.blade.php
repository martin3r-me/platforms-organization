{{-- Ein Interlink im Baum. Erwartet: $item, $depth (Tiefe des Owner-Knotens). --}}
<div wire:key="il-{{ $item->id }}" class="group flex items-center gap-2 py-1.5 pr-3 transition-colors hover:bg-[color:var(--nx-hover)]" style="padding-left: {{ 16 + ($depth + 1) * 20 }}px">
    @svg('heroicon-o-arrows-right-left', 'w-3.5 h-3.5 shrink-0 text-[color:var(--nx-faint)]')
    <div class="min-w-0 flex-1">
        <div class="flex min-w-0 items-center gap-2">
            <a href="{{ route('organization.interlinks.show', $item) }}" wire:navigate class="truncate text-sm font-medium text-[color:var(--nx-text)] hover:underline">{{ $item->name }}</a>
            @if ($item->category)
                <span class="shrink-0 text-xs text-[color:var(--nx-muted)]">{{ $item->category->name }}</span>
            @endif
            @if ($item->type)
                <span class="shrink-0 text-xs text-[color:var(--nx-faint)]">· {{ $item->type->name }}</span>
            @endif
            @if ($item->is_bidirectional)
                <span title="Bidirektional">@svg('heroicon-o-arrows-right-left', 'w-3.5 h-3.5 shrink-0 text-[color:var(--nx-accent)]')</span>
            @endif
        </div>
        @if ($item->description)
            <div class="truncate text-xs text-[color:var(--nx-faint)]">{{ $item->description }}</div>
        @endif
    </div>
    @unless ($item->is_active)
        <x-nx-badge dot>Inaktiv</x-nx-badge>
    @endunless
    <button type="button" wire:click="deleteInterlink({{ $item->id }})" wire:confirm="Interlink „{{ $item->name }}“ wirklich löschen?"
        title="Löschen"
        class="shrink-0 rounded-[6px] p-1 text-[color:var(--nx-faint)] opacity-0 transition hover:bg-[color:var(--nx-hover)] hover:text-[color:var(--nx-danger)] group-hover:opacity-100">
        @svg('heroicon-o-trash', 'w-4 h-4')
    </button>
</div>
