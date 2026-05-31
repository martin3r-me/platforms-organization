@php
    $metrics = $snapshot->metrics ?? [];
    $sentiment = $metrics['sentiment_score'] ?? null;
    $relevance = $metrics['relevance_score'] ?? null;
    $topics = $metrics['topics'] ?? [];
    $itemCount = $metrics['new_items_count'] ?? 0;

    $sourceMemory = $this->sourceRelevanceMemories[$snapshot->source_id] ?? null;
    $memoryData = $sourceMemory?->structured_data ?? [];
    $memoryRating = $memoryData['relevance_rating'] ?? null;
    $memoryConfidence = $sourceMemory?->confidence;
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Umwelt'],
            ['label' => 'Snapshots', 'href' => route('organization.environment-snapshots.index')],
            ['label' => ($snapshot->source?->name ?? 'Snapshot') . ' ' . $snapshot->snapshot_date->format('d.m.Y')],
        ]">
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Details" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                {{-- Metadaten --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Metadaten</h3>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-[var(--ui-muted)]">Quelle</dt>
                            <dd class="font-medium">{{ $snapshot->source?->name ?? '-' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-[var(--ui-muted)]">Datum</dt>
                            <dd class="font-medium">{{ $snapshot->snapshot_date->format('d.m.Y') }}</dd>
                        </div>
                        <div class="flex justify-between items-center">
                            <dt class="text-[var(--ui-muted)]">Sentiment</dt>
                            <dd>
                                @if($sentiment !== null)
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="inline-block w-3 h-3 rounded-full {{ $sentiment > 0.3 ? 'bg-green-500' : ($sentiment < -0.3 ? 'bg-red-500' : 'bg-yellow-500') }}"></span>
                                        <span class="font-medium">{{ number_format($sentiment, 2) }}</span>
                                    </span>
                                @else
                                    <span class="text-[var(--ui-muted)]">-</span>
                                @endif
                            </dd>
                        </div>
                        <div class="flex justify-between items-center">
                            <dt class="text-[var(--ui-muted)]">Relevanz</dt>
                            <dd>
                                @if($relevance !== null)
                                    <span class="inline-flex items-center gap-1.5">
                                        <div class="w-16 bg-gray-200 rounded-full h-2">
                                            <div class="h-2 rounded-full {{ $relevance >= 0.7 ? 'bg-green-500' : ($relevance >= 0.4 ? 'bg-yellow-500' : 'bg-gray-400') }}" style="width: {{ $relevance * 100 }}%"></div>
                                        </div>
                                        <span class="font-medium">{{ number_format($relevance, 2) }}</span>
                                    </span>
                                @else
                                    <span class="text-[var(--ui-muted)]">-</span>
                                @endif
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-[var(--ui-muted)]">Items</dt>
                            <dd class="font-medium">{{ $itemCount }}</dd>
                        </div>
                    </dl>
                </div>

                {{-- Gelernte Relevanz fuer diese Source --}}
                @if($sourceMemory)
                    <div>
                        <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Gelernte Relevanz</h3>
                        <div class="text-xs">
                            <div class="flex justify-between mb-1">
                                <span class="font-medium">{{ $memoryData['source_name'] ?? '?' }}</span>
                                <span class="text-[var(--ui-muted)]">{{ number_format($memoryConfidence, 2) }}</span>
                            </div>
                            @if($memoryRating !== null)
                                <div class="w-full bg-gray-200 rounded-full h-1.5">
                                    <div class="h-1.5 rounded-full {{ $memoryRating >= 0.7 ? 'bg-green-500' : ($memoryRating >= 0.4 ? 'bg-yellow-500' : 'bg-red-400') }}" style="width: {{ $memoryRating * 100 }}%"></div>
                                </div>
                            @endif
                            @if(!empty($memoryData['topics_useful']))
                                <div class="flex flex-wrap gap-1 mt-2">
                                    @foreach(array_slice($memoryData['topics_useful'], 0, 8) as $t)
                                        <x-ui-badge variant="success" size="xs">{{ $t }}</x-ui-badge>
                                    @endforeach
                                </div>
                            @endif
                            @if(!empty($memoryData['topics_noise']))
                                <div class="flex flex-wrap gap-1 mt-1">
                                    @foreach(array_slice($memoryData['topics_noise'], 0, 8) as $t)
                                        <x-ui-badge variant="danger" size="xs">{{ $t }}</x-ui-badge>
                                    @endforeach
                                </div>
                            @endif
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
        <div class="space-y-6">
            {{-- Zusammenfassung --}}
            <x-ui-card>
                <x-ui-card-header>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider">Zusammenfassung</h3>
                </x-ui-card-header>
                <x-ui-card-body>
                    <p class="text-sm text-[var(--ui-secondary)] whitespace-pre-line">{{ $snapshot->summary }}</p>
                </x-ui-card-body>
            </x-ui-card>

            {{-- Org-Relevanz-Reasoning --}}
            @if(!empty($metrics['org_relevance_reasoning']))
                <x-ui-card>
                    <x-ui-card-header>
                        <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider">Org-Relevanz</h3>
                    </x-ui-card-header>
                    <x-ui-card-body>
                        <p class="text-sm text-[var(--ui-muted)]">{{ $metrics['org_relevance_reasoning'] }}</p>
                    </x-ui-card-body>
                </x-ui-card>
            @endif

            {{-- Topics mit Feedback --}}
            @if(!empty($topics))
                <x-ui-card>
                    <x-ui-card-header>
                        <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider">Topics</h3>
                    </x-ui-card-header>
                    <x-ui-card-body>
                        <div class="flex flex-wrap gap-3">
                            @foreach($topics as $topic)
                                @php
                                    $topicStatus = $this->getTopicStatus($snapshot->source_id, $topic);
                                    $badgeVariant = match($topicStatus) {
                                        'useful' => 'success',
                                        'noise' => 'danger',
                                        default => 'info',
                                    };
                                @endphp
                                <span class="inline-flex items-center gap-1">
                                    <x-ui-badge variant="{{ $badgeVariant }}">{{ $topic }}</x-ui-badge>
                                    <button
                                        wire:click="rateTopic({{ $snapshot->source_id }}, '{{ addslashes($topic) }}', 'useful')"
                                        class="text-green-600 hover:text-green-800 text-sm p-0.5 {{ $topicStatus === 'useful' ? 'font-bold' : 'opacity-50 hover:opacity-100' }}"
                                        title="Relevant"
                                    >&#10003;</button>
                                    <button
                                        wire:click="rateTopic({{ $snapshot->source_id }}, '{{ addslashes($topic) }}', 'noise')"
                                        class="text-red-600 hover:text-red-800 text-sm p-0.5 {{ $topicStatus === 'noise' ? 'font-bold' : 'opacity-50 hover:opacity-100' }}"
                                        title="Rauschen"
                                    >&#10007;</button>
                                </span>
                            @endforeach
                        </div>
                    </x-ui-card-body>
                </x-ui-card>
            @endif

            {{-- Raw Items --}}
            @if(!empty($snapshot->raw_items))
                <x-ui-card>
                    <x-ui-card-header>
                        <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider">Raw Items ({{ count($snapshot->raw_items) }})</h3>
                    </x-ui-card-header>
                    <x-ui-card-body>
                        <div class="space-y-2">
                            @foreach($snapshot->raw_items as $item)
                                <div class="text-sm border-l-2 border-gray-300 pl-3 py-1">
                                    <strong>{{ $item['title'] ?? '' }}</strong>
                                    @if(!empty($item['link']))
                                        <a href="{{ $item['link'] }}" target="_blank" class="text-[var(--ui-primary)] ml-1">Link</a>
                                    @endif
                                    @if(!empty($item['description']))
                                        <p class="text-[var(--ui-muted)] mt-0.5">{{ $item['description'] }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </x-ui-card-body>
                </x-ui-card>
            @endif
        </div>
    </x-ui-page-container>
</x-ui-page>
