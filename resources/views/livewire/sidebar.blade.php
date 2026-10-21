<div>
    <div x-show="!collapsed" class="p-3 text-sm italic text-[color:var(--nx-muted)] uppercase border-b border-[color:var(--nx-line)]">
        Organisation
    </div>

    {{-- Perspektive (aktive Carrier-Entity) --}}
    <div x-show="!collapsed" class="border-b border-[color:var(--nx-line)]">
        @livewire('organization.perspective-switcher')
    </div>

    {{-- Ausgeklappt: gruppierte Navigation --}}
    <div x-show="!collapsed">
        @foreach($sections as $section)
            @php($muted = $section['muted'] ?? false)
            <x-ui-sidebar-list :label="$section['label'] . (! empty($section['note']) ? ' · ' . $section['note'] : '')">
                @foreach($section['items'] as $item)
                    <x-ui-sidebar-item :href="route($item['route'])" :active="$item['active']" :class="$muted ? 'opacity-80' : ''">
                        @svg('heroicon-o-' . (app('safe-svg')->resolve($item['icon'] ?? null, 'heroicon-o-') ?? 'cube'), 'w-4 h-4 shrink-0 text-[color:var(--nx-muted)]')
                        <span class="text-sm truncate">{{ $item['label'] }}</span>
                        @if(! empty($item['migrates']))
                            <x-slot name="trailing">
                                <span class="text-[10px] text-[color:var(--nx-faint)]" title="wandert ab nach: {{ $item['migrates'] }}">→ {{ $item['migrates'] }}</span>
                            </x-slot>
                        @endif
                    </x-ui-sidebar-item>
                @endforeach
            </x-ui-sidebar-list>
        @endforeach
    </div>

    {{-- Eingeklappt: nur Icons, Gruppen durch Hairlines getrennt --}}
    <div x-show="collapsed" x-cloak>
        @foreach($sections as $section)
            <div class="px-2 py-2 border-b border-[color:var(--nx-line)] flex flex-col gap-1">
                @foreach($section['items'] as $item)
                    <a href="{{ route($item['route']) }}" wire:navigate title="{{ $item['label'] }}"
                       @class([
                           'flex items-center justify-center p-2 rounded-md transition',
                           'bg-[color:var(--nx-active)] text-[color:var(--nx-text)]' => $item['active'],
                           'text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]' => ! $item['active'],
                       ])>
                        @svg('heroicon-o-' . (app('safe-svg')->resolve($item['icon'] ?? null, 'heroicon-o-') ?? 'cube'), 'w-5 h-5')
                    </a>
                @endforeach
            </div>
        @endforeach
    </div>
</div>
