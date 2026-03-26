{{-- Link item (leaf node) - optionally expandable for projects with tasks --}}
{{-- Variables: $link (array), $group (array with type, icon, label) --}}
@php
    $hasChildren = ($link['has_tasks'] ?? false) && !empty($link['task_items'] ?? []);
    $groupIcon = $group['icon'] ?? 'link';
    $linkType = $group['type'] ?? '';
@endphp

<div class="ml-6 border-l-2 border-[var(--ui-border)]/20"
    @if($hasChildren) x-data="{ taskOpen: false, init() { this.$watch('$store.tree.allExpanded', v => this.taskOpen = v); } }" @endif
>
    <div class="group rounded-lg transition-colors hover:bg-[var(--ui-muted-5)] py-2 px-3">
        <div class="flex items-center gap-2 {{ $hasChildren ? 'cursor-pointer' : '' }}"
            @if($hasChildren) @click="taskOpen = !taskOpen" @endif
        >
            {{-- Chevron for expandable projects --}}
            <div class="w-5 h-5 flex items-center justify-center flex-shrink-0">
                @if($hasChildren)
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                        class="w-4 h-4 text-[var(--ui-muted)] transition-transform duration-200"
                        :class="{ 'rotate-90': taskOpen }">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    </svg>
                @endif
            </div>
            @svg('heroicon-o-' . $groupIcon, 'w-4 h-4 text-[var(--ui-muted)] flex-shrink-0')
            @if($link['url'])
                <a href="{{ $link['url'] }}" class="text-sm font-medium text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] hover:underline truncate" @click.stop>
                    {{ $link['name'] }}
                </a>
            @else
                <span class="text-sm font-medium text-[var(--ui-secondary)] truncate">{{ $link['name'] }}</span>
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

    {{-- Task children (for projects) --}}
    @if($hasChildren)
        <div x-show="taskOpen" x-collapse x-cloak>
            @foreach($link['task_items'] as $task)
                <div class="ml-6 border-l-2 border-[var(--ui-border)]/20">
                    <div class="group rounded-lg transition-colors hover:bg-[var(--ui-muted-5)] py-2 px-3">
                        <div class="flex items-center gap-2">
                            <div class="w-5 h-5 flex-shrink-0"></div>
                            @svg('heroicon-o-clipboard-document-check', 'w-4 h-4 text-[var(--ui-muted)] flex-shrink-0')
                            <span class="text-sm font-medium text-[var(--ui-secondary)] truncate {{ ($task['is_done'] ?? false) ? 'line-through opacity-60' : '' }}">
                                {{ $task['name'] }}
                            </span>
                            @if($task['priority'] ?? null)
                                <span class="text-[10px] text-[var(--ui-muted)]">{{ $task['priority'] }}</span>
                            @endif
                            @if(($task['logged_minutes'] ?? 0) > 0)
                                <span class="text-[10px] text-[var(--ui-muted)]">
                                    {{ intdiv($task['logged_minutes'], 60) }}:{{ str_pad($task['logged_minutes'] % 60, 2, '0', STR_PAD_LEFT) }}h
                                </span>
                            @endif
                            @if($task['due_date'] ?? null)
                                <span class="text-[10px] text-[var(--ui-muted)]">{{ $task['due_date'] }}</span>
                            @endif
                            @if($task['is_done'] ?? false)
                                <span class="text-[10px] text-green-600">erledigt</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
