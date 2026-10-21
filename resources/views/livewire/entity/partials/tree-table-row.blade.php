{{-- Eine Zeile der Einheiten-Liste, rekursiv für Kinder derselben Gruppe.
     Erwartet: $entity, $depth, $childrenByParent, $entityMovements, $vsmSystemMap, $signalCounts.
     Bewusst schlichtes Markup: die Liste hat schnell mehrere hundert Zeilen. --}}
@php
    $children = $childrenByParent[$entity->id] ?? collect();
    $rawIcon = $entity->type->icon ? str_replace('heroicons.', '', $entity->type->icon) : null;
    $iconName = $rawIcon ? (app('safe-svg')->resolve($rawIcon, 'heroicon-o-') ?? 'cube') : null;
    $vc = $entity->type->vsm_class;
    $relFrom = $entity->relations_from_count ?? 0;
    $relTo = $entity->relations_to_count ?? 0;
    $vsmValues = $vsmSystemMap[$entity->id] ?? [];
    $sc = $signalCounts[$entity->id] ?? null;
    $mv = $entityMovements[$entity->id] ?? null;

    $signalDot = ! $sc ? null : (
        (($sc['algedonic_count'] ?? 0) > 0 || ($sc['critical_count'] ?? 0) > 0) ? 'var(--nx-danger)'
        : ((($sc['warning_count'] ?? 0) > 0) ? 'var(--nx-warning)' : 'var(--nx-info)')
    );
    $moveDot = ! ($mv && $mv['delta_count'] > 0) ? null : (
        $mv['positive'] > $mv['negative'] ? 'var(--nx-success)'
        : ($mv['negative'] > $mv['positive'] ? 'var(--nx-danger)' : 'var(--nx-warning)')
    );
    [$vcLetter, $vcClass, $vcTitle] = match ($vc) {
        'carrier'  => ['C', 'bg-[rgba(47,158,68,.12)] text-[color:var(--nx-success)]', 'Carrier — kann eigene Perspektive sein'],
        'actor'    => ['A', 'bg-[color:var(--nx-accent-soft)] text-[color:var(--nx-muted)]', 'Actor — füllt VSM-Funktion aus'],
        'observed' => ['O', 'bg-[rgba(232,89,12,.12)] text-[color:var(--nx-warning)]', 'Observed — Umwelt, wird beobachtet'],
        default    => [null, '', ''],
    };
@endphp
<tr wire:key="ent-{{ $entity->id }}" class="border-b border-[color:var(--nx-line)] hover:bg-[color:var(--nx-hover)] {{ $entity->is_active ? '' : 'opacity-50' }}">
    <td class="px-4 py-1.5">
        <div class="flex min-w-0 items-center gap-2" style="padding-left: {{ $depth * 1.25 }}rem">
            @if ($depth > 0)
                <span class="h-px w-3 shrink-0 bg-[color:var(--nx-line-strong)]"></span>
            @endif
            @if ($iconName)
                @svg('heroicon-o-' . $iconName, 'w-4 h-4 shrink-0 text-[color:var(--nx-faint)]')
            @endif
            <a href="{{ route('organization.entities.show', $entity) }}" wire:navigate class="truncate font-medium text-[color:var(--nx-text)] hover:underline">{{ $entity->name }}</a>
            @if ($children->isNotEmpty())
                <span class="shrink-0 text-xs tabular-nums text-[color:var(--nx-faint)]">{{ $children->count() }}</span>
            @endif
            @if ($entity->code)
                <span class="shrink-0 font-mono text-xs text-[color:var(--nx-faint)]">{{ $entity->code }}</span>
            @endif
            @if (! $entity->is_active)
                <span class="shrink-0 text-xs text-[color:var(--nx-faint)]">inaktiv</span>
            @endif
        </div>
    </td>
    <td class="whitespace-nowrap px-3 py-1.5">
        <div class="flex items-center gap-1.5">
            <span class="text-[color:var(--nx-muted)]">{{ $entity->type->name }}</span>
            @if ($vcLetter)
                <span class="inline-flex h-4 w-4 items-center justify-center rounded-[4px] text-[10px] font-semibold {{ $vcClass }}" title="{{ $vcTitle }}">{{ $vcLetter }}</span>
            @endif
        </div>
    </td>
    <td class="whitespace-nowrap px-3 py-1.5 text-xs tabular-nums text-[color:var(--nx-muted)]">
        @if ($relFrom + $relTo > 0)
            @if ($relTo > 0)<span title="eingehend">← {{ $relTo }}</span>@endif
            @if ($relFrom > 0)<span class="ml-1" title="ausgehend">→ {{ $relFrom }}</span>@endif
        @else
            <span class="text-[color:var(--nx-faint)]">–</span>
        @endif
    </td>
    <td class="px-3 py-1.5">
        @if (count($vsmValues) > 0)
            <div class="flex flex-wrap gap-1">
                @foreach ($vsmValues as $val)
                    <span class="rounded-[4px] bg-[rgba(25,113,194,.12)] px-1.5 py-0.5 text-[10px] font-medium text-[color:var(--nx-info)]" title="{{ $val['name'] }}">{{ $val['code'] }}</span>
                @endforeach
            </div>
        @else
            <span class="text-[color:var(--nx-faint)]">–</span>
        @endif
    </td>
    <td class="whitespace-nowrap px-3 py-1.5">
        @if ($sc)
            <a href="{{ route('organization.entities.show', $entity) }}?tab=signals" wire:navigate class="inline-flex items-center gap-1.5 text-xs tabular-nums text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]">
                <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $signalDot }}"></span>
                {{ $sc['total'] }}
            </a>
        @else
            <span class="text-[color:var(--nx-faint)]">–</span>
        @endif
    </td>
    <td class="px-4 py-1.5">
        @if ($moveDot)
            <div class="flex items-center gap-1.5">
                <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $moveDot }}"></span>
                <span class="max-w-[8rem] truncate text-xs text-[color:var(--nx-muted)]" title="{{ $mv['top_delta'] }}">{{ $mv['top_delta'] }}</span>
            </div>
        @else
            <span class="text-[color:var(--nx-faint)]">–</span>
        @endif
    </td>
</tr>

@foreach ($children as $childEntity)
    @include('organization::livewire.entity.partials.tree-table-row', [
        'entity' => $childEntity,
        'depth' => $depth + 1,
        'childrenByParent' => $childrenByParent,
        'entityMovements' => $entityMovements,
        'vsmSystemMap' => $vsmSystemMap,
        'signalCounts' => $signalCounts,
    ])
@endforeach
