{{-- Entity group header --}}
<div style="padding-left: {{ $node['depth'] * 24 }}px;">
    <div class="flex items-center gap-2 py-2 px-3 {{ $node['depth'] === 0 ? 'bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 rounded-lg mt-2' : '' }}">
        @if($node['depth'] === 0)
            @svg('heroicon-o-building-office-2', 'w-4 h-4 text-[var(--ui-muted)] flex-shrink-0')
        @else
            @svg('heroicon-o-folder', 'w-3.5 h-3.5 text-[var(--ui-muted)] flex-shrink-0')
        @endif
        <span class="text-sm font-semibold text-[var(--ui-secondary)]">{{ $node['label'] }}</span>
        @if($node['entity_type'])
            <span class="text-[10px] text-[var(--ui-muted)] bg-[var(--ui-muted-10)] px-1.5 py-0.5 rounded">{{ $node['entity_type'] }}</span>
        @endif
        @php
            $countAll = count($node['items']);
            $stack = $node['children'];
            while (!empty($stack)) {
                $child = array_shift($stack);
                $countAll += count($child['items']);
                $stack = array_merge($stack, $child['children']);
            }
        @endphp
        <span class="text-[10px] text-[var(--ui-muted)]">{{ $countAll }}</span>
    </div>
</div>

{{-- Direct items of this entity --}}
@foreach($node['items'] as $item)
    @include($itemPartial, ['item' => $item, 'depth' => $node['depth']])
@endforeach

{{-- Recursive children --}}
@foreach($node['children'] as $childNode)
    @include('organization::livewire.partials.entity-tree-node', ['node' => $childNode, 'itemPartial' => $itemPartial])
@endforeach
