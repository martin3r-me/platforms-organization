{{-- Owner-Knoten im Interlink-Baum (rekursiv). Erwartet: $node aus Index::itemTree(). --}}
@php
    $countAll = count($node['items']);
    $stack = $node['children'];
    while (! empty($stack)) {
        $child = array_shift($stack);
        $countAll += count($child['items']);
        $stack = array_merge($stack, $child['children']);
    }
    $indent = 16 + $node['depth'] * 20;
@endphp

<div class="flex items-center gap-2 py-1.5 pr-4 {{ $node['depth'] === 0 ? 'mt-1 border-t border-[color:var(--nx-line)] first:mt-0 first:border-0' : '' }}" style="padding-left: {{ $indent }}px">
    @if ($node['type'] === 'group')
        @svg('heroicon-o-inbox', 'w-4 h-4 shrink-0 text-[color:var(--nx-faint)]')
    @elseif ($node['depth'] === 0)
        @svg('heroicon-o-building-office-2', 'w-4 h-4 shrink-0 text-[color:var(--nx-faint)]')
    @else
        @svg('heroicon-o-folder', 'w-4 h-4 shrink-0 text-[color:var(--nx-faint)]')
    @endif
    <span class="text-sm font-semibold text-[color:var(--nx-text)]">{{ $node['label'] }}</span>
    @if ($node['entity_type'])
        <span class="text-xs text-[color:var(--nx-faint)]">{{ $node['entity_type'] }}</span>
    @endif
    <span class="ml-auto text-xs tabular-nums text-[color:var(--nx-faint)]">{{ $countAll }}</span>
</div>

@foreach ($node['items'] as $item)
    @include('organization::livewire.interlink.partials.tree-item', ['item' => $item, 'depth' => $node['depth']])
@endforeach

@foreach ($node['children'] as $childNode)
    @include('organization::livewire.interlink.partials.tree-node', ['node' => $childNode])
@endforeach
