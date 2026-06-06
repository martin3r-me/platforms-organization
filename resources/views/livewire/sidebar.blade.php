<div>
    {{-- Modul Header --}}
    <x-sidebar-module-header module-name="Organization" />

    {{-- Perspective Switcher --}}
    <div x-show="!collapsed">
        @livewire('organization.perspective-switcher')
    </div>

    @foreach($sections as $section)
        <div class="mt-2">
            <h4 x-show="!collapsed" class="px-3 pt-2 pb-1 text-[10px] tracking-wider font-semibold text-[color:var(--ui-muted)] uppercase">
                {{ $section['label'] }}
            </h4>

            @foreach($section['items'] as $item)
                @php($matchExpr = "new RegExp('" . addslashes($item['match']) . "').test(window.location.pathname)")
                <a href="{{ route($item['route']) }}"
                   class="relative flex items-center px-3 py-1.5 my-px rounded-md text-sm font-medium transition"
                   :class="[
                       ({{ $matchExpr }})
                           ? 'bg-[color:var(--ui-primary)] text-[color:var(--ui-on-primary)] shadow-sm'
                           : 'text-[color:var(--ui-secondary)] hover:bg-[color:var(--ui-primary-5)] hover:text-[color:var(--ui-primary)]',
                       collapsed ? 'justify-center' : 'gap-2.5'
                   ]"
                   wire:navigate>
                    @svg('heroicon-o-' . $item['icon'], 'w-5 h-5 flex-shrink-0')
                    <span x-show="!collapsed" class="truncate">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </div>
    @endforeach
</div>
