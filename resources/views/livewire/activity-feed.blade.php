<div class="space-y-1">
    @forelse($this->feedItems as $item)
        <div class="px-4 py-3 hover:bg-[var(--ui-muted-5)] transition-colors rounded-lg">
            <div class="flex items-center gap-2 text-xs">
                <span class="font-semibold text-[var(--ui-secondary)]">{{ $item['user_name'] }}</span>
                <span class="text-[var(--ui-muted)]">&middot;</span>
                <span class="text-[var(--ui-muted)]">{{ intdiv($item['minutes'], 60) }}:{{ str_pad($item['minutes'] % 60, 2, '0', STR_PAD_LEFT) }}h</span>
            </div>

            @if($item['context_name'] || $item['entity_name'])
                <div class="text-xs text-[var(--ui-muted)] mt-1 truncate">
                    @if($item['type_label'])
                        <span>{{ $item['type_label'] }}:</span>
                    @endif
                    @if($item['context_name'])
                        <span class="font-medium text-[var(--ui-secondary)]">{{ $item['context_name'] }}</span>
                    @endif
                    @if($item['entity_name'])
                        <span>&rarr; {{ $item['entity_name'] }}</span>
                    @endif
                </div>
            @endif

            @if($item['note'])
                <div class="text-xs text-[var(--ui-muted)] mt-1 truncate italic">"{{ $item['note'] }}"</div>
            @endif

            <div class="text-[10px] text-[var(--ui-muted)] mt-1">
                {{ $item['created_at']->diffForHumans() }}
                @if($item['work_date'])
                    &middot; {{ $item['work_date']->format('d.m.Y') }}
                @endif
            </div>
        </div>
    @empty
        <div class="px-4 py-6 text-sm text-[var(--ui-muted)] text-center">Keine Aktivitäten verfügbar</div>
    @endforelse
</div>
