{{-- Tree Node - Server-rendered first level, recursive Alpine.js for deeper levels --}}
{{-- Variables: $node (array), $depth (int, default 0) --}}
@php $depth = $depth ?? 0; @endphp

@php
    $cTotalMin = $node['cascaded_time']['total_minutes'];
    $cBilledMin = $node['cascaded_time']['billed_minutes'];
    $cOpenMin = $cTotalMin - $cBilledMin;
    $cHours = intdiv($cTotalMin, 60);
    $cMins = $cTotalMin % 60;
    $cOpenH = intdiv(abs($cOpenMin), 60);
    $cOpenM = abs($cOpenMin) % 60;
    $hasCascadedData = $node['descendant_count'] > 0;
    $isExpandable = $node['has_children'] || !empty($node['own_links_grouped']);
@endphp

<div
    x-data="{
        expanded: false,
        loading: false,
        childData: [],
        hasChildren: {{ $node['has_children'] ? 'true' : 'false' }},
        isExpandable: {{ $isExpandable ? 'true' : 'false' }},
        init() {
            this.$watch('$store.tree.allExpanded', (val) => {
                if (val && this.isExpandable) {
                    if (this.hasChildren && this.childData.length === 0) {
                        const preloaded = Alpine.store('tree')?.preloadedNodes?.[{{ $node['id'] }}];
                        if (preloaded) this.childData = preloaded;
                    }
                    this.expanded = true;
                } else if (!val) {
                    this.expanded = false;
                    this.childData = [];
                }
            });
        },
        async toggle() {
            if (!this.isExpandable) return;
            if (this.expanded) { this.expanded = false; return; }
            if (this.hasChildren && this.childData.length === 0) {
                this.loading = true;
                try {
                    const preloaded = Alpine.store('tree')?.preloadedNodes?.[{{ $node['id'] }}];
                    this.childData = preloaded || await $wire.loadChildNodes({{ $node['id'] }});
                } finally {
                    this.loading = false;
                }
            }
            this.expanded = true;
        }
    }"
    class="ml-6 border-l-2 border-[var(--ui-border)]/20"
>
    {{-- Node Card (server-rendered) --}}
    <div class="group rounded-lg transition-colors hover:bg-[var(--ui-muted-5)] {{ !$node['is_active'] ? 'opacity-50' : '' }} py-2 px-3">
        <div class="flex items-center gap-2 {{ $isExpandable ? 'cursor-pointer' : '' }}"
            @if($isExpandable) @click="toggle()" @endif
        >
            <div class="w-5 h-5 flex items-center justify-center flex-shrink-0">
                @if($isExpandable)
                    <template x-if="loading">
                        <svg class="w-4 h-4 animate-spin text-[var(--ui-muted)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </template>
                    <template x-if="!loading">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                            class="w-4 h-4 text-[var(--ui-muted)] transition-transform duration-200"
                            :class="{ 'rotate-90': expanded }">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>
                    </template>
                @endif
            </div>

            @if($node['type_icon'])
                @svg('heroicon-o-' . $node['type_icon'], 'w-4 h-4 text-[var(--ui-muted)] flex-shrink-0')
            @endif

            <a href="{{ route('organization.entities.show', $node['id']) }}"
                class="text-sm font-semibold text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] hover:underline truncate"
                @click.stop>
                {{ $node['name'] }}
            </a>

            @if($node['code'])
                <span class="text-xs text-[var(--ui-muted)] font-mono flex-shrink-0">{{ $node['code'] }}</span>
            @endif

            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-[var(--ui-muted-5)] text-[var(--ui-muted)] flex-shrink-0">
                {{ $node['type_name'] }}
            </span>

            @if($cTotalMin > 0)
                <div class="flex items-center gap-2 ml-auto flex-shrink-0">
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-[var(--ui-secondary)]">
                        <span class="w-2 h-2 rounded-full flex-shrink-0 {{ $cOpenMin > 0 ? 'bg-amber-400' : 'bg-green-500' }}"></span>
                        {{ $cHours }}:{{ str_pad($cMins, 2, '0', STR_PAD_LEFT) }}h
                    </span>
                    @if($cOpenMin > 0)
                        <span class="text-[10px] text-amber-600 font-medium">
                            {{ $cOpenH }}:{{ str_pad($cOpenM, 2, '0', STR_PAD_LEFT) }}h offen
                        </span>
                    @endif
                </div>
            @endif
        </div>

        @if(!empty($node['cascaded_link_counts']) || $hasCascadedData)
            <div class="flex items-center gap-1.5 mt-1.5 ml-7 flex-wrap">
                @foreach($node['cascaded_link_counts'] as $type => $count)
                    @php $config = $this->linkTypeConfig[$type] ?? null; @endphp
                    @if($config && $count > 0)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] bg-[var(--ui-muted-5)] text-[var(--ui-muted)] border border-[var(--ui-border)]/20">
                            @svg('heroicon-o-' . $config['icon'], 'w-3 h-3')
                            {{ $config['label'] }}
                            <span class="font-semibold text-[var(--ui-secondary)]">{{ $count }}</span>
                        </span>
                    @endif
                @endforeach

                @if($hasCascadedData)
                    <span class="text-[10px] text-[var(--ui-muted)] ml-1">
                        inkl. {{ $node['descendant_count'] }} {{ $node['descendant_count'] === 1 ? 'Untereinheit' : 'Untereinheiten' }}
                    </span>
                @endif
            </div>
        @endif
    </div>

    {{-- Expanded content: server-rendered link groups + Alpine-rendered children --}}
    @if($isExpandable)
        <div x-show="expanded" x-collapse x-cloak>
            {{-- Server-rendered own link groups --}}
            @foreach($node['own_links_grouped'] as $group)
                <div x-data="{ groupOpen: false, init() { this.$watch('$store.tree.allExpanded', v => this.groupOpen = v); } }" class="ml-6 border-l-2 border-[var(--ui-border)]/20">
                    <div class="group rounded-lg transition-colors hover:bg-[var(--ui-muted-5)] py-2 px-3 cursor-pointer"
                        @click="groupOpen = !groupOpen">
                        <div class="flex items-center gap-2">
                            <div class="w-5 h-5 flex items-center justify-center flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                    class="w-4 h-4 text-[var(--ui-muted)] transition-transform duration-200"
                                    :class="{ 'rotate-90': groupOpen }">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                </svg>
                            </div>
                            @svg('heroicon-o-' . $group['icon'], 'w-4 h-4 text-[var(--ui-muted)] flex-shrink-0')
                            <span class="text-sm font-medium text-[var(--ui-secondary)]">{{ $group['label'] }}</span>
                            <span class="text-xs text-[var(--ui-muted)]">({{ count($group['items']) }})</span>
                            @if(($group['group_logged_minutes'] ?? 0) > 0)
                                <span class="text-xs text-[var(--ui-muted)] ml-auto flex-shrink-0">
                                    {{ intdiv($group['group_logged_minutes'], 60) }}:{{ str_pad($group['group_logged_minutes'] % 60, 2, '0', STR_PAD_LEFT) }}h
                                </span>
                            @endif
                        </div>
                    </div>
                    <div x-show="groupOpen" x-collapse x-cloak>
                        @foreach($group['items'] as $link)
                            @include('organization::livewire.entity.partials.link-item', ['link' => $link, 'group' => $group])
                        @endforeach
                    </div>
                </div>
            @endforeach

            {{-- Alpine-rendered children (recursive via x-html) --}}
            <template x-for="child in childData" :key="child.id">
                <div x-data="treeNode(child)" class="ml-6 border-l-2 border-[var(--ui-border)]/20">
                    <div x-html="renderNode(child)"></div>
                    <div x-show="nodeExpanded" x-collapse x-cloak>
                        <div x-html="renderLinkGroups(child)"></div>
                        <template x-for="gc in nodeChildren" :key="gc.id">
                            <div x-data="treeNode(gc)" class="ml-6 border-l-2 border-[var(--ui-border)]/20">
                                <div x-html="renderNode(gc)"></div>
                                <div x-show="nodeExpanded" x-collapse x-cloak>
                                    <div x-html="renderLinkGroups(gc)"></div>
                                    <template x-for="ggc in nodeChildren" :key="ggc.id">
                                        <div x-data="treeNode(ggc)" class="ml-6 border-l-2 border-[var(--ui-border)]/20">
                                            <div x-html="renderNode(ggc)"></div>
                                            <div x-show="nodeExpanded" x-collapse x-cloak>
                                                <div x-html="renderLinkGroups(ggc)"></div>
                                                <template x-for="gggc in nodeChildren" :key="gggc.id">
                                                    <div x-data="treeNode(gggc)" class="ml-6 border-l-2 border-[var(--ui-border)]/20">
                                                        <div x-html="renderNode(gggc)"></div>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    @endif
</div>
