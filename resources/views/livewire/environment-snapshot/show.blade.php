@php
    $metrics = $snapshot->metrics ?? [];
    $sentiment = $metrics['sentiment_score'] ?? null;
    $relevance = $metrics['relevance_score'] ?? null;
    $topics = $metrics['topics'] ?? [];
    $itemCount = $metrics['new_items_count'] ?? 0;

    $sourceMemory = $this->sourceRelevanceMemories[$snapshot->source_id] ?? null;
    $memoryData = $sourceMemory?->structured_data ?? [];
    $memoryRating = $memoryData['relevance_rating'] ?? null;

    $titel = ($snapshot->source?->name ?? 'Snapshot') . ' · ' . $snapshot->snapshot_date->format('d.m.Y');
    $ampel = fn ($v) => $v >= 0.7 ? 'var(--nx-success)' : ($v >= 0.4 ? 'var(--nx-warning)' : 'var(--nx-danger)');
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$titel" icon="heroicon-o-camera" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organisation', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Umwelt-Snapshots', 'href' => route('organization.environment-snapshots.index')],
            ['label' => $titel],
        ]" />
    </x-slot>

    <x-ui-page-container width="contained">
        <div class="space-y-5">
            {{-- Kopf: Identität + Kennwerte --}}
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="m-0 text-2xl font-semibold tracking-tight text-[color:var(--nx-text)]">{{ $snapshot->source?->name ?? 'Snapshot' }}</h1>
                <span class="text-sm text-[color:var(--nx-muted)]">{{ $snapshot->snapshot_date->format('d.m.Y') }}</span>
            </div>

            <x-nx-panel title="Eckdaten">
                <div class="grid grid-cols-1 gap-x-6 md:grid-cols-2">
                    <x-nx-property-row icon="heroicon-o-globe-alt" label="Quelle">{{ $snapshot->source?->name ?? '–' }}</x-nx-property-row>
                    <x-nx-property-row icon="heroicon-o-calendar" label="Datum">{{ $snapshot->snapshot_date->format('d.m.Y') }}</x-nx-property-row>
                    <x-nx-property-row icon="heroicon-o-face-smile" label="Stimmung">
                        @if ($sentiment !== null)
                            <span class="inline-flex items-center gap-1.5">
                                <span class="inline-block h-2.5 w-2.5 rounded-full" style="background: {{ $sentiment > 0.3 ? 'var(--nx-success)' : ($sentiment < -0.3 ? 'var(--nx-danger)' : 'var(--nx-warning)') }}"></span>
                                <span class="tabular-nums">{{ number_format($sentiment, 2) }}</span>
                            </span>
                        @else
                            <span class="text-[color:var(--nx-faint)]">–</span>
                        @endif
                    </x-nx-property-row>
                    <x-nx-property-row icon="heroicon-o-adjustments-horizontal" label="Relevanz">
                        @if ($relevance !== null)
                            <span class="inline-flex items-center gap-2">
                                <span class="block h-1.5 w-16 rounded-full bg-[color:var(--nx-accent-soft)]">
                                    <span class="block h-1.5 rounded-full" style="width: {{ $relevance * 100 }}%; background: {{ $ampel($relevance) }}"></span>
                                </span>
                                <span class="tabular-nums">{{ number_format($relevance, 2) }}</span>
                            </span>
                        @else
                            <span class="text-[color:var(--nx-faint)]">–</span>
                        @endif
                    </x-nx-property-row>
                    <x-nx-property-row icon="heroicon-o-queue-list" label="Neue Einträge"><span class="tabular-nums">{{ $itemCount }}</span></x-nx-property-row>
                </div>
            </x-nx-panel>

            <x-nx-panel title="Zusammenfassung">
                <p class="m-0 whitespace-pre-line text-sm text-[color:var(--nx-text)]">{{ $snapshot->summary ?: '–' }}</p>
            </x-nx-panel>

            @if (! empty($metrics['org_relevance_reasoning']))
                <x-nx-panel title="Relevanz für die Organisation">
                    <p class="m-0 text-sm text-[color:var(--nx-muted)]">{{ $metrics['org_relevance_reasoning'] }}</p>
                </x-nx-panel>
            @endif

            {{-- Themen mit Feedback: ✓ relevant / ✗ Rauschen → lernt die Quellen-Relevanz --}}
            @if (! empty($topics))
                <x-nx-panel title="Themen" subtitle="Markieren, was relevant ist und was Rauschen — das fließt in die gelernte Relevanz der Quelle.">
                    <div class="flex flex-wrap gap-2">
                        @foreach ($topics as $topic)
                            @php
                                $topicStatus = $this->getTopicStatus($snapshot->source_id, $topic);
                            @endphp
                            <span wire:key="topic-{{ md5($topic) }}" class="inline-flex items-center gap-0.5 rounded-full border border-[color:var(--nx-line)] py-0.5 pl-2.5 pr-1 text-xs
                                {{ $topicStatus === 'useful' ? 'bg-[rgba(47,158,68,.12)] text-[color:var(--nx-success)]' : ($topicStatus === 'noise' ? 'bg-[rgba(224,49,49,.12)] text-[color:var(--nx-danger)]' : 'text-[color:var(--nx-text)]') }}">
                                <span class="mr-1">{{ $topic }}</span>
                                <button type="button" title="Relevant"
                                    wire:click="rateTopic({{ $snapshot->source_id }}, '{{ addslashes($topic) }}', 'useful')"
                                    class="rounded-full p-0.5 transition-colors hover:bg-[color:var(--nx-hover)] {{ $topicStatus === 'useful' ? 'text-[color:var(--nx-success)]' : 'text-[color:var(--nx-faint)] hover:text-[color:var(--nx-success)]' }}">
                                    @svg('heroicon-o-check', 'w-3.5 h-3.5')
                                </button>
                                <button type="button" title="Rauschen"
                                    wire:click="rateTopic({{ $snapshot->source_id }}, '{{ addslashes($topic) }}', 'noise')"
                                    class="rounded-full p-0.5 transition-colors hover:bg-[color:var(--nx-hover)] {{ $topicStatus === 'noise' ? 'text-[color:var(--nx-danger)]' : 'text-[color:var(--nx-faint)] hover:text-[color:var(--nx-danger)]' }}">
                                    @svg('heroicon-o-x-mark', 'w-3.5 h-3.5')
                                </button>
                            </span>
                        @endforeach
                    </div>
                </x-nx-panel>
            @endif

            @if ($sourceMemory)
                <x-nx-panel title="Gelernte Relevanz der Quelle" :subtitle="'Konfidenz ' . number_format($sourceMemory->confidence, 2)">
                    <div class="space-y-2">
                        @if ($memoryRating !== null)
                            <div class="flex items-center gap-2">
                                <div class="h-1.5 flex-1 rounded-full bg-[color:var(--nx-accent-soft)]">
                                    <div class="h-1.5 rounded-full" style="width: {{ $memoryRating * 100 }}%; background: {{ $ampel($memoryRating) }}"></div>
                                </div>
                                <span class="text-xs tabular-nums text-[color:var(--nx-muted)]">{{ number_format($memoryRating, 2) }}</span>
                            </div>
                        @endif
                        @if (! empty($memoryData['topics_useful']) || ! empty($memoryData['topics_noise']))
                            <div class="flex flex-wrap gap-1">
                                @foreach (array_slice($memoryData['topics_useful'] ?? [], 0, 8) as $t)
                                    <x-nx-badge variant="success">{{ $t }}</x-nx-badge>
                                @endforeach
                                @foreach (array_slice($memoryData['topics_noise'] ?? [], 0, 8) as $t)
                                    <x-nx-badge variant="danger">{{ $t }}</x-nx-badge>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </x-nx-panel>
            @endif

            @if (! empty($snapshot->raw_items))
                <x-nx-panel flush title="Rohdaten" :subtitle="count($snapshot->raw_items) . ' Einträge'">
                    <div class="divide-y divide-[color:var(--nx-line)]">
                        @foreach ($snapshot->raw_items as $item)
                            <div class="px-4 py-2.5 text-sm">
                                <div class="flex items-start gap-2">
                                    <span class="flex-1 font-medium text-[color:var(--nx-text)]">{{ $item['title'] ?? '' }}</span>
                                    @if (! empty($item['link']))
                                        <a href="{{ $item['link'] }}" target="_blank" rel="noopener noreferrer" class="shrink-0 text-[color:var(--nx-faint)] hover:text-[color:var(--nx-text)]" title="Öffnen">
                                            @svg('heroicon-o-arrow-top-right-on-square', 'w-4 h-4')
                                        </a>
                                    @endif
                                </div>
                                @if (! empty($item['description']))
                                    <p class="m-0 mt-0.5 text-[color:var(--nx-muted)]">{{ $item['description'] }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </x-nx-panel>
            @endif
        </div>
    </x-ui-page-container>
</x-ui-page>
