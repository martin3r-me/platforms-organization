{{-- Entity group header --}}
<div wire:key="node-{{ $node['entity']?->id ?? 'unowned' }}-{{ $node['depth'] }}" style="padding-left: {{ $node['depth'] * 24 }}px;">
    <div class="flex items-center gap-2 py-2 px-3 {{ $node['depth'] === 0 ? 'bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 rounded-lg mt-2' : '' }}">
        @if($node['depth'] === 0)
            @svg('heroicon-o-building-office-2', 'w-4 h-4 text-[var(--ui-muted)] flex-shrink-0')
        @else
            @svg('heroicon-o-folder', 'w-3.5 h-3.5 text-[var(--ui-muted)] flex-shrink-0')
        @endif
        <span class="text-sm font-semibold text-[var(--ui-secondary)]">{{ $node['label'] }}</span>
        @if($node['entity_type'])
            <span class="text-[10px] text-[var(--ui-muted)] bg-[var(--ui-muted-10)] px-1.5 py-0.5 rounded">{{ $node['entity_type'] }}</span>
        @endif
        @php
            // Count all processes in this node + children recursively
            $countAll = count($node['processes']);
            $stack = $node['children'];
            while (!empty($stack)) {
                $child = array_shift($stack);
                $countAll += count($child['processes']);
                $stack = array_merge($stack, $child['children']);
            }
        @endphp
        <span class="text-[10px] text-[var(--ui-muted)]">{{ $countAll }} {{ $countAll === 1 ? 'Prozess' : 'Prozesse' }}</span>
    </div>
</div>

{{-- Direct processes of this entity --}}
@foreach($node['processes'] as $process)
    <div wire:key="process-{{ $process->id }}" style="padding-left: {{ ($node['depth'] + 1) * 24 }}px;">
        <div class="flex items-center gap-3 py-2 px-3 hover:bg-[var(--ui-muted-5)] rounded transition-colors group">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                    @svg('heroicon-o-arrow-right-circle', 'w-3.5 h-3.5 text-[var(--ui-muted)] flex-shrink-0')
                    <a href="{{ route('organization.processes.show', $process) }}" class="text-sm font-medium text-[var(--ui-primary)] hover:underline truncate" wire:navigate>
                        {{ $process->name }}
                    </a>
                    @if($process->code)
                        <code class="text-[10px] text-[var(--ui-muted)] flex-shrink-0">{{ $process->code }}</code>
                    @endif
                    @if($process->is_focus)
                        <span class="text-yellow-500" title="{{ $process->focus_reason ?? 'Fokus-Prozess' }}">
                            @svg('heroicon-s-star', 'w-4 h-4')
                        </span>
                    @endif
                    @if($process->status === 'active')
                        <x-ui-badge variant="success" size="sm">Aktiv</x-ui-badge>
                    @elseif($process->status === 'draft')
                        <x-ui-badge variant="muted" size="sm">Entwurf</x-ui-badge>
                    @else
                        <x-ui-badge variant="danger" size="sm">Veraltet</x-ui-badge>
                    @endif
                    @if($process->process_category)
                        <x-ui-badge variant="{{ $process->process_category->color() }}" size="sm">
                            @svg($process->process_category->icon(), 'w-3 h-3')
                            {{ $process->process_category->label() }}
                        </x-ui-badge>
                    @endif
                    @if($process->vsmSystem)
                        <x-ui-badge variant="muted" size="sm">
                            @svg('heroicon-o-circle-stack', 'w-3 h-3')
                            {{ $process->vsmSystem->name }}
                        </x-ui-badge>
                    @endif
                </div>
                @if($process->description)
                    <div class="text-xs text-[var(--ui-muted)] ml-5.5 truncate">{{ \Illuminate\Support\Str::limit($process->description, 80) }}</div>
                @endif
            </div>

            {{-- Steps count --}}
            <span class="text-xs text-[var(--ui-muted)] flex-shrink-0 w-14 text-right">{{ $process->steps_count }} Steps</span>

            {{-- LLM Quote --}}
            @php
                $totalSteps = $process->steps->count();
                $llmSteps = $process->steps->whereIn('automation_level', ['llm_assisted', 'llm_autonomous', 'hybrid'])->count();
                $llmQuote = $totalSteps > 0 ? round(($llmSteps / $totalSteps) * 100) : 0;
            @endphp
            <div class="flex items-center gap-1.5 flex-shrink-0 w-20">
                @if($totalSteps > 0)
                    <div class="w-12 bg-[var(--ui-muted-20)] rounded-full h-1.5">
                        <div class="h-1.5 rounded-full {{ $llmQuote >= 70 ? 'bg-[var(--ui-success)]' : ($llmQuote >= 30 ? 'bg-[var(--ui-info)]' : 'bg-[var(--ui-muted)]') }}" style="width: {{ $llmQuote }}%"></div>
                    </div>
                    <span class="text-[10px] font-medium text-[var(--ui-secondary)]">{{ $llmQuote }}%</span>
                @else
                    <span class="text-[10px] text-[var(--ui-muted)]">–</span>
                @endif
            </div>

            {{-- Actions --}}
            <div class="flex gap-1 flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                <x-ui-button size="xs" variant="secondary-outline" wire:click="edit({{ $process->id }})">
                    @svg('heroicon-o-pencil-square', 'w-3.5 h-3.5')
                </x-ui-button>
                <x-ui-confirm-button size="xs" variant="danger-outline" wire:click="delete({{ $process->id }})" confirm-text="Prozess wirklich löschen?">
                    @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                </x-ui-confirm-button>
            </div>
        </div>
    </div>
@endforeach

{{-- Recursive children --}}
@foreach($node['children'] as $childNode)
    @include('organization::livewire.process.partials.tree-node', ['node' => $childNode])
@endforeach
