<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Umwelt'],
            ['label' => 'Snapshots'],
        ]">
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Quelle</h3>
                    <select
                        name="sourceFilter"
                        wire:model.live="sourceFilter"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 text-sm"
                    >
                        <option value="">Alle Quellen</option>
                        @foreach($this->sources as $source)
                            <option value="{{ $source->id }}">{{ $source->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Learned Relevance Info --}}
                @if($this->sourceRelevanceMemories->isNotEmpty())
                    <div>
                        <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Gelernte Relevanz</h3>
                        <div class="space-y-3">
                            @foreach($this->sourceRelevanceMemories as $memory)
                                @php
                                    $data = $memory->structured_data ?? [];
                                    $rating = $data['relevance_rating'] ?? 0.5;
                                    $confidence = $memory->confidence;
                                @endphp
                                <div class="text-xs">
                                    <div class="flex justify-between">
                                        <span class="font-medium truncate">{{ $data['source_name'] ?? '?' }}</span>
                                        <span class="text-[var(--ui-muted)]">{{ number_format($confidence, 2) }}</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-1.5 mt-1">
                                        <div class="h-1.5 rounded-full {{ $rating >= 0.7 ? 'bg-green-500' : ($rating >= 0.4 ? 'bg-yellow-500' : 'bg-red-400') }}" style="width: {{ $rating * 100 }}%"></div>
                                    </div>
                                    @if(!empty($data['topics_useful']))
                                        <div class="flex flex-wrap gap-1 mt-1">
                                            @foreach(array_slice($data['topics_useful'], 0, 5) as $t)
                                                <x-ui-badge variant="success" size="xs">{{ $t }}</x-ui-badge>
                                            @endforeach
                                        </div>
                                    @endif
                                    @if(!empty($data['topics_noise']))
                                        <div class="flex flex-wrap gap-1 mt-1">
                                            @foreach(array_slice($data['topics_noise'], 0, 5) as $t)
                                                <x-ui-badge variant="danger" size="xs">{{ $t }}</x-ui-badge>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitaeten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-6 text-sm text-[var(--ui-muted)]">Keine Aktivitaeten verfuegbar</div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container>
        <x-ui-table compact="true">
            <x-ui-table-header>
                <x-ui-table-header-cell compact="true">Datum</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Quelle</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Sentiment</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Relevanz</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Topics</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Summary</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Items</x-ui-table-header-cell>
            </x-ui-table-header>

            <x-ui-table-body>
                @forelse($this->snapshots as $snapshot)
                    @php
                        $metrics = $snapshot->metrics ?? [];
                        $sentiment = $metrics['sentiment_score'] ?? null;
                        $relevance = $metrics['relevance_score'] ?? null;
                        $topics = $metrics['topics'] ?? [];
                        $itemCount = $metrics['new_items_count'] ?? 0;
                    @endphp
                    <x-ui-table-row compact="true" class="cursor-pointer" wire:click="toggleExpand({{ $snapshot->id }})">
                        <x-ui-table-cell compact="true">
                            <span class="text-xs">{{ $snapshot->snapshot_date->format('d.m.Y') }}</span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="font-medium text-sm">{{ $snapshot->source?->name ?? '-' }}</span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            @if($sentiment !== null)
                                <span class="inline-block w-3 h-3 rounded-full {{ $sentiment > 0.3 ? 'bg-green-500' : ($sentiment < -0.3 ? 'bg-red-500' : 'bg-yellow-500') }}" title="Sentiment: {{ number_format($sentiment, 2) }}"></span>
                            @else
                                <span class="text-[var(--ui-muted)]">-</span>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            @if($relevance !== null)
                                <div class="w-16 bg-gray-200 rounded-full h-2">
                                    <div class="h-2 rounded-full {{ $relevance >= 0.7 ? 'bg-green-500' : ($relevance >= 0.4 ? 'bg-yellow-500' : 'bg-gray-400') }}" style="width: {{ $relevance * 100 }}%"></div>
                                </div>
                            @else
                                <span class="text-[var(--ui-muted)]">-</span>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <div class="flex flex-wrap gap-1">
                                @foreach(array_slice($topics, 0, 3) as $topic)
                                    <x-ui-badge variant="muted">{{ $topic }}</x-ui-badge>
                                @endforeach
                                @if(count($topics) > 3)
                                    <span class="text-xs text-[var(--ui-muted)]">+{{ count($topics) - 3 }}</span>
                                @endif
                            </div>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="text-xs text-[var(--ui-secondary)] truncate max-w-[250px] inline-block">
                                {{ \Illuminate\Support\Str::limit($snapshot->summary, 80) }}
                            </span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            {{ $itemCount }}
                        </x-ui-table-cell>
                    </x-ui-table-row>

                    {{-- Expanded Details --}}
                    @if($expandedId === $snapshot->id)
                        <x-ui-table-row compact="true">
                            <x-ui-table-cell compact="true" colspan="7">
                                <div class="p-4 bg-[var(--ui-muted-5)] rounded-lg space-y-3">
                                    <div>
                                        <h4 class="text-xs font-bold text-[var(--ui-secondary)] uppercase mb-1">Zusammenfassung</h4>
                                        <p class="text-sm text-[var(--ui-secondary)]">{{ $snapshot->summary }}</p>
                                    </div>

                                    @if(!empty($metrics['org_relevance_reasoning']))
                                        <div>
                                            <h4 class="text-xs font-bold text-[var(--ui-secondary)] uppercase mb-1">Org-Relevanz</h4>
                                            <p class="text-sm text-[var(--ui-muted)]">{{ $metrics['org_relevance_reasoning'] }}</p>
                                        </div>
                                    @endif

                                    @if(!empty($topics))
                                        <div>
                                            <h4 class="text-xs font-bold text-[var(--ui-secondary)] uppercase mb-1">Alle Topics</h4>
                                            <div class="flex flex-wrap gap-2">
                                                @foreach($topics as $topic)
                                                    @php
                                                        $topicStatus = $this->getTopicStatus($snapshot->source_id, $topic);
                                                        $badgeVariant = match($topicStatus) {
                                                            'useful' => 'success',
                                                            'noise' => 'danger',
                                                            default => 'info',
                                                        };
                                                    @endphp
                                                    <span class="inline-flex items-center gap-0.5">
                                                        <x-ui-badge variant="{{ $badgeVariant }}">{{ $topic }}</x-ui-badge>
                                                        <button
                                                            wire:click="rateTopic({{ $snapshot->source_id }}, '{{ addslashes($topic) }}', 'useful')"
                                                            class="text-green-600 hover:text-green-800 text-xs p-0.5 {{ $topicStatus === 'useful' ? 'font-bold' : 'opacity-50 hover:opacity-100' }}"
                                                            title="Relevant"
                                                        >&#10003;</button>
                                                        <button
                                                            wire:click="rateTopic({{ $snapshot->source_id }}, '{{ addslashes($topic) }}', 'noise')"
                                                            class="text-red-600 hover:text-red-800 text-xs p-0.5 {{ $topicStatus === 'noise' ? 'font-bold' : 'opacity-50 hover:opacity-100' }}"
                                                            title="Rauschen"
                                                        >&#10007;</button>
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    @if(!empty($snapshot->raw_items))
                                        <div>
                                            <h4 class="text-xs font-bold text-[var(--ui-secondary)] uppercase mb-1">Raw Items ({{ count($snapshot->raw_items) }})</h4>
                                            <div class="space-y-1 max-h-64 overflow-y-auto">
                                                @foreach($snapshot->raw_items as $item)
                                                    <div class="text-xs border-l-2 border-gray-300 pl-2">
                                                        <strong>{{ $item['title'] ?? '' }}</strong>
                                                        @if(!empty($item['link']))
                                                            <a href="{{ $item['link'] }}" target="_blank" class="text-[var(--ui-primary)] ml-1">Link</a>
                                                        @endif
                                                        @if(!empty($item['description']))
                                                            <p class="text-[var(--ui-muted)] mt-0.5">{{ \Illuminate\Support\Str::limit($item['description'], 150) }}</p>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </x-ui-table-cell>
                        </x-ui-table-row>
                    @endif
                @empty
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true" colspan="7">
                            <div class="text-center text-[var(--ui-muted)] py-8">
                                Keine Snapshots vorhanden. Snapshots werden automatisch beim Pull der Quellen erstellt.
                            </div>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @endforelse
            </x-ui-table-body>
        </x-ui-table>
    </x-ui-page-container>
</x-ui-page>
