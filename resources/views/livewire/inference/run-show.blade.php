@php
    $statusVariant = match ($run->status) { 'completed' => 'success', 'failed' => 'danger', 'running' => 'warning', default => 'neutral' };
    $statusLabel = match ($run->status) { 'running' => 'Läuft', 'completed' => 'Fertig', 'failed' => 'Fehler', default => ucfirst((string) $run->status) };

    $dauer = '–';
    if ($run->duration_ms > 0) {
        $dauer = match (true) {
            $run->duration_ms < 1000 => $run->duration_ms . ' ms',
            $run->duration_ms < 60000 => number_format($run->duration_ms / 1000, 1, ',', '') . ' s',
            default => intdiv($run->duration_ms, 60000) . 'm ' . intdiv($run->duration_ms % 60000, 1000) . 's',
        };
    }

    $json = fn ($v) => json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $hatLlm = $run->llm_model || $run->token_usage;
    $cost = $hatLlm ? $run->costBreakdown() : null;
    $cacheRead = (int) ($run->token_usage['cache_read_input_tokens'] ?? 0);
    $cacheWrite = (int) ($run->token_usage['cache_creation_input_tokens'] ?? 0);
    $stepsByPrompt = $run->steps->groupBy('inference_prompt_id');
    $pre = 'overflow-x-auto whitespace-pre-wrap break-words rounded-[6px] bg-[color:var(--nx-bg)] p-2 font-mono text-xs text-[color:var(--nx-text)]';
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="'Lauf #' . $run->id" icon="heroicon-o-cpu-chip" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organisation', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Inference-Läufe', 'href' => route('organization.inference-runs.index')],
            ['label' => '#' . $run->id],
        ]" />
    </x-slot>

    <x-ui-page-container width="contained">
        <div class="space-y-5">
            {{-- Kopf: Identität + Zustand --}}
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="m-0 text-2xl font-semibold tracking-tight text-[color:var(--nx-text)]">Inference-Lauf #{{ $run->id }}</h1>
                    <x-nx-badge :variant="$statusVariant" dot>{{ $statusLabel }}</x-nx-badge>
                    <span class="ml-auto text-xs text-[color:var(--nx-faint)]" title="{{ $run->created_at->format('d.m.Y H:i:s') }}">gestartet {{ $run->created_at->diffForHumans() }}</span>
                </div>
                <div class="mt-1 break-all font-mono text-xs text-[color:var(--nx-faint)]">{{ $run->uuid }}</div>
            </div>

            @if ($run->isFailed() && $run->error_message)
                <x-nx-callout variant="danger" title="Fehler">
                    <pre class="mt-1 whitespace-pre-wrap break-words font-mono text-xs">{{ $run->error_message }}</pre>
                </x-nx-callout>
            @endif

            @if ($run->isRunning())
                <x-nx-callout variant="warning" icon="heroicon-o-arrow-path">
                    Lauf läuft noch — letztes Lebenszeichen {{ $run->updated_at->diffForHumans() }}.
                </x-nx-callout>
            @endif

            <x-nx-panel title="Eckdaten">
                <div class="grid grid-cols-1 gap-x-6 md:grid-cols-2">
                    <x-nx-property-row icon="heroicon-o-bolt" label="Trigger-Typ">{{ $run->trigger_type ?: '–' }}</x-nx-property-row>
                    <x-nx-property-row icon="heroicon-o-clock" label="Dauer"><span class="tabular-nums">{{ $dauer }}</span></x-nx-property-row>
                    <x-nx-property-row icon="heroicon-o-play" label="Gestartet">{{ $run->created_at->format('d.m.Y H:i:s') }}</x-nx-property-row>
                    <x-nx-property-row icon="heroicon-o-arrow-path" label="Aktualisiert">
                        <span title="{{ $run->updated_at->format('d.m.Y H:i:s') }}">{{ $run->updated_at->diffForHumans() }}</span>
                    </x-nx-property-row>
                </div>
            </x-nx-panel>

            <x-nx-stat-grid cols="3">
                <x-nx-stat label="Prompts" :value="$run->prompts_evaluated ?? 0" icon="heroicon-o-cpu-chip" />
                <x-nx-stat label="Einheiten" :value="$run->entities_analyzed ?? 0" icon="heroicon-o-building-office" />
                <x-nx-stat label="Signale" :value="$run->signals_created ?? 0" icon="heroicon-o-bell-alert" />
                <x-nx-stat label="Rückfragen" :value="$run->inquiries_created ?? 0" icon="heroicon-o-question-mark-circle" />
                <x-nx-stat label="Memory" :value="$run->memory_updates ?? 0" icon="heroicon-o-circle-stack" />
                <x-nx-stat label="Nichts tun" :value="$run->do_nothing_count ?? 0" icon="heroicon-o-hand-raised" />
            </x-nx-stat-grid>

            @if ($run->summary)
                <x-nx-panel title="Zusammenfassung">
                    <p class="m-0 text-sm text-[color:var(--nx-text)]">{{ $run->summary }}</p>
                </x-nx-panel>
            @endif

            @if ($hatLlm)
                <x-nx-panel title="LLM & Kosten">
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-x-6 md:grid-cols-2">
                            @if ($run->llm_model)
                                <x-nx-property-row icon="heroicon-o-sparkles" label="Modell"><span class="font-mono">{{ $run->llm_model }}</span></x-nx-property-row>
                            @endif
                            @if ($run->token_usage)
                                <x-nx-property-row icon="heroicon-o-arrow-down-tray" label="Input-Tokens"><span class="tabular-nums">{{ number_format($run->token_usage['input_tokens'] ?? 0, 0, ',', '.') }}</span></x-nx-property-row>
                                <x-nx-property-row icon="heroicon-o-arrow-up-tray" label="Output-Tokens"><span class="tabular-nums">{{ number_format($run->token_usage['output_tokens'] ?? 0, 0, ',', '.') }}</span></x-nx-property-row>
                                @if ($cacheRead > 0 || $cacheWrite > 0)
                                    <x-nx-property-row icon="heroicon-o-circle-stack" label="Cache (lesen / schreiben)">
                                        <span class="tabular-nums">{{ number_format($cacheRead, 0, ',', '.') }} / {{ number_format($cacheWrite, 0, ',', '.') }}</span>
                                    </x-nx-property-row>
                                @endif
                            @endif
                        </div>

                        <x-nx-stat-grid cols="4">
                            <x-nx-stat label="Kosten (USD)" :value="'$' . number_format($cost['usd'], 4)" />
                            @if ($cost['eur'] !== null)
                                <x-nx-stat label="Kosten (EUR)" :value="number_format($cost['eur'], 4, ',', '.') . ' €'" />
                            @endif
                            @if ($run->cost_per_signal_usd !== null)
                                <x-nx-stat label="Kosten je Signal" :value="'$' . number_format($run->cost_per_signal_usd, 4)" />
                            @endif
                            <x-nx-stat label="Tokens gesamt" :value="number_format($cost['tokens'], 0, ',', '.')" />
                        </x-nx-stat-grid>

                        @if ($run->token_usage && $cacheRead === 0 && $cacheWrite === 0)
                            <p class="m-0 text-xs text-[color:var(--nx-faint)]">Hinweis: kein Prompt-Caching aktiv — der Input-Kontext wird pro Loop-Iteration voll berechnet (Kostenhebel „B“).</p>
                        @endif
                    </div>
                </x-nx-panel>
            @endif

            @if ($run->trigger)
                <x-nx-panel title="Trigger">
                    <div class="space-y-3">
                        <div class="grid grid-cols-1 gap-x-6 md:grid-cols-2">
                            <x-nx-property-row icon="heroicon-o-bolt" label="Typ">{{ $run->trigger->trigger_type }}</x-nx-property-row>
                            <x-nx-property-row icon="heroicon-o-flag" label="Priorität"><span class="tabular-nums">{{ $run->trigger->priority ?? '–' }}</span></x-nx-property-row>
                            <x-nx-property-row icon="heroicon-o-signal" label="Status">{{ $run->trigger->status ?? '–' }}</x-nx-property-row>
                        </div>
                        @if (! empty($run->trigger->prompt_filter))
                            <div>
                                <div class="mb-1 text-xs font-medium text-[color:var(--nx-muted)]">Prompt-Filter</div>
                                <pre class="{{ $pre }}">{{ $json($run->trigger->prompt_filter) }}</pre>
                            </div>
                        @endif
                    </div>
                </x-nx-panel>
            @endif

            @if ($run->synthesisReports->isNotEmpty() || $run->inquiries->isNotEmpty())
                <x-nx-panel flush title="Erzeugte Ergebnisse">
                    <div class="divide-y divide-[color:var(--nx-line)]">
                        @foreach ($run->synthesisReports as $report)
                            <a href="{{ route('organization.synthesis-reports.show', $report) }}" wire:navigate class="flex items-center gap-3 px-4 py-2.5 transition-colors hover:bg-[color:var(--nx-hover)]">
                                @svg('heroicon-o-document-text', 'w-4 h-4 shrink-0 text-[color:var(--nx-faint)]')
                                <span class="flex-1 truncate text-sm font-medium text-[color:var(--nx-text)]">{{ $report->title }}</span>
                                <x-nx-badge variant="info">{{ ucfirst($report->report_type) }}</x-nx-badge>
                                <x-nx-badge :variant="$report->status === 'published' ? 'success' : 'neutral'" dot>{{ ucfirst($report->status) }}</x-nx-badge>
                            </a>
                        @endforeach
                        @foreach ($run->inquiries as $inquiry)
                            <div class="flex items-center gap-3 px-4 py-2.5">
                                @svg('heroicon-o-question-mark-circle', 'w-4 h-4 shrink-0 text-[color:var(--nx-faint)]')
                                <span class="flex-1 truncate text-sm text-[color:var(--nx-text)]">
                                    {{ $inquiry->inquiry_type ?? 'Rückfrage' }}
                                    @if ($inquiry->context_summary)
                                        <span class="text-[color:var(--nx-muted)]">— {{ \Illuminate\Support\Str::limit($inquiry->context_summary, 80) }}</span>
                                    @endif
                                </span>
                                <x-nx-badge :variant="match ($inquiry->status) { 'completed' => 'success', 'pending' => 'warning', default => 'neutral' }" dot>{{ ucfirst($inquiry->status) }}</x-nx-badge>
                            </div>
                        @endforeach
                    </div>
                </x-nx-panel>
            @endif

            {{-- Step-Drilldown: jeder Tool-Call mit Argumenten und Ergebnis --}}
            @if ($run->steps->isNotEmpty())
                <x-nx-panel flush title="Schritte" :subtitle="$run->steps->count() . ' Tool-Calls'">
                    <div class="divide-y divide-[color:var(--nx-line)]">
                        @foreach ($stepsByPrompt as $promptId => $promptSteps)
                            @php
                                $firstStep = $promptSteps->first();
                            @endphp
                            <div class="px-4 py-3">
                                @if ($firstStep->prompt)
                                    <div class="mb-2 flex items-center gap-2">
                                        <x-nx-badge variant="info">{{ strtoupper(str_replace('_star', '*', $firstStep->prompt->vsm_system)) }}</x-nx-badge>
                                        <span class="text-sm font-medium text-[color:var(--nx-text)]">{{ $firstStep->prompt->name }}</span>
                                        <span class="text-xs text-[color:var(--nx-faint)]">· {{ $promptSteps->count() }} Schritte</span>
                                    </div>
                                @endif

                                <div class="space-y-1" x-data="{ open: {} }">
                                    @foreach ($promptSteps as $step)
                                        @php
                                            $isAssistant = $step->step_type === 'assistant_message';
                                            $assistantText = $isAssistant ? ($step->result['text'] ?? '') : null;
                                        @endphp
                                        <div class="overflow-hidden rounded-[6px] border border-[color:var(--nx-line)] {{ $isAssistant ? 'bg-[color:var(--nx-bg)]' : '' }}">
                                            <button type="button" @click="open[{{ $step->id }}] = !open[{{ $step->id }}]"
                                                class="flex w-full items-start gap-2.5 px-3 py-2 text-left transition-colors hover:bg-[color:var(--nx-hover)]">
                                                <span class="mt-0.5 w-6 shrink-0 font-mono text-[10px] tabular-nums text-[color:var(--nx-faint)]">#{{ $step->step_index }}</span>
                                                @if ($isAssistant)
                                                    @svg('heroicon-o-chat-bubble-left', 'mt-0.5 w-3.5 h-3.5 shrink-0 text-[color:var(--nx-info)]')
                                                    <span class="line-clamp-2 flex-1 text-xs italic text-[color:var(--nx-text)]">{{ \Illuminate\Support\Str::limit($assistantText, 240) }}</span>
                                                @else
                                                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $step->result_ok ? 'bg-[color:var(--nx-success)]' : 'bg-[color:var(--nx-danger)]' }}" title="{{ $step->result_ok ? 'OK' : 'Fehler' }}"></span>
                                                    <span class="mt-0.5 flex-1 truncate font-mono text-xs text-[color:var(--nx-text)]" title="{{ $step->tool_name }}">{{ $step->tool_name }}</span>
                                                @endif
                                                @if ($step->occurred_at)
                                                    <span class="mt-0.5 shrink-0 text-[10px] tabular-nums text-[color:var(--nx-faint)]">{{ $step->occurred_at->format('H:i:s') }}</span>
                                                @endif
                                                <span class="mt-0.5 inline-block transition-transform" :class="open[{{ $step->id }}] ? 'rotate-180' : ''">
                                                    @svg('heroicon-o-chevron-down', 'w-3.5 h-3.5 shrink-0 text-[color:var(--nx-faint)]')
                                                </span>
                                            </button>

                                            <div x-show="open[{{ $step->id }}]" x-cloak class="space-y-3 border-t border-[color:var(--nx-line)] p-3">
                                                @if ($isAssistant)
                                                    <div>
                                                        <div class="mb-1 text-xs font-medium text-[color:var(--nx-muted)]">LLM-Begründung</div>
                                                        <div class="whitespace-pre-wrap text-sm leading-relaxed text-[color:var(--nx-text)]">{{ $assistantText }}</div>
                                                    </div>
                                                @else
                                                    @if ($step->error_message)
                                                        <x-nx-callout variant="danger" title="Fehler">
                                                            <pre class="mt-1 whitespace-pre-wrap break-words font-mono text-xs">{{ $step->error_message }}</pre>
                                                        </x-nx-callout>
                                                    @endif
                                                    @if (! empty($step->arguments))
                                                        <div>
                                                            <div class="mb-1 text-xs font-medium text-[color:var(--nx-muted)]">Argumente</div>
                                                            <pre class="{{ $pre }}">{{ $json($step->arguments) }}</pre>
                                                        </div>
                                                    @endif
                                                    @if (! empty($step->result))
                                                        <div>
                                                            <div class="mb-1 text-xs font-medium text-[color:var(--nx-muted)]">
                                                                Ergebnis
                                                                @if ($step->result['_truncated'] ?? false)
                                                                    <span class="ml-1 text-[color:var(--nx-warning)]">(gekürzt — Original {{ number_format($step->result['_original_bytes'] ?? 0, 0, ',', '.') }} Bytes)</span>
                                                                @endif
                                                            </div>
                                                            <pre class="{{ $pre }}">{{ $json($step->result) }}</pre>
                                                        </div>
                                                    @endif
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-nx-panel>
            @endif
        </div>
    </x-ui-page-container>
</x-ui-page>
