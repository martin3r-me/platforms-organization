@php
    $children = $childrenByParent[$entity->id] ?? collect();
    $hasChildren = $children->isNotEmpty();
    $indent = $depth * 1.25;
@endphp
<x-ui-table-row compact="true" class="{{ !$entity->is_active ? 'opacity-50' : '' }} {{ $depth === 0 ? '' : 'bg-[var(--ui-muted-5)]/20' }}">
    {{-- Name (with indentation + expand indicator) --}}
    <x-ui-table-cell compact="true">
        <div class="flex items-center" style="padding-left: {{ $indent }}rem;">
            {{-- Tree line indicator --}}
            @if($depth > 0)
                <span class="w-4 h-px bg-[var(--ui-border)]/40 mr-1 flex-shrink-0"></span>
            @endif

            {{-- Type Icon --}}
            @if($entity->type->icon)
                @php
                    $iconName = str_replace('heroicons.', '', $entity->type->icon);
                    $iconName = app('safe-svg')->resolve($iconName, 'heroicon-o-') ?? 'cube';
                @endphp
                @svg('heroicon-o-' . $iconName, 'w-4 h-4 text-[var(--ui-muted)] mr-2 flex-shrink-0')
            @endif

            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <a href="{{ route('organization.entities.show', $entity) }}" class="text-sm font-medium text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] hover:underline truncate">{{ $entity->name }}</a>
                    @if($hasChildren)
                        <span class="inline-flex items-center justify-center min-w-[1.1rem] h-4 px-1 text-[10px] font-bold text-[var(--ui-muted)] bg-[var(--ui-muted-5)] rounded-full flex-shrink-0">{{ $children->count() }}</span>
                    @endif
                </div>
                @if($entity->code)
                    <div class="text-[10px] text-[var(--ui-muted)] font-mono mt-0.5">{{ $entity->code }}</div>
                @endif
            </div>
        </div>
    </x-ui-table-cell>

    {{-- Typ --}}
    <x-ui-table-cell compact="true">
        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-[var(--ui-muted-5)] text-[var(--ui-muted)]">{{ $entity->type->name }}</span>
    </x-ui-table-cell>

    {{-- Relationen --}}
    <x-ui-table-cell compact="true">
        @php
            $relationsFromCount = $entity->relationsFrom->count();
            $relationsToCount = $entity->relationsTo->count();
            $totalRelations = $relationsFromCount + $relationsToCount;
        @endphp
        @if($totalRelations > 0)
            <div class="flex items-center gap-1">
                @if($relationsToCount > 0)
                    <span class="text-[10px] text-[var(--ui-muted)]">← {{ $relationsToCount }}</span>
                @endif
                @if($relationsFromCount > 0)
                    <span class="text-[10px] text-[var(--ui-muted)]">→ {{ $relationsFromCount }}</span>
                @endif
            </div>
        @else
            <span class="text-xs text-[var(--ui-muted)]">&ndash;</span>
        @endif
    </x-ui-table-cell>

    {{-- VSM --}}
    <x-ui-table-cell compact="true">
        @php $vsmValues = $vsmSystemMap[$entity->id] ?? []; @endphp
        @if(count($vsmValues) > 0)
            <div class="flex flex-wrap gap-1">
                @foreach($vsmValues as $val)
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-600/10" title="{{ $val['name'] }}">{{ $val['code'] }}</span>
                @endforeach
            </div>
        @else
            <span class="text-xs text-[var(--ui-muted)]">&ndash;</span>
        @endif
    </x-ui-table-cell>

    {{-- Status --}}
    <x-ui-table-cell compact="true">
        @if($entity->is_active)
            <span class="w-2 h-2 rounded-full bg-green-500 inline-block" title="Aktiv"></span>
        @else
            <span class="w-2 h-2 rounded-full bg-gray-300 inline-block" title="Inaktiv"></span>
        @endif
    </x-ui-table-cell>

    {{-- Signale --}}
    <x-ui-table-cell compact="true">
        @php $sc = $signalCounts[$entity->id] ?? null; @endphp
        @if($sc)
            <a href="{{ route('organization.entities.show', $entity) }}?tab=signals" class="flex items-center gap-1">
                @if(($sc['algedonic_count'] ?? 0) > 0 || ($sc['critical_count'] ?? 0) > 0)
                    <span class="w-2 h-2 rounded-full bg-red-500 flex-shrink-0"></span>
                @elseif(($sc['warning_count'] ?? 0) > 0)
                    <span class="w-2 h-2 rounded-full bg-amber-400 flex-shrink-0"></span>
                @else
                    <span class="w-2 h-2 rounded-full bg-blue-400 flex-shrink-0"></span>
                @endif
                <span class="text-[10px] text-[var(--ui-muted)]">{{ $sc['total'] }}</span>
            </a>
        @else
            <span class="text-xs text-[var(--ui-muted)]">&ndash;</span>
        @endif
    </x-ui-table-cell>

    {{-- Bewegung --}}
    <x-ui-table-cell compact="true">
        @php $mv = ($entityMovements[$entity->id] ?? null); @endphp
        @if($mv && $mv['delta_count'] > 0)
            <div class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full flex-shrink-0
                    {{ $mv['positive'] > $mv['negative'] ? 'bg-green-500' : '' }}
                    {{ $mv['negative'] > $mv['positive'] ? 'bg-red-500' : '' }}
                    {{ $mv['positive'] === $mv['negative'] && $mv['delta_count'] > 0 ? 'bg-amber-400' : '' }}
                "></span>
                <span class="text-[10px] text-[var(--ui-muted)] truncate max-w-[80px]" title="{{ $mv['top_delta'] }}">
                    {{ $mv['top_delta'] }}
                </span>
            </div>
        @else
            <span class="w-2 h-2 rounded-full bg-gray-200 inline-block" title="Keine Bewegung"></span>
        @endif
    </x-ui-table-cell>
</x-ui-table-row>

{{-- Recursively render children --}}
@if($hasChildren)
    @foreach($children->sortBy('name') as $childEntity)
        @include('organization::livewire.entity.partials.tree-table-row', [
            'entity' => $childEntity,
            'depth' => $depth + 1,
            'childrenByParent' => $childrenByParent,
            'entityMovements' => $entityMovements,
            'vsmSystemMap' => $vsmSystemMap,
            'signalCounts' => $signalCounts,
        ])
    @endforeach
@endif
