@php
    $dimensions = array_keys($radar);
    $count = count($dimensions);
    $cx = 150;
    $cy = 150;
    $maxR = 110;
    $gridLevels = [25, 50, 75, 100];
    $angleStep = 360 / $count;

    // Pre-compute axis endpoints and label positions
    $axes = [];
    foreach ($dimensions as $i => $dimKey) {
        $angleDeg = -90 + ($i * $angleStep);
        $angleRad = deg2rad($angleDeg);
        $axes[$dimKey] = [
            'x' => $cx + $maxR * cos($angleRad),
            'y' => $cy + $maxR * sin($angleRad),
            'lx' => $cx + ($maxR + 18) * cos($angleRad),
            'ly' => $cy + ($maxR + 18) * sin($angleRad),
            'angle' => $angleDeg,
        ];
    }

    // Build polygon points
    $polygonPoints = [];
    $hasAnyData = false;
    foreach ($dimensions as $i => $dimKey) {
        $score = $radar[$dimKey]['score'] ?? 0;
        if ($score > 0) $hasAnyData = true;
        $r = ($score / 100) * $maxR;
        $angleDeg = -90 + ($i * $angleStep);
        $angleRad = deg2rad($angleDeg);
        $polygonPoints[] = round($cx + $r * cos($angleRad), 2) . ',' . round($cy + $r * sin($angleRad), 2);
    }
    $polygonStr = implode(' ', $polygonPoints);
@endphp

<div x-data="{ activeTooltip: null }" class="relative">
    <svg viewBox="0 0 300 300" class="w-full max-w-[320px] mx-auto" xmlns="http://www.w3.org/2000/svg">
        {{-- Grid circles --}}
        @foreach($gridLevels as $level)
            <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ ($level / 100) * $maxR }}"
                fill="none" stroke="var(--nx-line-strong)" stroke-width="0.5" opacity="0.4" />
        @endforeach

        {{-- Axis lines --}}
        @foreach($dimensions as $i => $dimKey)
            @php $ax = $axes[$dimKey]; $dim = $radar[$dimKey]; @endphp
            <line x1="{{ $cx }}" y1="{{ $cy }}" x2="{{ $ax['x'] }}" y2="{{ $ax['y'] }}"
                stroke="var(--nx-line-strong)"
                stroke-width="0.8"
                @if(!$dim['has_data']) stroke-dasharray="4,3" opacity="0.5" @endif
            />
        @endforeach

        {{-- Filled polygon --}}
        @if($hasAnyData)
            <polygon points="{{ $polygonStr }}"
                fill="var(--nx-accent)" fill-opacity="0.12"
                stroke="var(--nx-accent)" stroke-width="1.5" stroke-linejoin="round" />

            {{-- Score dots --}}
            @foreach($dimensions as $i => $dimKey)
                @php
                    $dim = $radar[$dimKey];
                    $score = $dim['score'];
                    $r = ($score / 100) * $maxR;
                    $angleDeg = -90 + ($i * $angleStep);
                    $angleRad = deg2rad($angleDeg);
                    $dotX = round($cx + $r * cos($angleRad), 2);
                    $dotY = round($cy + $r * sin($angleRad), 2);
                @endphp
                @if($dim['has_data'])
                    <circle cx="{{ $dotX }}" cy="{{ $dotY }}" r="3"
                        fill="var(--nx-accent)" stroke="var(--nx-surface)" stroke-width="1.5" />
                @endif
            @endforeach
        @endif

        {{-- Axis labels --}}
        @foreach($dimensions as $i => $dimKey)
            @php
                $ax = $axes[$dimKey];
                $dim = $radar[$dimKey];
                $anchor = 'middle';
                if ($ax['angle'] > -80 && $ax['angle'] < 80) $anchor = 'start';
                elseif ($ax['angle'] > 100 || $ax['angle'] < -100) $anchor = 'end';
            @endphp
            <text x="{{ $ax['lx'] }}" y="{{ $ax['ly'] }}"
                text-anchor="{{ $anchor }}"
                dominant-baseline="central"
                class="fill-current {{ $dim['has_data'] ? 'text-[color:var(--nx-text)]' : 'text-[color:var(--nx-faint)]' }}"
                style="font-size: 9px; font-weight: {{ $dim['has_data'] ? '600' : '400' }};"
            >{{ $dim['label'] }}</text>
        @endforeach

        {{-- Invisible hit areas for tooltips --}}
        @foreach($dimensions as $i => $dimKey)
            @php $ax = $axes[$dimKey]; @endphp
            <circle cx="{{ $ax['lx'] }}" cy="{{ $ax['ly'] }}" r="20"
                fill="transparent" class="cursor-pointer"
                @mouseenter="activeTooltip = '{{ $dimKey }}'"
                @mouseleave="activeTooltip = null" />
        @endforeach
    </svg>

    {{-- Tooltips (rendered outside SVG for better styling) --}}
    @foreach($dimensions as $dimKey)
        @php $dim = $radar[$dimKey]; @endphp
        <div x-show="activeTooltip === '{{ $dimKey }}'" x-cloak x-transition.opacity
            class="absolute z-20 px-3 py-2 rounded-[6px] border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] text-[color:var(--nx-text)] text-[11px] shadow-sm pointer-events-none whitespace-nowrap"
            style="left: 50%; top: 50%; transform: translate(-50%, -50%);">
            <div class="font-semibold mb-1">{{ $dim['label'] }} &mdash; Score {{ round($dim['score']) }}</div>
            @if($dim['delta'] != 0)
                <div class="text-[10px] {{ $dim['delta'] > 0 ? 'text-[color:var(--nx-success)]' : 'text-[color:var(--nx-danger)]' }}">
                    7d: {{ $dim['delta'] > 0 ? '+' : '' }}{{ round($dim['delta'], 1) }}
                </div>
            @endif
            @if(!empty($dim['metrics']))
                <div class="mt-1 border-t border-[color:var(--nx-line)] pt-1 space-y-0.5">
                    @foreach($dim['metrics'] as $m)
                        <div class="text-[10px] text-[color:var(--nx-muted)]">{{ $m['label'] }}: {{ $m['formatted'] }}</div>
                    @endforeach
                </div>
            @endif
            @if(!$dim['has_data'])
                <div class="text-[10px] text-[color:var(--nx-faint)] italic">Keine Daten</div>
            @endif
        </div>
    @endforeach
</div>
