<div>
    {{-- Modul Header --}}
    <x-sidebar-module-header module-name="Organization" />

    {{-- Perspective Switcher --}}
    <div x-show="!collapsed">
        @livewire('organization.perspective-switcher')
    </div>

    @foreach($sections as $section)
        @php($muted = $section['muted'] ?? false)
        <div class="mt-2 @if($muted) mt-3 pt-2 border-t border-[color:var(--ui-border)]/50 @endif">
            <h4 x-show="!collapsed" class="flex items-baseline gap-1.5 px-3 pt-2 pb-1 text-[10px] tracking-wider font-semibold uppercase @if($muted) text-[color:var(--ui-muted)]/70 @else text-[color:var(--ui-muted)] @endif">
                <span>{{ $section['label'] }}</span>
                @if(!empty($section['note']))
                    <span class="normal-case tracking-normal font-normal text-[9px] text-[color:var(--ui-muted)]/70">{{ $section['note'] }}</span>
                @endif
            </h4>

            @foreach($section['items'] as $item)
                @php($matchExpr = "new RegExp('" . addslashes($item['match']) . "').test(window.location.pathname)")
                <a href="{{ route($item['route']) }}"
                   class="relative flex items-center px-3 py-1.5 my-px rounded-md text-sm font-medium transition @if($muted) opacity-90 @endif"
                   :class="[
                       ({{ $matchExpr }})
                           ? 'bg-[color:var(--ui-primary)] text-[color:var(--ui-on-primary)] shadow-sm'
                           : 'text-[color:var(--ui-secondary)] hover:bg-[color:var(--ui-primary-5)] hover:text-[color:var(--ui-primary)]',
                       collapsed ? 'justify-center' : 'gap-2.5'
                   ]"
                   wire:navigate>
                    @svg('heroicon-o-' . (app('safe-svg')->resolve($item['icon'] ?? null, 'heroicon-o-') ?? 'cube'), 'w-5 h-5 flex-shrink-0')
                    <span x-show="!collapsed" class="truncate">{{ $item['label'] }}</span>
                    @if(!empty($item['migrates']))
                        <span x-show="!collapsed"
                              title="wandert ab nach: {{ $item['migrates'] }}"
                              class="ml-auto shrink-0 rounded px-1 py-0.5 text-[8px] font-semibold uppercase tracking-wide text-[color:var(--ui-muted)] border border-[color:var(--ui-border)]/60">
                            → {{ $item['migrates'] }}
                        </span>
                    @endif
                </a>
            @endforeach
        </div>
    @endforeach
</div>
