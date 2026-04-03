{{-- Link item (leaf node) - optionally expandable via display rules --}}
{{-- Variables: $link (array), $group (array with type, icon, label) --}}
@php
    $groupIcon = $group['icon'] ?? 'link';
    $linkType = $group['type'] ?? '';
    $isDone = $link['done'] ?? $link['is_done'] ?? false;

    // Generic expandable_children detection from display rules
    $expandRule = null;
    $rules = resolve(\Platform\Organization\Services\EntityLinkRegistry::class)->allMetadataDisplayRules()[$linkType] ?? [];
    foreach ($rules as $r) {
        if (($r['format'] ?? '') === 'expandable_children') {
            $expandRule = $r;
            break;
        }
    }

    $childrenField = $expandRule['field'] ?? null;
    $hasChildren = $childrenField && !empty($link[$childrenField] ?? []);
    $childType = $expandRule['child_type'] ?? null;
    $nameField = $expandRule['name_field'] ?? 'name';
    $doneField = $expandRule['done_field'] ?? 'is_done';
@endphp

<div class="ml-6 border-l-2 border-[var(--ui-border)]/20"
    @if($hasChildren) x-data="{ childOpen: false, init() { this.$watch('$store.tree.allExpanded', v => this.childOpen = v); } }" @endif
    @if($isDone) x-show="$store.tree.showDone" x-transition @endif
>
    <div class="group rounded-lg transition-colors hover:bg-[var(--ui-muted-5)] py-2 px-3">
        <div class="flex items-center gap-2 {{ $hasChildren ? 'cursor-pointer' : '' }}"
            @if($hasChildren) @click="childOpen = !childOpen" @endif
        >
            {{-- Chevron for expandable items --}}
            <div class="w-5 h-5 flex items-center justify-center flex-shrink-0">
                @if($hasChildren)
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                        class="w-4 h-4 text-[var(--ui-muted)] transition-transform duration-200"
                        :class="{ 'rotate-90': childOpen }">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    </svg>
                @endif
            </div>
            @svg('heroicon-o-' . $groupIcon, 'w-4 h-4 text-[var(--ui-muted)] flex-shrink-0')
            @if($link['url'])
                <a href="{{ $link['url'] }}" class="text-sm font-medium text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] hover:underline truncate {{ $isDone ? 'line-through opacity-60' : '' }}" @click.stop>
                    {{ $link['name'] }}
                </a>
            @else
                <span class="text-sm font-medium text-[var(--ui-secondary)] truncate {{ $isDone ? 'line-through opacity-60' : '' }}">{{ $link['name'] }}</span>
            @endif
            @include('organization::livewire.entity.partials.link-meta', ['link' => $link, 'linkType' => $linkType])
            @if($link['status'])
                <x-ui-badge variant="secondary" size="xs">{{ $link['status'] }}</x-ui-badge>
            @endif
            @if($link['url'])
                <div class="ml-auto flex-shrink-0">
                    @svg('heroicon-o-arrow-top-right-on-square', 'w-3.5 h-3.5 text-[var(--ui-muted)]')
                </div>
            @endif
        </div>
    </div>

    {{-- Generic expandable children --}}
    @if($hasChildren)
        @php
            $childIconName = resolve(\Platform\Organization\Services\EntityLinkRegistry::class)->allLinkTypeConfig()[$childType]['icon'] ?? 'link';
        @endphp
        <div x-show="childOpen" x-collapse x-cloak>
            @foreach($link[$childrenField] as $child)
                @php $childIsDone = $child[$doneField] ?? false; @endphp
                <div class="ml-6 border-l-2 border-[var(--ui-border)]/20"
                    @if($childIsDone) x-show="$store.tree.showDone" x-transition @endif
                >
                    <div class="group rounded-lg transition-colors hover:bg-[var(--ui-muted-5)] py-2 px-3">
                        <div class="flex items-center gap-2">
                            <div class="w-5 h-5 flex-shrink-0"></div>
                            @svg('heroicon-o-' . $childIconName, 'w-4 h-4 text-[var(--ui-muted)] flex-shrink-0')
                            <span class="text-sm font-medium text-[var(--ui-secondary)] truncate {{ $childIsDone ? 'line-through opacity-60' : '' }}">
                                {{ $child[$nameField] ?? '—' }}
                            </span>
                            @include('organization::livewire.entity.partials.link-meta', ['link' => $child, 'linkType' => $childType])
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
