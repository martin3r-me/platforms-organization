<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Inference Runs', 'href' => route('organization.inference-runs.index')],
            ['label' => '#' . $run->id],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="max-w-5xl mx-auto space-y-6">

            {{-- Status + Header --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <div class="flex items-start justify-between gap-4 mb-4">
                    <div class="min-w-0 flex-1">
                        <h1 class="text-xl font-semibold text-[var(--ui-secondary)]">
                            Inference Run #{{ $run->id }}
                        </h1>
                        <p class="text-xs text-[var(--ui-muted)] mt-1 font-mono break-all">{{ $run->uuid }}</p>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        @php($variant = match($run->status) { 'completed' => 'success', 'failed' => 'danger', 'running' => 'warning', default => 'secondary' })
                        <x-ui-badge :variant="$variant">{{ ucfirst($run->status) }}</x-ui-badge>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <div class="text-xs text-[var(--ui-muted)]">Trigger-Typ</div>
                        <div class="font-medium text-[var(--ui-secondary)]">{{ $run->trigger_type ?: '–' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-[var(--ui-muted)]">Dauer</div>
                        <div class="font-medium text-[var(--ui-secondary)] tabular-nums">
                            @if($run->duration_ms > 0)
                                @if($run->duration_ms < 1000)
                                    {{ $run->duration_ms }} ms
                                @elseif($run->duration_ms < 60000)
                                    {{ number_format($run->duration_ms / 1000, 1, ',', '') }} s
                                @else
                                    {{ intdiv($run->duration_ms, 60000) }}m {{ intdiv($run->duration_ms % 60000, 1000) }}s
                                @endif
                            @else
                                –
                            @endif
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-[var(--ui-muted)]">Gestartet</div>
                        <div class="font-medium text-[var(--ui-secondary)]" title="{{ $run->created_at->format('d.m.Y H:i:s') }}">
                            {{ $run->created_at->diffForHumans() }}
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-[var(--ui-muted)]">Aktualisiert</div>
                        <div class="font-medium text-[var(--ui-secondary)]" title="{{ $run->updated_at->format('d.m.Y H:i:s') }}">
                            {{ $run->updated_at->diffForHumans() }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Error (only if failed) --}}
            @if($run->isFailed() && $run->error_message)
                <div class="bg-red-50 rounded-lg border border-red-200 p-6">
                    <div class="flex items-center gap-2 mb-3">
                        @svg('heroicon-o-exclamation-triangle', 'w-5 h-5 text-red-600')
                        <h2 class="text-lg font-semibold text-red-900">Fehler</h2>
                    </div>
                    <pre class="text-xs text-red-900 bg-white border border-red-200 rounded p-3 overflow-x-auto whitespace-pre-wrap font-mono">{{ $run->error_message }}</pre>
                </div>
            @endif

            {{-- Running indicator --}}
            @if($run->isRunning())
                <div class="bg-yellow-50 rounded-lg border border-yellow-200 p-4 flex items-center gap-3">
                    <div class="w-2 h-2 rounded-full bg-yellow-500 animate-pulse"></div>
                    <span class="text-sm text-yellow-900">Run läuft noch — heartbeat: {{ $run->updated_at->diffForHumans() }}</span>
                </div>
            @endif

            {{-- Stats --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Ergebnisse</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                    @foreach([
                        ['Prompts', $run->prompts_evaluated, 'cpu-chip'],
                        ['Entities', $run->entities_analyzed, 'building-office'],
                        ['Signale', $run->signals_created, 'bell-alert'],
                        ['Inquiries', $run->inquiries_created, 'question-mark-circle'],
                        ['Memory', $run->memory_updates, 'circle-stack'],
                        ['Do-Nothing', $run->do_nothing_count, 'hand-raised'],
                    ] as [$label, $value, $icon])
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="flex items-center gap-2 text-xs text-[var(--ui-muted)] mb-1">
                                @svg('heroicon-o-' . $icon, 'w-3.5 h-3.5')
                                <span>{{ $label }}</span>
                            </div>
                            <div class="text-2xl font-bold text-[var(--ui-secondary)] tabular-nums">{{ $value }}</div>
                        </div>
                    @endforeach
                </div>

                @if($run->summary)
                    <div class="mt-4 pt-4 border-t border-[var(--ui-border)]/40">
                        <div class="text-xs text-[var(--ui-muted)] mb-1">Zusammenfassung</div>
                        <p class="text-sm text-[var(--ui-secondary)]">{{ $run->summary }}</p>
                    </div>
                @endif
            </div>

            {{-- LLM Info --}}
            @if($run->llm_model || $run->token_usage)
                <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                    <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">LLM</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                        @if($run->llm_model)
                            <div>
                                <div class="text-xs text-[var(--ui-muted)]">Model</div>
                                <div class="font-mono text-[var(--ui-secondary)]">{{ $run->llm_model }}</div>
                            </div>
                        @endif
                        @if($run->token_usage)
                            <div>
                                <div class="text-xs text-[var(--ui-muted)]">Input Tokens</div>
                                <div class="font-medium tabular-nums text-[var(--ui-secondary)]">{{ number_format($run->token_usage['input_tokens'] ?? 0, 0, ',', '.') }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-[var(--ui-muted)]">Output Tokens</div>
                                <div class="font-medium tabular-nums text-[var(--ui-secondary)]">{{ number_format($run->token_usage['output_tokens'] ?? 0, 0, ',', '.') }}</div>
                            </div>
                        @endif
                    </div>
                    @if($run->getTotalTokens() > 0)
                        <p class="text-xs text-[var(--ui-muted)] mt-3">Total: {{ number_format($run->getTotalTokens(), 0, ',', '.') }} Tokens</p>
                    @endif
                </div>
            @endif

            {{-- Trigger Info --}}
            @if($run->trigger)
                <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                    <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Trigger</h2>
                    <div class="space-y-3 text-sm">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <div class="text-xs text-[var(--ui-muted)]">Trigger-Typ</div>
                                <div class="font-medium text-[var(--ui-secondary)]">{{ $run->trigger->trigger_type }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-[var(--ui-muted)]">Priorität</div>
                                <div class="font-medium tabular-nums text-[var(--ui-secondary)]">{{ $run->trigger->priority ?? '–' }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-[var(--ui-muted)]">Trigger-Status</div>
                                <div class="font-medium text-[var(--ui-secondary)]">{{ $run->trigger->status ?? '–' }}</div>
                            </div>
                        </div>
                        @if(! empty($run->trigger->prompt_filter))
                            <div>
                                <div class="text-xs text-[var(--ui-muted)] mb-1">Prompt-Filter</div>
                                <pre class="text-xs bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 rounded p-3 overflow-x-auto font-mono">{{ json_encode($run->trigger->prompt_filter, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Linked Outputs --}}
            @if($run->synthesisReports->isNotEmpty() || $run->inquiries->isNotEmpty())
                <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                    <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Erzeugte Outputs</h2>
                    <div class="space-y-3">
                        @foreach($run->synthesisReports as $report)
                            <a href="{{ route('organization.synthesis-reports.show', $report) }}" class="flex items-center gap-3 py-2 px-3 rounded-md border border-[var(--ui-border)]/40 hover:border-[var(--ui-primary)]/60 hover:bg-[var(--ui-primary-5)] transition">
                                @svg('heroicon-o-document-text', 'w-4 h-4 text-[var(--ui-muted)]')
                                <span class="text-sm font-medium text-[var(--ui-secondary)] flex-1">{{ $report->title }}</span>
                                <x-ui-badge variant="info">{{ ucfirst($report->report_type) }}</x-ui-badge>
                                <x-ui-badge :variant="$report->status === 'published' ? 'success' : 'muted'">{{ ucfirst($report->status) }}</x-ui-badge>
                            </a>
                        @endforeach
                        @foreach($run->inquiries as $inquiry)
                            <div class="flex items-center gap-3 py-2 px-3 rounded-md border border-[var(--ui-border)]/40">
                                @svg('heroicon-o-question-mark-circle', 'w-4 h-4 text-[var(--ui-muted)]')
                                <span class="text-sm text-[var(--ui-secondary)] flex-1">
                                    {{ $inquiry->inquiry_type ?? 'Inquiry' }}
                                    @if($inquiry->context_summary)
                                        <span class="text-[var(--ui-muted)]">— {{ \Illuminate\Support\Str::limit($inquiry->context_summary, 80) }}</span>
                                    @endif
                                </span>
                                <x-ui-badge :variant="match($inquiry->status) { 'completed' => 'success', 'pending' => 'warning', default => 'muted' }">{{ ucfirst($inquiry->status) }}</x-ui-badge>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Step-Drilldown: jeder Tool-Call mit Argumenten und Ergebnis --}}
            @if($run->steps->isNotEmpty())
                <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-[var(--ui-secondary)]">Steps</h2>
                        <span class="text-xs text-[var(--ui-muted)]">{{ $run->steps->count() }} Tool-Calls</span>
                    </div>

                    @php
                        $stepsByPrompt = $run->steps->groupBy('inference_prompt_id');
                    @endphp

                    <div class="space-y-6">
                        @foreach($stepsByPrompt as $promptId => $promptSteps)
                            @php $firstStep = $promptSteps->first(); @endphp
                            <div>
                                @if($firstStep->prompt)
                                    <div class="flex items-center gap-2 mb-2 pb-2 border-b border-[var(--ui-border)]/40">
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-600/10">{{ strtoupper(str_replace('_star', '*', $firstStep->prompt->vsm_system)) }}</span>
                                        <span class="text-sm font-medium text-[var(--ui-secondary)]">{{ $firstStep->prompt->name }}</span>
                                        <span class="text-xs text-[var(--ui-muted)]">· {{ $promptSteps->count() }} Steps</span>
                                    </div>
                                @endif

                                <div class="space-y-1.5" x-data="{ open: {} }">
                                    @foreach($promptSteps as $step)
                                        @php
                                            $isAssistant = $step->step_type === 'assistant_message';
                                            $assistantText = $isAssistant ? ($step->result['text'] ?? '') : null;
                                        @endphp
                                        <div class="border {{ $isAssistant ? 'border-indigo-200 bg-indigo-50/30' : 'border-[var(--ui-border)]/40' }} rounded-md overflow-hidden">
                                            <button
                                                type="button"
                                                @click="open[{{ $step->id }}] = !open[{{ $step->id }}]"
                                                class="w-full flex items-start gap-2.5 px-3 py-2 hover:bg-[var(--ui-muted-5)]/40 transition-colors text-left"
                                            >
                                                <span class="text-[10px] font-mono text-[var(--ui-muted)] w-6 tabular-nums flex-shrink-0 mt-0.5">#{{ $step->step_index }}</span>
                                                @if($isAssistant)
                                                    @svg('heroicon-o-chat-bubble-left', 'w-3.5 h-3.5 text-indigo-700 flex-shrink-0 mt-0.5')
                                                @elseif($step->result_ok)
                                                    <span class="w-2 h-2 rounded-full bg-emerald-500 flex-shrink-0 mt-1.5" title="OK"></span>
                                                @else
                                                    <span class="w-2 h-2 rounded-full bg-red-500 flex-shrink-0 mt-1.5" title="Fehler"></span>
                                                @endif

                                                @if($isAssistant)
                                                    <span class="text-xs text-[var(--ui-secondary)] flex-1 line-clamp-2 italic">{{ \Illuminate\Support\Str::limit($assistantText, 240) }}</span>
                                                @else
                                                    <span class="text-xs font-mono text-[var(--ui-secondary)] truncate flex-1 mt-0.5" title="{{ $step->tool_name }}">{{ $step->tool_name }}</span>
                                                @endif

                                                @if($step->occurred_at)
                                                    <span class="text-[10px] text-[var(--ui-muted)] flex-shrink-0 mt-0.5">{{ $step->occurred_at->format('H:i:s') }}</span>
                                                @endif
                                                @svg('heroicon-o-chevron-down', 'w-3.5 h-3.5 text-[var(--ui-muted)] flex-shrink-0 transition-transform mt-0.5', ['x-bind:class' => "open[{$step->id}] ? 'rotate-180' : ''"])
                                            </button>

                                            <div x-show="open[{{ $step->id }}]" x-cloak class="border-t {{ $isAssistant ? 'border-indigo-200' : 'border-[var(--ui-border)]/40' }}">
                                                @if($isAssistant)
                                                    <div class="p-3">
                                                        <div class="text-[10px] font-bold text-indigo-900 uppercase tracking-wider mb-1">LLM-Reasoning</div>
                                                        <div class="text-sm text-[var(--ui-secondary)] whitespace-pre-wrap leading-relaxed">{{ $assistantText }}</div>
                                                    </div>
                                                @else
                                                    @if($step->error_message)
                                                        <div class="p-3 bg-red-50 border-b border-red-200">
                                                            <div class="text-[10px] font-bold text-red-900 uppercase tracking-wider mb-1">Fehler</div>
                                                            <pre class="text-xs text-red-900 font-mono whitespace-pre-wrap break-words">{{ $step->error_message }}</pre>
                                                        </div>
                                                    @endif

                                                    @if(!empty($step->arguments))
                                                        <div class="p-3 border-b border-[var(--ui-border)]/30">
                                                            <div class="text-[10px] font-bold text-[var(--ui-muted)] uppercase tracking-wider mb-1">Arguments</div>
                                                            <pre class="text-xs text-[var(--ui-secondary)] bg-[var(--ui-muted-5)]/40 rounded p-2 overflow-x-auto font-mono whitespace-pre-wrap break-words">{{ json_encode($step->arguments, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                                        </div>
                                                    @endif

                                                    @if(!empty($step->result))
                                                        <div class="p-3">
                                                            <div class="text-[10px] font-bold text-[var(--ui-muted)] uppercase tracking-wider mb-1">
                                                                Result
                                                                @if(($step->result['_truncated'] ?? false))
                                                                    <span class="text-amber-700 ml-1">(gekuerzt — original {{ number_format($step->result['_original_bytes'] ?? 0, 0, ',', '.') }} Bytes)</span>
                                                                @endif
                                                            </div>
                                                            <pre class="text-xs text-[var(--ui-secondary)] bg-[var(--ui-muted-5)]/40 rounded p-2 overflow-x-auto font-mono whitespace-pre-wrap break-words">{{ json_encode($step->result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
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
                </div>
            @endif
        </div>
    </x-ui-page-container>
</x-ui-page>
