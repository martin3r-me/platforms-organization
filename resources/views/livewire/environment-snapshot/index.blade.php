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
                    <x-ui-table-row compact="true" class="cursor-pointer" onclick="window.location='{{ route('organization.environment-snapshots.show', $snapshot) }}'">
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
