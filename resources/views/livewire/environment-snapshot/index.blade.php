@php
    $alle = $this->snapshots;
    $snapshots = $alle->take($limit);
    $mehr = $alle->count() > $limit;
    $sourceOptions = $this->sources->map(fn ($s) => ['value' => $s->id, 'label' => $s->name])->values()->all();
    $ampel = fn ($v, $hi, $lo) => $v >= $hi ? 'var(--nx-success)' : ($v >= $lo ? 'var(--nx-warning)' : 'var(--nx-danger)');
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Umwelt-Snapshots" icon="heroicon-o-camera" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-64" :defaultOpen="true" side="left">
            <div class="flex flex-col gap-4 p-3">
                <x-nx-input-select name="sourceFilter" label="Quelle" size="sm" wire:model.live="sourceFilter"
                    :options="$sourceOptions" nullable nullLabel="Alle Quellen" />

                {{-- Gelernte Relevanz je Quelle (aus dem Memory) --}}
                @if ($this->sourceRelevanceMemories->isNotEmpty())
                    <div class="flex flex-col gap-3 border-t border-[color:var(--nx-line)] pt-3">
                        <span class="px-1 text-xs font-medium tracking-wide text-[color:var(--nx-faint)]">Gelernte Relevanz</span>
                        @foreach ($this->sourceRelevanceMemories as $memory)
                            @php
                                $data = $memory->structured_data ?? [];
                                $rating = (float) ($data['relevance_rating'] ?? 0.5);
                            @endphp
                            <div class="px-1 text-xs">
                                <div class="flex justify-between gap-2">
                                    <span class="truncate font-medium text-[color:var(--nx-text)]">{{ $data['source_name'] ?? '?' }}</span>
                                    <span class="tabular-nums text-[color:var(--nx-faint)]" title="Konfidenz">{{ number_format($memory->confidence, 2) }}</span>
                                </div>
                                <div class="mt-1 h-1.5 w-full rounded-full bg-[color:var(--nx-accent-soft)]">
                                    <div class="h-1.5 rounded-full" style="width: {{ $rating * 100 }}%; background: {{ $ampel($rating, 0.7, 0.4) }}"></div>
                                </div>
                                @if (! empty($data['topics_useful']) || ! empty($data['topics_noise']))
                                    <div class="mt-1.5 flex flex-wrap gap-1">
                                        @foreach (array_slice($data['topics_useful'] ?? [], 0, 5) as $t)
                                            <x-nx-badge variant="success">{{ $t }}</x-nx-badge>
                                        @endforeach
                                        @foreach (array_slice($data['topics_noise'] ?? [], 0, 5) as $t)
                                            <x-nx-badge variant="danger">{{ $t }}</x-nx-badge>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organisation', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Umwelt-Snapshots'],
        ]">
            <x-nx-button size="sm" :href="route('organization.environment-sources.index')" wire:navigate>
                @svg('heroicon-o-globe-alt', 'w-4 h-4')
                <span>Quellen</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
        <x-nx-card flush>
            @if ($snapshots->isEmpty())
                <x-nx-empty icon="heroicon-o-camera">
                    Keine Snapshots vorhanden. Sie entstehen automatisch, wenn die Quellen abgerufen werden.
                </x-nx-empty>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs text-[color:var(--nx-muted)]">
                                <th class="border-b border-[color:var(--nx-line)] px-4 py-2.5 text-left font-medium">Datum</th>
                                <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-left font-medium">Quelle · Zusammenfassung</th>
                                <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-left font-medium">Themen</th>
                                <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-center font-medium">Stimmung</th>
                                <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-left font-medium">Relevanz</th>
                                <th class="border-b border-[color:var(--nx-line)] px-4 py-2.5 text-right font-medium">Einträge</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($snapshots as $snapshot)
                                @php
                                    $metrics = $snapshot->metrics ?? [];
                                    $sentiment = $metrics['sentiment_score'] ?? null;
                                    $relevance = $metrics['relevance_score'] ?? null;
                                    $topics = $metrics['topics'] ?? [];
                                @endphp
                                <tr wire:key="snap-{{ $snapshot->id }}" class="cursor-pointer border-b border-[color:var(--nx-line)] last:border-0 hover:bg-[color:var(--nx-hover)]"
                                    onclick="window.location.href='{{ route('organization.environment-snapshots.show', $snapshot) }}'">
                                    <td class="whitespace-nowrap px-4 py-2 tabular-nums text-[color:var(--nx-muted)]">{{ $snapshot->snapshot_date->format('d.m.Y') }}</td>
                                    <td class="max-w-md px-3 py-2">
                                        <div class="font-medium text-[color:var(--nx-text)]">{{ $snapshot->source?->name ?? '–' }}</div>
                                        @if ($snapshot->summary)
                                            <div class="truncate text-xs text-[color:var(--nx-muted)]" title="{{ $snapshot->summary }}">{{ \Illuminate\Support\Str::limit($snapshot->summary, 120) }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach (array_slice($topics, 0, 3) as $topic)
                                                <span class="rounded-full bg-[color:var(--nx-accent-soft)] px-2 py-0.5 text-xs text-[color:var(--nx-muted)]">{{ $topic }}</span>
                                            @endforeach
                                            @if (count($topics) > 3)
                                                <span class="text-xs text-[color:var(--nx-faint)]">+{{ count($topics) - 3 }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        @if ($sentiment !== null)
                                            <span class="inline-block h-2.5 w-2.5 rounded-full" title="Stimmung: {{ number_format($sentiment, 2) }}"
                                                style="background: {{ $sentiment > 0.3 ? 'var(--nx-success)' : ($sentiment < -0.3 ? 'var(--nx-danger)' : 'var(--nx-warning)') }}"></span>
                                        @else
                                            <span class="text-[color:var(--nx-faint)]">–</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        @if ($relevance !== null)
                                            <div class="h-1.5 w-16 rounded-full bg-[color:var(--nx-accent-soft)]" title="Relevanz: {{ number_format($relevance, 2) }}">
                                                <div class="h-1.5 rounded-full" style="width: {{ $relevance * 100 }}%; background: {{ $relevance >= 0.7 ? 'var(--nx-success)' : ($relevance >= 0.4 ? 'var(--nx-warning)' : 'var(--nx-faint)') }}"></div>
                                            </div>
                                        @else
                                            <span class="text-[color:var(--nx-faint)]">–</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right tabular-nums text-[color:var(--nx-text)]">{{ $metrics['new_items_count'] ?? 0 }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($mehr)
                    <div class="flex items-center justify-between border-t border-[color:var(--nx-line)] px-4 py-2.5">
                        <span class="text-xs text-[color:var(--nx-faint)]">{{ $snapshots->count() }} angezeigt</span>
                        <x-nx-button size="sm" wire:click="loadMore">
                            <span wire:loading.remove wire:target="loadMore">Weitere laden</span>
                            <span wire:loading wire:target="loadMore">Lädt…</span>
                        </x-nx-button>
                    </div>
                @endif
            @endif
        </x-nx-card>
    </x-ui-page-container>
</x-ui-page>
