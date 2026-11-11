@php
    $rows = $this->rows;
    $trend = $this->trend;
    $fmtDur = function (?int $s) {
        if ($s === null) return '–';
        if ($s < 3600) return max(1, (int) round($s / 60)) . ' min';
        if ($s < 86400) return round($s / 3600, 1) . ' h';
        return round($s / 86400, 1) . ' Tg';
    };
    $fmtNum = fn ($n) => $n === null ? '–' : number_format($n, 0, ',', '.');
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Wirksamkeit" icon="heroicon-o-chart-bar" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organisation', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Agenten', 'href' => route('organization.agents.index')],
            ['label' => 'Wirksamkeit'],
        ]" />
    </x-slot>

    <x-ui-page-container width="contained">
        <div class="space-y-5">
            <div>
                <h1 class="m-0 text-2xl font-semibold tracking-tight text-[color:var(--nx-text)]">Wirksamkeit der Agenten</h1>
                <p class="mt-1 max-w-[680px] text-sm text-[color:var(--nx-muted)]">
                    Was die Agenten in der Organisation bewirken — erledigte Arbeit, was sie Menschen vorlegen, wie oft nachgebessert werden muss, wie schnell sie reagieren und was es kostet. Gezählt wird, wo die Arbeit landet: Planner, Dev, Helpdesk, Zeiterfassung und Vorgänge in Parlan.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="inline-flex rounded-[8px] border border-[color:var(--nx-line)] p-0.5 text-xs">
                    @foreach (['week' => 'Diese Woche', 'last_week' => 'Letzte Woche', '4w' => 'Letzte 4 Wochen'] as $k => $l)
                        <button type="button" wire:click="setRange('{{ $k }}')" class="rounded-[6px] px-3 py-1 {{ $range === $k ? 'bg-[color:var(--nx-active)] font-medium text-[color:var(--nx-text)]' : 'text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]' }}">{{ $l }}</button>
                    @endforeach
                </div>
                <span class="text-xs text-[color:var(--nx-muted)]">{{ $from->format('d.m.') }} – {{ $to->format('d.m.Y') }}</span>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                @foreach ([
                    ['Ergebnisse', $totals['done'], 'erledigte Aufgaben, Tickets, Vorgänge'],
                    ['Anfragen', $totals['requests'], 'an Menschen vorgelegt'],
                    ['Abgelehnt', $totals['declined'], 'Übergaben/Vereinbarungen'],
                    ['Verworfen', $totals['dropped'], 'Aufgaben, die nichts wurden'],
                    ['Korrekturen', $totals['corrections'], 'am Gedächtnis'],
                    ['Zeit', number_format($totals['hours'], 1, ',', '.') . ' h', 'verbucht'],
                ] as [$l, $v, $h])
                    <div class="rounded-[8px] border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] p-3">
                        <div class="text-[11px] uppercase tracking-wide text-[color:var(--nx-faint)]">{{ $l }}</div>
                        <div class="mt-1 text-xl font-semibold tabular-nums text-[color:var(--nx-text)]">{{ $v }}</div>
                        <div class="text-[11px] text-[color:var(--nx-muted)]">{{ $h }}</div>
                    </div>
                @endforeach
            </div>

            <x-nx-panel title="Je Agent" subtitle="Ergebnisse aufgeschlüsselt, dazu Qualität, Tempo und Einsatz" flush>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs tabular-nums">
                        <thead>
                            <tr class="border-b border-[color:var(--nx-line)] text-left text-[11px] uppercase tracking-wide text-[color:var(--nx-faint)]">
                                <th class="px-4 py-2 font-medium">Agent</th>
                                <th class="px-2 py-2 text-right font-medium" title="Summe aus Aufgaben, Tickets und erledigten Vorgängen">Ergebnisse</th>
                                <th class="px-2 py-2 text-right font-medium">Aufgaben</th>
                                <th class="px-2 py-2 text-right font-medium">Tickets</th>
                                <th class="px-2 py-2 text-right font-medium">Vorgänge</th>
                                <th class="px-2 py-2 text-right font-medium" title="Freigaben, Aufgaben, Formulare … an andere">Anfragen</th>
                                <th class="px-2 py-2 text-right font-medium" title="abgelehnte Übergaben/Vereinbarungen + verworfene Aufgaben">Abgelehnt/verworfen</th>
                                <th class="px-2 py-2 text-right font-medium" title="Kuratier-Aufträge am Gedächtnis">Korrekturen</th>
                                <th class="px-2 py-2 text-right font-medium" title="Median der Reaktionszeit auf Vorgänge">Reaktion</th>
                                <th class="px-2 py-2 text-right font-medium">Zeit</th>
                                <th class="px-2 py-2 text-right font-medium" title="Token der letzten 7 Tage je Ergebnis (nur aktueller Zeitraum)">Token/Ergebnis</th>
                                <th class="px-4 py-2 text-right font-medium" title="Ergebnisse je Woche, 8 Wochen">Verlauf</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[color:var(--nx-line)]">
                            @forelse ($rows as $r)
                                @php
                                    $series = array_values($trend[$r['entity_id']] ?? []);
                                    $max = max(1, max($series ?: [0]));
                                    $neg = $r['declined'] + $r['dropped'];
                                @endphp
                                <tr wire:key="eff-{{ $r['entity_id'] }}" class="hover:bg-[color:var(--nx-hover)]">
                                    <td class="px-4 py-2"><a href="{{ route('organization.entities.show', $r['entity_id']) }}" class="font-medium text-[color:var(--nx-text)] hover:underline">{{ $r['name'] }}</a></td>
                                    <td class="px-2 py-2 text-right text-sm font-semibold {{ $r['done'] > 0 ? 'text-[color:var(--nx-text)]' : 'text-[color:var(--nx-faint)]' }}">{{ $r['done'] }}</td>
                                    <td class="px-2 py-2 text-right text-[color:var(--nx-muted)]">{{ $r['tasks'] ?: '·' }}</td>
                                    <td class="px-2 py-2 text-right text-[color:var(--nx-muted)]">{{ ($r['issues'] + $r['tickets']) ?: '·' }}</td>
                                    <td class="px-2 py-2 text-right text-[color:var(--nx-muted)]">{{ $r['cases_done'] ?: '·' }}</td>
                                    <td class="px-2 py-2 text-right text-[color:var(--nx-muted)]">{{ $r['requests'] ?: '·' }}</td>
                                    <td class="px-2 py-2 text-right {{ $neg > 0 ? 'text-[color:var(--nx-warning)]' : 'text-[color:var(--nx-muted)]' }}">{{ $neg ?: '·' }}</td>
                                    <td class="px-2 py-2 text-right {{ $r['corrections'] > 0 ? 'text-[color:var(--nx-warning)]' : 'text-[color:var(--nx-muted)]' }}">{{ $r['corrections'] ?: '·' }}</td>
                                    <td class="px-2 py-2 text-right text-[color:var(--nx-muted)]">{{ $fmtDur($r['reaction_median_s']) }}</td>
                                    <td class="px-2 py-2 text-right text-[color:var(--nx-muted)]">{{ $r['hours'] > 0 ? number_format($r['hours'], 1, ',', '.') . ' h' : '·' }}</td>
                                    <td class="px-2 py-2 text-right text-[color:var(--nx-muted)]">{{ $isCurrent ? $fmtNum($r['tokens_per_result']) : '–' }}</td>
                                    <td class="px-4 py-2">
                                        <div class="ml-auto flex h-6 w-24 items-end justify-end gap-[2px]" title="{{ implode(' · ', $series) }}">
                                            @foreach ($series as $i => $v)
                                                <span class="w-[9px] rounded-[2px] {{ $i === count($series) - 1 ? 'bg-[color:var(--nx-accent,#2563eb)]' : 'bg-[color:var(--nx-line)]' }}" style="height: {{ $v > 0 ? max(12, (int) round($v / $max * 100)) : 4 }}%"></span>
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="12" class="px-4 py-6 text-center text-[color:var(--nx-muted)]">Keine Agenten mit verknüpftem Bot-User.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-nx-panel>

            <p class="m-0 max-w-[680px] text-xs text-[color:var(--nx-faint)]">
                So gelesen: „Ergebnisse“ zählt nur Abgeschlossenes, nicht Aktivität. „Abgelehnt/verworfen“ und „Korrekturen“ zeigen, wo Menschen nachbessern mussten. Token/Ergebnis gibt es nur für den laufenden Zeitraum (das Gehirn meldet ein gleitendes 7-Tage-Fenster). Wie gesund die Gehirne selbst arbeiten, zeigt die Leitwarte.
            </p>
        </div>
    </x-ui-page-container>
</x-ui-page>
