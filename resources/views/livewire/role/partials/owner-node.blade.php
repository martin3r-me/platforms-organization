{{-- Owner-Einheit als Gruppenzeile, darunter ihre Rollen, dann rekursiv die Kinder. --}}
@php
    $countAll = count($node['items']);
    $stack = $node['children'];
    while (! empty($stack)) {
        $child = array_shift($stack);
        $countAll += count($child['items']);
        $stack = array_merge($stack, $child['children']);
    }
@endphp
<div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] py-2 pr-4 {{ $node['depth'] === 0 ? 'bg-[color:var(--nx-bg)]' : '' }}" style="padding-left: {{ 1 + $node['depth'] * 1.25 }}rem">
    @svg($node['depth'] === 0 ? 'heroicon-o-building-office-2' : 'heroicon-o-folder', 'w-4 h-4 shrink-0 text-[color:var(--nx-faint)]')
    <span class="text-sm font-semibold text-[color:var(--nx-text)]">{{ $node['label'] }}</span>
    @if ($node['entity_type'])
        <span class="text-xs text-[color:var(--nx-faint)]">{{ $node['entity_type'] }}</span>
    @endif
    <span class="ml-auto text-xs tabular-nums text-[color:var(--nx-faint)]">{{ $countAll }}</span>
</div>

@foreach ($node['items'] as $item)
    @include('organization::livewire.role.partials.tree-item', ['item' => $item, 'depth' => $node['depth']])
@endforeach

@foreach ($node['children'] as $childNode)
    @include('organization::livewire.role.partials.owner-node', ['node' => $childNode])
@endforeach
