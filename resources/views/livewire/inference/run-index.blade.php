@php
    $alle = $this->runs;
    $runs = $alle->take($limit);
    $mehr = $alle->count() > $limit;
    $statusVariant = fn ($s) => match ($s) { 'running' => 'warning', 'completed' => 'success', 'failed' => 'danger', default => 'neutral' };
    $statusLabel = fn ($s) => match ($s) { 'running' => 'Läuft', 'completed' => 'Fertig', 'failed' => 'Fehler', default => ucfirst((string) $s) };
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Inference-Läufe" icon="heroicon-o-cpu-chip" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organisation', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Inference-Läufe'],
        ]">
            <x-slot name="left">
                <div class="flex items-center gap-3">
                    <div class="w-56">
                        <x-nx-input-text name="search" size="sm" placeholder="Zusammenfassung, Modell…" wire:model.live.debounce.400ms="search" />
                    </div>
                    <div class="w-40">
                        <x-nx-input-select name="statusFilter" size="sm" wire:model.live="statusFilter" nullable nullLabel="Alle Status"
                            :options="[
                                ['value' => 'running', 'label' => 'Läuft'],
                                ['value' => 'completed', 'label' => 'Fertig'],
                                ['value' => 'failed', 'label' => 'Fehler'],
                            ]" />
                    </div>
                </div>
            </x-slot>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
        <x-nx-card flush>
            @if ($runs->isEmpty())
                <x-nx-empty icon="heroicon-o-cpu-chip">Keine Inference-Läufe gefunden.</x-nx-empty>
            @else
                {{-- Schlichtes Markup statt Komponente pro Zelle: bis zu einige hundert Zeilen. --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs text-[color:var(--nx-muted)]">
                                <th class="border-b border-[color:var(--nx-line)] px-4 py-2.5 text-left font-medium">Lauf</th>
                                <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-left font-medium">Status</th>
                                <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-right font-medium" title="Prompts / Einheiten">Prompts · Einh.</th>
                                <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-right font-medium" title="Signale / Rückfragen / Memory">Sig · Rückfr. · Mem</th>
                                <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-right font-medium">Dauer</th>
                                <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-right font-medium">Kosten</th>
                                <th class="border-b border-[color:var(--nx-line)] px-4 py-2.5 text-right font-medium">Gestartet</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($runs as $run)
                                <tr wire:key="run-{{ $run->id }}" class="cursor-pointer border-b border-[color:var(--nx-line)] last:border-0 hover:bg-[color:var(--nx-hover)]"
                                    onclick="window.location.href='{{ route('organization.inference-runs.show', $run) }}'">
                                    <td class="px-4 py-2">
                                        <a href="{{ route('organization.inference-runs.show', $run) }}" wire:navigate class="font-medium text-[color:var(--nx-text)] hover:underline">
                                            {{ $run->trigger?->name ?? $run->trigger_type ?? 'Lauf' }}
                                        </a>
                                        <div class="font-mono text-xs text-[color:var(--nx-faint)]">
                                            {{ \Illuminate\Support\Str::limit($run->uuid, 8, '') }}@if ($run->llm_model) · {{ $run->llm_model }}@endif
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2" title="{{ $run->status === 'failed' && $run->error_message ? $run->error_message : '' }}">
                                        <x-nx-badge :variant="$statusVariant($run->status)" dot>{{ $statusLabel($run->status) }}</x-nx-badge>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-[color:var(--nx-muted)]">
                                        {{ $run->prompts_evaluated ?? 0 }} · {{ $run->entities_analyzed ?? 0 }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-[color:var(--nx-text)]">
                                        {{ $run->signals_created ?? 0 }} · {{ $run->inquiries_created ?? 0 }} · {{ $run->memory_updates ?? 0 }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-[color:var(--nx-muted)]">
                                        {{ $run->duration_ms ? number_format($run->duration_ms / 1000, 1, ',', '.') . ' s' : '–' }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">
                                        <div class="text-[color:var(--nx-text)]">
                                            {{ $run->cost_eur !== null ? number_format($run->cost_eur, 4, ',', '.') . ' €' : '$' . number_format($run->cost_usd, 4) }}
                                        </div>
                                        @if ($run->cost_per_signal_usd !== null)
                                            <div class="text-xs text-[color:var(--nx-faint)]">${{ number_format($run->cost_per_signal_usd, 4) }}/Signal</div>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right tabular-nums text-[color:var(--nx-muted)]" title="{{ $run->created_at->format('d.m.Y H:i:s') }}">
                                        {{ $run->created_at->format('d.m.Y H:i') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($mehr)
                    <div class="flex items-center justify-between border-t border-[color:var(--nx-line)] px-4 py-2.5">
                        <span class="text-xs text-[color:var(--nx-faint)]">{{ $runs->count() }} angezeigt</span>
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
