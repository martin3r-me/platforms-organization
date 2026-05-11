<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Prozesse', 'href' => route('organization.processes.index')],
            ['label' => $process->name],
        ]">
            <div class="flex-1"></div>

            @if($this->isDirty)
                <x-ui-button variant="secondary-ghost" size="sm" wire:click="loadForm">
                    @svg('heroicon-o-x-mark', 'w-4 h-4')
                    <span>Abbrechen</span>
                </x-ui-button>
                <x-ui-button variant="primary" size="sm" wire:click="save">
                    @svg('heroicon-o-check', 'w-4 h-4')
                    <span>Speichern</span>
                </x-ui-button>
            @endif

            <x-ui-button variant="primary" size="sm" wire:click="startRun">
                @svg('heroicon-o-play', 'w-4 h-4')
                <span>Durchlauf starten</span>
                @if($this->activeRunCount > 0)
                    <x-ui-badge variant="warning" size="sm" class="ml-1">{{ $this->activeRunCount }}</x-ui-badge>
                @endif
            </x-ui-button>

            {{-- Ausweis Split Button --}}
            <div x-data="{ open: false }" class="relative inline-flex">
                <x-ui-button variant="secondary-outline" size="sm" wire:click="$set('activeTab', 'certificate')" class="rounded-r-none border-r-0">
                    @svg('heroicon-o-identification', 'w-4 h-4')
                    <span>Ausweis</span>
                </x-ui-button>
                <button
                    @click="open = !open"
                    class="inline-flex items-center px-1.5 border border-[var(--ui-border)] rounded-r-md hover:bg-[var(--ui-muted-5)] transition-colors"
                >
                    @svg('heroicon-o-chevron-down', 'w-3 h-3 text-[var(--ui-secondary)]')
                </button>
                <div
                    x-show="open"
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    @click.outside="open = false"
                    class="absolute right-0 mt-1 w-56 rounded-lg bg-[var(--ui-surface)] shadow-lg ring-1 ring-[var(--ui-border)] z-50 py-1 top-full"
                    style="display: none;"
                >
                    <a href="{{ route('organization.processes.certificate-pdf', $process) }}"
                       class="flex items-center gap-2 px-3 py-2 text-sm text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition-colors">
                        @svg('heroicon-o-arrow-down-tray', 'w-4 h-4 text-[var(--ui-muted)]')
                        PDF herunterladen
                    </a>
                    <div class="border-t border-[var(--ui-border)]/40 my-1"></div>
                    @if($process->public_token && $process->public_token_expires_at?->isFuture())
                        <button
                            @click="navigator.clipboard.writeText('{{ route('organization.certificate.public', $process->public_token) }}'); $wire.dispatch('toast', {message: 'Link kopiert!'}); open = false"
                            class="flex items-center gap-2 w-full px-3 py-2 text-sm text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition-colors text-left"
                        >
                            @svg('heroicon-o-clipboard-document', 'w-4 h-4 text-green-500')
                            Link kopieren
                        </button>
                        <div class="px-3 py-1">
                            <span class="text-[10px] text-[var(--ui-muted)]">Gültig bis {{ $process->public_token_expires_at->format('d.m.Y') }}</span>
                        </div>
                        <button
                            wire:click="revokePublicLink"
                            wire:confirm="Link wirklich widerrufen?"
                            @click="open = false"
                            class="flex items-center gap-2 w-full px-3 py-2 text-sm text-[var(--ui-danger)] hover:bg-[var(--ui-muted-5)] transition-colors text-left"
                        >
                            @svg('heroicon-o-x-mark', 'w-4 h-4')
                            Link widerrufen
                        </button>
                    @else
                        <button
                            wire:click="generatePublicLink"
                            @click="open = false"
                            class="flex items-center gap-2 w-full px-3 py-2 text-sm text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition-colors text-left"
                        >
                            @svg('heroicon-o-link', 'w-4 h-4 text-[var(--ui-muted)]')
                            Öffentlichen Link erstellen
                        </button>
                    @endif
                </div>
            </div>

            <x-ui-confirm-button
                variant="danger-outline"
                size="sm"
                wire:click="delete"
                confirm-text="Prozess wirklich löschen?"
            >
                @svg('heroicon-o-trash', 'w-4 h-4')
            </x-ui-confirm-button>
        </x-ui-page-actionbar>
        @php
            $tabItems = [
                ['value' => 'details', 'label' => 'Details'],
                ['value' => 'corefit', 'label' => 'COREFIT'],
                ['value' => 'steps', 'label' => 'Steps', 'count' => $this->steps->count()],
                ['value' => 'flows', 'label' => 'Flows', 'count' => $this->flows->count()],
                ['value' => 'triggers', 'label' => 'Triggers', 'count' => $this->triggers->count()],
                ['value' => 'outputs', 'label' => 'Outputs', 'count' => $this->outputs->count()],
                ['value' => 'improvements', 'label' => 'Verbesserungen', 'count' => $this->processImprovements->count()],
                ['value' => 'runs', 'label' => 'Durchläufe', 'count' => $this->allRuns->count()],
                ['value' => 'snapshots', 'label' => 'Snapshots', 'count' => $this->processSnapshots->count()],
                ['value' => 'certificate', 'label' => 'Ausweis'],
            ];
        @endphp
        <div class="px-4 py-2 bg-[var(--ui-surface)] border-b border-[var(--ui-border)]/40">
            <x-ui-tab :tabs="$tabItems" model="activeTab" :showCounts="true" size="xs" />
        </div>
        @if($this->chains->isNotEmpty())
            <div class="px-4 py-2 bg-[var(--ui-info)]/5 border-b border-[var(--ui-border)]/40 flex items-center gap-2 flex-wrap">
                @svg('heroicon-o-link', 'w-4 h-4 text-[var(--ui-info)]')
                <span class="text-xs text-[var(--ui-muted)]">Teil von:</span>
                @foreach($this->chains as $chain)
                    <x-ui-badge variant="info" size="sm">
                        {{ $chain->name }}
                        @if($chain->pivot?->role && $chain->pivot->role !== 'middle')
                            <span class="text-[10px] opacity-70 ml-1">({{ $chain->pivot->role }})</span>
                        @endif
                    </x-ui-badge>
                @endforeach
            </div>
        @endif
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Informationen" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Status</h3>
                    <div class="space-y-2">
                        @if($process->status === 'active')
                            <x-ui-badge variant="success" size="sm">Aktiv</x-ui-badge>
                        @elseif($process->status === 'pilot')
                            <x-ui-badge variant="info" size="sm">Pilot</x-ui-badge>
                        @elseif($process->status === 'under_review')
                            <x-ui-badge variant="warning" size="sm">In Prüfung</x-ui-badge>
                        @elseif($process->status === 'draft')
                            <x-ui-badge variant="muted" size="sm">Entwurf</x-ui-badge>
                        @else
                            <x-ui-badge variant="danger" size="sm">Veraltet</x-ui-badge>
                        @endif
                        @if($process->is_active)
                            <x-ui-badge variant="info" size="sm">Aktiv geschaltet</x-ui-badge>
                        @endif
                    </div>
                </div>
                @php
                    $sidebarSteps = $this->steps;
                    $sidebarTotal = $sidebarSteps->count();
                    $sidebarLlm = $sidebarSteps->whereIn('automation_level', ['llm_assisted', 'llm_autonomous', 'hybrid'])->count();
                    $sidebarLlmQuote = $sidebarTotal > 0 ? round(($sidebarLlm / $sidebarTotal) * 100) : 0;
                @endphp
                @if($sidebarTotal > 0)
                    <div>
                        <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">LLM-Quote</h3>
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs text-[var(--ui-muted)]">{{ $sidebarLlm }} von {{ $sidebarTotal }} Steps</span>
                                <span class="text-lg font-bold {{ $sidebarLlmQuote >= 70 ? 'text-[var(--ui-success)]' : ($sidebarLlmQuote >= 30 ? 'text-[var(--ui-info)]' : 'text-[var(--ui-secondary)]') }}">{{ $sidebarLlmQuote }}%</span>
                            </div>
                            <div class="w-full bg-[var(--ui-muted-20)] rounded-full h-2">
                                <div class="h-2 rounded-full {{ $sidebarLlmQuote >= 70 ? 'bg-[var(--ui-success)]' : ($sidebarLlmQuote >= 30 ? 'bg-[var(--ui-info)]' : 'bg-[var(--ui-muted)]') }}" style="width: {{ $sidebarLlmQuote }}%"></div>
                            </div>
                            @php
                                $autoBreakdown = $this->automationMetrics;
                            @endphp
                            <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-[10px] text-[var(--ui-muted)]">
                                @if($autoBreakdown['llm_autonomous']['count'] > 0)
                                    <span><span class="inline-block w-1.5 h-1.5 rounded-full bg-[var(--ui-success)] mr-0.5"></span>{{ $autoBreakdown['llm_autonomous']['count'] }} autonom</span>
                                @endif
                                @if($autoBreakdown['llm_assisted']['count'] > 0)
                                    <span><span class="inline-block w-1.5 h-1.5 rounded-full bg-[var(--ui-info)] mr-0.5"></span>{{ $autoBreakdown['llm_assisted']['count'] }} assisted</span>
                                @endif
                                @if($autoBreakdown['hybrid']['count'] > 0)
                                    <span><span class="inline-block w-1.5 h-1.5 rounded-full bg-[var(--ui-warning)] mr-0.5"></span>{{ $autoBreakdown['hybrid']['count'] }} hybrid</span>
                                @endif
                                @if($autoBreakdown['human']['count'] > 0)
                                    <span><span class="inline-block w-1.5 h-1.5 rounded-full bg-[var(--ui-muted)] mr-0.5"></span>{{ $autoBreakdown['human']['count'] }} human</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
                @php
                    $sidebarAutoScore = $this->automationScore;
                    $sidebarComplexity = $this->complexityMetrics;
                @endphp
                @if($sidebarAutoScore['score'] !== null)
                    <div>
                        <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Automation-Score</h3>
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-2xl font-bold text-[var(--ui-{{ $sidebarAutoScore['color'] }})]">{{ $sidebarAutoScore['label'] }}</span>
                                <span class="text-sm text-[var(--ui-muted)]">{{ $sidebarAutoScore['score'] }}/100</span>
                            </div>
                            <div class="w-full bg-[var(--ui-muted-20)] rounded-full h-2">
                                <div class="h-2 rounded-full bg-[var(--ui-{{ $sidebarAutoScore['color'] }})]" style="width: {{ $sidebarAutoScore['score'] }}%"></div>
                            </div>
                            <p class="text-[10px] text-[var(--ui-muted)] mt-1.5">Gewichteter Score: Einfach + Human = niedrig, Komplex + Human = OK</p>
                        </div>
                    </div>
                @endif
                @if($sidebarComplexity['count_with'] > 0)
                    <div>
                        <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Komplexität</h3>
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs text-[var(--ui-muted)]">{{ $sidebarComplexity['count_with'] }} von {{ $sidebarComplexity['total'] }} bewertet</span>
                                <span class="text-lg font-bold text-[var(--ui-secondary)]">Ø {{ $sidebarComplexity['avg_label'] }}</span>
                            </div>
                            <div class="flex flex-wrap gap-x-3 gap-y-1 text-[10px] text-[var(--ui-muted)]">
                                @foreach($sidebarComplexity['distribution'] as $key => $dist)
                                    @if($dist['count'] > 0)
                                        <span>{{ $dist['label'] }}: {{ $dist['count'] }}</span>
                                    @endif
                                @endforeach
                            </div>
                            <p class="text-[10px] text-[var(--ui-muted)] mt-1">Ø {{ $sidebarComplexity['avg_points'] }} Punkte · {{ $sidebarComplexity['total_points'] }} Punkte gesamt</p>
                        </div>
                    </div>
                @endif
                @php $sidebarCosts = $this->costMetrics; @endphp
                @if($sidebarCosts['cost_per_run'] > 0)
                    <div>
                        <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Prozesskosten</h3>
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-[var(--ui-muted)]">Pro Durchlauf</span>
                                <span class="text-sm text-[var(--ui-secondary)]">{{ number_format($sidebarCosts['cost_per_run'], 2, ',', '.') }} &euro;</span>
                            </div>
                            @if($sidebarCosts['external_cost_per_run'] > 0)
                                <div class="flex items-center justify-between text-[10px]">
                                    <span class="text-[var(--ui-muted)]">&nbsp;&nbsp;davon Arbeitszeit</span>
                                    <span class="text-[var(--ui-muted)]">{{ number_format($sidebarCosts['labor_cost_per_run'], 2, ',', '.') }} &euro;</span>
                                </div>
                                <div class="flex items-center justify-between text-[10px]">
                                    <span class="text-[var(--ui-muted)]">&nbsp;&nbsp;davon extern</span>
                                    <span class="text-[var(--ui-muted)]">{{ number_format($sidebarCosts['external_cost_per_run'], 2, ',', '.') }} &euro;</span>
                                </div>
                            @endif
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-[var(--ui-muted)]">Pro Monat</span>
                                <span class="text-sm font-bold text-[var(--ui-secondary)]">{{ number_format($sidebarCosts['cost_per_month'], 2, ',', '.') }} &euro;</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-[var(--ui-muted)]">Pro Jahr</span>
                                <span class="text-base font-bold text-[var(--ui-secondary)]">{{ number_format($sidebarCosts['cost_per_year'], 2, ',', '.') }} &euro;</span>
                            </div>
                            <div class="pt-1 border-t border-[var(--ui-border)]/30">
                                <span class="text-[10px] text-[var(--ui-muted)]">~{{ $sidebarCosts['runs_per_month'] }} Durchläufe/Monat</span>
                            </div>
                        </div>
                    </div>
                @endif
                {{-- Aktive Durchläufe --}}
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider">Aktive Durchläufe</h3>
                        @if($this->activeRunCount > 0)
                            <x-ui-badge variant="warning" size="sm">{{ $this->activeRunCount }}</x-ui-badge>
                        @endif
                    </div>
                    @forelse($this->activeRuns as $aRun)
                        @php
                            $aRunTotal = $aRun->runSteps->count();
                            $aRunDone = $aRun->runSteps->whereIn('status', [\Platform\Organization\Enums\RunStepStatus::COMPLETED, \Platform\Organization\Enums\RunStepStatus::SKIPPED])->count();
                            $aRunPercent = $aRunTotal > 0 ? round(($aRunDone / $aRunTotal) * 100) : 0;
                        @endphp
                        <a
                            href="{{ route('organization.processes.runs.show', [$process, $aRun]) }}"
                            wire:navigate
                            class="block w-full text-left py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40 hover:border-[var(--ui-warning)] transition-colors mb-2"
                        >
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs text-[var(--ui-muted)]">{{ $aRun->started_at->format('d.m.Y H:i') }}</span>
                                <span class="text-xs font-medium text-[var(--ui-secondary)]">{{ $aRunDone }}/{{ $aRunTotal }}</span>
                            </div>
                            <div class="w-full bg-[var(--ui-muted-20)] rounded-full h-1.5 mb-1">
                                <div class="h-1.5 rounded-full bg-[var(--ui-warning)]" style="width: {{ $aRunPercent }}%"></div>
                            </div>
                            @if($aRun->notes)
                                <p class="text-[10px] text-[var(--ui-muted)] truncate mt-1">{{ $aRun->notes }}</p>
                            @endif
                        </a>
                    @empty
                        <p class="text-xs text-[var(--ui-muted)] mb-2">Keine aktiven Durchläufe</p>
                    @endforelse
                    <button
                        type="button"
                        wire:click="startRun"
                        class="w-full py-2 px-4 border-2 border-dashed border-[var(--ui-border)]/60 rounded-lg text-xs text-[var(--ui-muted)] hover:border-[var(--ui-warning)] hover:text-[var(--ui-secondary)] transition-colors flex items-center justify-center gap-1"
                    >
                        @svg('heroicon-o-play', 'w-3.5 h-3.5')
                        Durchlauf starten
                    </button>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Details</h3>
                    <div class="space-y-3">
                        @if($process->code)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">Code</span>
                                <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $process->code }}</div>
                            </div>
                        @endif
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Version</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $process->version ?? 1 }}</div>
                        </div>
                        @if($process->ownerEntity)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">Owner</span>
                                <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $process->ownerEntity->name }}</div>
                            </div>
                        @endif
                        @if($process->vsmSystem)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">VSM System</span>
                                <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $process->vsmSystem->name }}</div>
                            </div>
                        @endif
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Erstellt</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $process->created_at->format('d.m.Y H:i') }}</div>
                        </div>
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Aktualisiert</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $process->updated_at->format('d.m.Y H:i') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-6 text-sm text-[var(--ui-muted)]">Keine Aktivitäten verfügbar</div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container>
        {{-- ── Tab: Details (Dashboard) ────────────────────────────────── --}}
        @if($activeTab === 'details')
            @php
                $metrics = $this->corefitMetrics;
                $autoMetrics = $this->automationMetrics;
                $matrix = $this->efficiencyMatrix;
                $dashSteps = $this->steps;
                $dashTotal = $dashSteps->count();
                $dashLlm = $dashSteps->whereIn('automation_level', ['llm_assisted', 'llm_autonomous', 'hybrid'])->count();
                $dashLlmQuote = $dashTotal > 0 ? round(($dashLlm / $dashTotal) * 100) : 0;

                // Handlungsbedarf from efficiency matrix
                $recommendations = [
                    'core' => ['human' => 'Investieren', 'llm_assisted' => 'Gut', 'llm_autonomous' => 'Optimal', 'hybrid' => 'Gut'],
                    'context' => ['human' => 'Automatisieren', 'llm_assisted' => 'Akzeptabel', 'llm_autonomous' => 'Akzeptabel', 'hybrid' => 'Akzeptabel'],
                    'no_fit' => ['human' => 'Eliminieren', 'llm_assisted' => 'Eliminieren', 'llm_autonomous' => 'Eliminieren', 'hybrid' => 'Eliminieren'],
                ];
                $dashEliminate = 0; $dashAutomate = 0; $dashOptimal = 0;
                foreach ($matrix as $cf => $autos) {
                    foreach ($autos as $al => $cell) {
                        if ($cell['count'] === 0) continue;
                        $rec = $recommendations[$cf][$al] ?? '';
                        if ($rec === 'Eliminieren') $dashEliminate += $cell['count'];
                        elseif ($rec === 'Automatisieren') $dashAutomate += $cell['count'];
                        elseif (in_array($rec, ['Optimal', 'Gut'])) $dashOptimal += $cell['count'];
                    }
                }
            @endphp

            {{-- 1. Grunddaten (kompakt, 2-spaltig) --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-5 mb-6">
                <div class="grid grid-cols-5 gap-4">
                    {{-- Links ~60% --}}
                    <div class="col-span-3 space-y-3">
                        <x-ui-input-text name="name" label="Name" wire:model.live="form.name" required placeholder="Aussagekräftiger Prozessname" />
                        <x-ui-input-text name="code" label="Code" wire:model.live="form.code" placeholder="Optionales Kürzel, z.B. PRO-001" />
                        <x-ui-input-textarea name="description" label="Beschreibung" wire:model.live="form.description" rows="2" placeholder="Kurze Zusammenfassung: Was macht dieser Prozess und warum existiert er?" />
                    </div>
                    {{-- Rechts ~40% --}}
                    <div class="col-span-2 space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <x-ui-input-select
                                name="status"
                                label="Status"
                                :options="[
                                    ['value' => 'draft', 'label' => 'Entwurf'],
                                    ['value' => 'under_review', 'label' => 'In Prüfung'],
                                    ['value' => 'pilot', 'label' => 'Pilot'],
                                    ['value' => 'active', 'label' => 'Aktiv'],
                                    ['value' => 'deprecated', 'label' => 'Veraltet'],
                                ]"
                                wire:model.live="form.status"
                            />
                            <x-ui-input-select
                                name="process_category"
                                label="Kategorie"
                                :options="\Platform\Organization\Enums\ProcessCategory::cases()"
                                optionValue="value"
                                optionLabel="label"
                                wire:model.live="form.process_category"
                                :nullable="true"
                                nullLabel="– Keine Kategorie –"
                            />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <x-ui-input-select
                                name="owner_entity_id"
                                label="Owner"
                                :options="$this->groupedEntityOptions"
                                nullable
                                nullLabel="– Kein Owner –"
                                wire:model.live="form.owner_entity_id"
                            />
                            <x-ui-input-select
                                name="vsm_system_id"
                                label="VSM System"
                                :options="$this->availableVsmSystems->map(fn($v) => ['value' => (string) $v->id, 'label' => $v->name])->toArray()"
                                nullable
                                nullLabel="– Kein VSM System –"
                                wire:model.live="form.vsm_system_id"
                            />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <x-ui-input-text name="version" label="Version" type="number" wire:model.live="form.version" min="1" />
                            <div class="flex items-center pt-6">
                                <input type="checkbox" wire:model.live="form.is_active" id="is_active" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                                <label for="is_active" class="ml-2 text-sm text-[var(--ui-secondary)]">Aktiv geschaltet</label>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" wire:model.live="form.is_focus" class="rounded border-gray-300 text-yellow-500 focus:ring-yellow-500" />
                                <span class="text-sm font-medium text-[var(--ui-secondary)]">Fokus-Prozess</span>
                            </label>
                            @if($form['is_focus'])
                                <div class="grid grid-cols-2 gap-3">
                                    <x-ui-input-textarea name="focus_reason" label="Fokus-Begründung" wire:model.live="form.focus_reason" rows="2" placeholder="Warum im Fokus?" />
                                    <x-ui-input-text type="date" name="focus_until" label="Fokus bis" wire:model.live="form.focus_until" />
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2. KPI-Kacheln (4er-Grid) --}}
            <div class="grid grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-lg border border-[var(--ui-border)] p-4">
                    <div class="flex items-center gap-2 mb-1">
                        @svg('heroicon-o-queue-list', 'w-4 h-4 text-[var(--ui-muted)]')
                        <h3 class="text-sm font-medium text-[var(--ui-muted)]">Steps</h3>
                    </div>
                    <p class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $metrics['total_steps'] }}</p>
                    <p class="text-xs text-[var(--ui-muted)]">Prozessschritte gesamt</p>
                </div>
                <div class="bg-white rounded-lg border border-[var(--ui-border)] p-4">
                    <div class="flex items-center gap-2 mb-1">
                        @svg('heroicon-o-clock', 'w-4 h-4 text-[var(--ui-muted)]')
                        <h3 class="text-sm font-medium text-[var(--ui-muted)]">Durchlaufzeit</h3>
                    </div>
                    <p class="text-2xl font-bold text-[var(--ui-info)]">{{ $metrics['lead_time'] }}</p>
                    <p class="text-xs text-[var(--ui-muted)]">Min. (Bearbeitung + Wartezeit)</p>
                </div>
                <div class="bg-white rounded-lg border border-[var(--ui-border)] p-4">
                    <div class="flex items-center gap-2 mb-1">
                        @svg('heroicon-o-bolt', 'w-4 h-4 text-[var(--ui-muted)]')
                        <h3 class="text-sm font-medium text-[var(--ui-muted)]">Effizienz</h3>
                    </div>
                    <p class="text-2xl font-bold {{ $metrics['efficiency'] >= 70 ? 'text-[var(--ui-success)]' : ($metrics['efficiency'] >= 40 ? 'text-[var(--ui-warning)]' : 'text-[var(--ui-danger)]') }}">{{ $metrics['efficiency'] }}%</p>
                    <p class="text-xs text-[var(--ui-muted)]">Anteil aktiver Arbeit</p>
                </div>
                <div class="bg-white rounded-lg border border-[var(--ui-border)] p-4">
                    <div class="flex items-center gap-2 mb-1">
                        @svg('heroicon-o-cpu-chip', 'w-4 h-4 text-[var(--ui-muted)]')
                        <h3 class="text-sm font-medium text-[var(--ui-muted)]">LLM-Quote</h3>
                    </div>
                    <p class="text-2xl font-bold {{ $dashLlmQuote >= 70 ? 'text-[var(--ui-success)]' : ($dashLlmQuote >= 30 ? 'text-[var(--ui-info)]' : 'text-[var(--ui-secondary)]') }}">{{ $dashLlmQuote }}%</p>
                    <p class="text-xs text-[var(--ui-muted)]">{{ $dashLlm }} von {{ $dashTotal }} Steps</p>
                </div>
            </div>

            {{-- 3. Zwei-Spalten: COREFIT Mini + Steps Preview --}}
            <div class="grid grid-cols-2 gap-6 mb-6">
                {{-- Links: COREFIT Mini --}}
                <div class="bg-white rounded-lg border border-[var(--ui-border)] p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-semibold text-[var(--ui-secondary)]">COREFIT-Verteilung</h3>
                        <button wire:click="$set('activeTab', 'corefit')" class="text-xs text-[var(--ui-info)] hover:underline">Analyse öffnen</button>
                    </div>

                    @if($metrics['total_steps'] > 0)
                        <div class="space-y-3 mb-4">
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-[var(--ui-secondary)]">Core <span class="text-[var(--ui-muted)] font-normal">({{ $metrics['core']['count'] }})</span></span>
                                    <span class="font-medium text-[var(--ui-secondary)]">{{ $metrics['core']['percent'] }}%</span>
                                </div>
                                <div class="w-full bg-[var(--ui-muted-20)] rounded-full h-2">
                                    <div class="bg-[var(--ui-success)] h-2 rounded-full" style="width: {{ min(100, $metrics['core']['percent']) }}%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-[var(--ui-secondary)]">Context <span class="text-[var(--ui-muted)] font-normal">({{ $metrics['context']['count'] }})</span></span>
                                    <span class="font-medium text-[var(--ui-secondary)]">{{ $metrics['context']['percent'] }}%</span>
                                </div>
                                <div class="w-full bg-[var(--ui-muted-20)] rounded-full h-2">
                                    <div class="bg-[var(--ui-warning)] h-2 rounded-full" style="width: {{ min(100, $metrics['context']['percent']) }}%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-[var(--ui-secondary)]">not Fit <span class="text-[var(--ui-muted)] font-normal">({{ $metrics['no_fit']['count'] }})</span></span>
                                    <span class="font-medium text-[var(--ui-secondary)]">{{ $metrics['no_fit']['percent'] }}%</span>
                                </div>
                                <div class="w-full bg-[var(--ui-muted-20)] rounded-full h-2">
                                    <div class="bg-[var(--ui-danger)] h-2 rounded-full" style="width: {{ min(100, $metrics['no_fit']['percent']) }}%"></div>
                                </div>
                            </div>
                        </div>

                        {{-- Handlungsbedarf --}}
                        <div class="pt-3 border-t border-[var(--ui-border)]/40">
                            <h4 class="text-xs font-semibold text-[var(--ui-secondary)] uppercase tracking-wider mb-2">Handlungsbedarf</h4>
                            <div class="flex flex-wrap gap-2">
                                @if($dashEliminate > 0)
                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-red-50 border border-red-200 text-xs font-medium text-red-700">
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>{{ $dashEliminate }} eliminieren
                                    </span>
                                @endif
                                @if($dashAutomate > 0)
                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-orange-50 border border-orange-200 text-xs font-medium text-orange-700">
                                        <span class="w-1.5 h-1.5 rounded-full bg-orange-500"></span>{{ $dashAutomate }} automatisieren
                                    </span>
                                @endif
                                @if($dashOptimal > 0)
                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-green-50 border border-green-200 text-xs font-medium text-green-700">
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-600"></span>{{ $dashOptimal }} optimal/gut
                                    </span>
                                @endif
                            </div>
                        </div>
                    @else
                        <p class="text-sm text-[var(--ui-muted)]">Keine Schritte vorhanden. Erst Steps anlegen, um die COREFIT-Verteilung zu sehen.</p>
                    @endif
                </div>

                {{-- Rechts: Steps Preview --}}
                <div class="bg-white rounded-lg border border-[var(--ui-border)] p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-semibold text-[var(--ui-secondary)]">Prozessschritte</h3>
                        @if($dashTotal > 0)
                            <button wire:click="$set('activeTab', 'steps')" class="text-xs text-[var(--ui-info)] hover:underline">Alle {{ $dashTotal }} Steps</button>
                        @endif
                    </div>

                    @if($dashTotal > 0)
                        <div class="space-y-1.5">
                            @foreach($dashSteps->take(8) as $step)
                                <div class="flex items-center gap-2 py-1.5 px-2 rounded hover:bg-[var(--ui-muted-5)]">
                                    <span class="text-xs font-mono text-[var(--ui-muted)] w-5 text-right">{{ $step->position }}</span>
                                    <span class="text-sm text-[var(--ui-secondary)] flex-1 truncate">{{ $step->name }}</span>
                                    {{-- CoreFit Badge --}}
                                    @if($step->corefit_classification?->value === 'core')
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-green-50 text-green-700 border border-green-200">Core</span>
                                    @elseif($step->corefit_classification?->value === 'context')
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-yellow-50 text-yellow-700 border border-yellow-200">Ctx</span>
                                    @else
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-50 text-red-700 border border-red-200">NF</span>
                                    @endif
                                    {{-- Automation Badge --}}
                                    @if($step->automation_level?->value === 'llm_autonomous')
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-green-50 text-green-700 border border-green-200">LLM</span>
                                    @elseif($step->automation_level?->value === 'llm_assisted')
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-50 text-blue-700 border border-blue-200">Asst</span>
                                    @elseif($step->automation_level?->value === 'hybrid')
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-yellow-50 text-yellow-700 border border-yellow-200">Hyb</span>
                                    @else
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-50 text-gray-600 border border-gray-200">H</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        @if($dashTotal > 8)
                            <div class="mt-2 pt-2 border-t border-[var(--ui-border)]/40">
                                <button wire:click="$set('activeTab', 'steps')" class="text-xs text-[var(--ui-info)] hover:underline">+ {{ $dashTotal - 8 }} weitere Steps anzeigen</button>
                            </div>
                        @endif
                    @else
                        <div class="text-center py-6">
                            <p class="text-sm text-[var(--ui-muted)] mb-2">Noch keine Schritte vorhanden.</p>
                            <button wire:click="$set('activeTab', 'steps')" class="text-sm text-[var(--ui-info)] hover:underline font-medium">Jetzt anlegen</button>
                        </div>
                    @endif
                </div>
            </div>

            {{-- 4. Quick-Links (3er-Grid) --}}
            <div class="grid grid-cols-3 gap-4">
                <button wire:click="$set('activeTab', 'improvements')" class="bg-white rounded-lg border border-[var(--ui-border)] p-4 text-left hover:border-[var(--ui-info)] transition-colors">
                    <div class="flex items-center gap-2 mb-2">
                        @svg('heroicon-o-light-bulb', 'w-4 h-4 text-[var(--ui-warning)]')
                        <h3 class="text-sm font-semibold text-[var(--ui-secondary)]">Verbesserungen</h3>
                    </div>
                    @php $improvementCount = $this->processImprovements->count(); @endphp
                    <p class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $improvementCount }}</p>
                    <p class="text-xs text-[var(--ui-muted)]">{{ $improvementCount === 1 ? 'Verbesserung' : 'Verbesserungen' }} erfasst</p>
                </button>
                <button wire:click="$set('activeTab', 'snapshots')" class="bg-white rounded-lg border border-[var(--ui-border)] p-4 text-left hover:border-[var(--ui-info)] transition-colors">
                    <div class="flex items-center gap-2 mb-2">
                        @svg('heroicon-o-camera', 'w-4 h-4 text-[var(--ui-info)]')
                        <h3 class="text-sm font-semibold text-[var(--ui-secondary)]">Snapshots</h3>
                    </div>
                    @php $snapshotCount = $this->processSnapshots->count(); @endphp
                    <p class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $snapshotCount }}</p>
                    <p class="text-xs text-[var(--ui-muted)]">{{ $snapshotCount === 1 ? 'Version' : 'Versionen' }} gespeichert</p>
                </button>
                <button wire:click="$set('activeTab', 'flows')" class="bg-white rounded-lg border border-[var(--ui-border)] p-4 text-left hover:border-[var(--ui-info)] transition-colors">
                    <div class="flex items-center gap-2 mb-2">
                        @svg('heroicon-o-arrows-right-left', 'w-4 h-4 text-[var(--ui-success)]')
                        <h3 class="text-sm font-semibold text-[var(--ui-secondary)]">Flows</h3>
                    </div>
                    @php $flowCount = $this->flows->count(); @endphp
                    <p class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $flowCount }}</p>
                    <p class="text-xs text-[var(--ui-muted)]">{{ $flowCount === 1 ? 'Verbindung' : 'Verbindungen' }}</p>
                </button>
            </div>
        @endif

        {{-- ── Tab: COREFIT ────────────────────────────────── --}}
        @if($activeTab === 'corefit')
            {{-- View Mode Toggle --}}
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <button
                        wire:click="closeCorefitWorkshop"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md transition-colors {{ $corefitViewMode === 'list' ? 'bg-[var(--ui-primary)] text-white' : 'bg-[var(--ui-muted-10)] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-20)]' }}"
                    >
                        @svg('heroicon-o-list-bullet', 'w-3.5 h-3.5')
                        Liste
                    </button>
                    <button
                        wire:click="openCorefitWorkshop"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md transition-colors {{ $corefitViewMode === 'workshop' ? 'bg-[var(--ui-primary)] text-white' : 'bg-[var(--ui-muted-10)] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-20)]' }}"
                    >
                        @svg('heroicon-o-squares-2x2', 'w-3.5 h-3.5')
                        Workshop
                    </button>
                </div>
            </div>

            @if($corefitViewMode === 'workshop')
                {{-- ═══ COREFIT WORKSHOP VIEW (using workshopBoard from core) ═══ --}}
                @php
                    $wsBlockDefs = $this->workshopBlockDefs;
                    $wsNotes = $this->getWorkshopNotes();
                    $wGridCols = 3;
                    $wGridRows = 3;
                    $wsMeta = $process->metadata ?? [];
                    $ws = $wsMeta['workshop_settings'] ?? [];
                    $wGridW = (int) ($ws['gridWidth'] ?? max(1200, $wGridCols * 280));
                    $wGridH = (int) ($ws['gridHeight'] ?? max(800, $wGridRows * 280));
                    $wBoardW = 5000;
                    $wBoardH = 3000;
                    $wGridLeft = intval(($wBoardW - $wGridW) / 2);
                    $wGridTop = intval(($wBoardH - $wGridH) / 2);

                    $wsLayout = ['type' => 'grid', 'columns' => $wGridCols, 'rows' => $wGridRows];

                    $wsIconMap = [
                        'target_description' => 'heroicon-o-flag',
                        'value_proposition' => 'heroicon-o-gift',
                        'cost_analysis' => 'heroicon-o-calculator',
                        'risk_assessment' => 'heroicon-o-shield-exclamation',
                        'improvement_levers' => 'heroicon-o-wrench-screwdriver',
                        'action_plan' => 'heroicon-o-clipboard-document-check',
                        'standardization_notes' => 'heroicon-o-document-check',
                        'process_landscape' => 'heroicon-o-map',
                        'corefit_classification_notes' => 'heroicon-o-adjustments-horizontal',
                    ];
                @endphp

                <div wire:key="corefit-workshop"
                     wire:ignore
                     x-data="workshopBoard({
                        notes: {{ Js::from($wsNotes) }},
                        canvasBlocks: {{ Js::from(collect($wsBlockDefs)->map(fn($d) => ['key' => $d['key'], 'label' => $d['label'] ?? $d['key'], 'id' => null])->values()) }},
                        gridLayout: {{ Js::from($wsLayout) }}
                     })"
                     class="relative overflow-hidden"
                     :class="isFullscreen ? 'workshop-fullscreen' : 'h-[calc(100vh-220px)]'"
                     style="background: #eef0f4; border-radius: 8px;"
                >
                    {{-- Zoom Controls --}}
                    <div class="workshop-zoom-controls">
                        <button x-on:click="zoomIn()" title="Zoom In">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
                            </svg>
                        </button>
                        <div class="zoom-level" x-text="Math.round(scale * 100) + '%'"></div>
                        <button x-on:click="zoomOut()" title="Zoom Out">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                <path fill-rule="evenodd" d="M4 10a.75.75 0 01.75-.75h10.5a.75.75 0 010 1.5H4.75A.75.75 0 014 10z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <button x-on:click="resetZoom()" title="Reset Zoom" class="text-[10px] font-bold">1:1</button>
                        <button x-on:click="fitToScreen()" title="An Bildschirm anpassen">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                <path fill-rule="evenodd" d="M4.25 2A2.25 2.25 0 002 4.25v2.5a.75.75 0 001.5 0v-2.5a.75.75 0 01.75-.75h2.5a.75.75 0 000-1.5h-2.5zM13.25 2a.75.75 0 000 1.5h2.5a.75.75 0 01.75.75v2.5a.75.75 0 001.5 0v-2.5A2.25 2.25 0 0015.75 2h-2.5zM3.5 13.25a.75.75 0 00-1.5 0v2.5A2.25 2.25 0 004.25 18h2.5a.75.75 0 000-1.5h-2.5a.75.75 0 01-.75-.75v-2.5zM18 13.25a.75.75 0 00-1.5 0v2.5a.75.75 0 01-.75.75h-2.5a.75.75 0 000 1.5h2.5A2.25 2.25 0 0018 15.75v-2.5z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <button x-on:click="toggleFullscreen()" title="Vollbild">
                            <template x-if="!isFullscreen">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                    <path d="M3.28 2.22a.75.75 0 00-1.06 1.06L5.44 6.5H2.75a.75.75 0 000 1.5h4.5A.75.75 0 008 7.25v-4.5a.75.75 0 00-1.5 0v2.69L3.28 2.22zM16.72 2.22a.75.75 0 010 1.06L13.56 6.5h2.69a.75.75 0 010 1.5h-4.5A.75.75 0 0111 7.25v-4.5a.75.75 0 011.5 0v2.69l3.22-3.22a.75.75 0 011.06 0zM3.28 17.78a.75.75 0 001.06 0L7.56 14.5h-2.69a.75.75 0 010-1.5h4.5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-2.69l-3.22 3.22a.75.75 0 01-1.06-1.06zM16.72 17.78a.75.75 0 01-1.06 0L12.44 14.5h2.69a.75.75 0 000-1.5h-4.5a.75.75 0 00-.75.75v4.5a.75.75 0 001.5 0v-2.69l3.22 3.22a.75.75 0 001.06-1.06z" />
                                </svg>
                            </template>
                            <template x-if="isFullscreen">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                    <path d="M3.28 2.22a.75.75 0 00-1.06 1.06L5.44 6.5H2.75a.75.75 0 000 1.5h4.5A.75.75 0 008 7.25v-4.5a.75.75 0 00-1.5 0v2.69L3.28 2.22z" />
                                </svg>
                            </template>
                        </button>
                    </div>

                    {{-- Element Toolbar --}}
                    <div class="workshop-toolbar">
                        <button class="workshop-toolbar-btn" x-on:click="addElement('note')" title="Sticky Note">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v12a1 1 0 01-1 1H4a1 1 0 01-1-1V4z"/></svg>
                            <span>Notiz</span>
                        </button>
                        <button class="workshop-toolbar-btn" x-on:click="addElement('text')" title="Textlabel">
                            <span style="font-weight:800;font-size:14px;line-height:1;">T</span>
                            <span>Text</span>
                        </button>
                        <button class="workshop-toolbar-btn" x-on:click="addElement('section')" title="Section / Frame">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" class="w-4 h-4"><rect x="3" y="3" width="14" height="14" rx="2" stroke-dasharray="3 2"/></svg>
                            <span>Section</span>
                        </button>
                        <button class="workshop-toolbar-btn" x-on:click="addElement('shape')" title="Form">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><circle cx="10" cy="10" r="7"/></svg>
                            <span>Form</span>
                        </button>
                        <button class="workshop-toolbar-btn"
                                x-on:click="startConnectorMode()"
                                :class="{ 'active': _connectorMode }"
                                title="Verbindung">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path fill-rule="evenodd" d="M2 10a.75.75 0 01.75-.75h12.69l-4.72-4.72a.75.75 0 011.06-1.06l6 6a.75.75 0 010 1.06l-6 6a.75.75 0 11-1.06-1.06l4.72-4.72H2.75A.75.75 0 012 10z" clip-rule="evenodd"/></svg>
                            <span>Pfeil</span>
                        </button>
                        <button class="workshop-toolbar-btn" x-on:click="addElement('kanban')" title="Kanban Board">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path d="M2 4.5A2.5 2.5 0 014.5 2h2A2.5 2.5 0 019 4.5v11A2.5 2.5 0 016.5 18h-2A2.5 2.5 0 012 15.5v-11zM11 4.5A2.5 2.5 0 0113.5 2h2A2.5 2.5 0 0118 4.5v6a2.5 2.5 0 01-2.5 2.5h-2A2.5 2.5 0 0111 10.5v-6z"/></svg>
                            <span>Kanban</span>
                        </button>
                        <button class="workshop-toolbar-btn" x-on:click="addElement('image')" title="Bild">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path fill-rule="evenodd" d="M1 5.25A2.25 2.25 0 013.25 3h13.5A2.25 2.25 0 0119 5.25v9.5A2.25 2.25 0 0116.75 17H3.25A2.25 2.25 0 011 14.75v-9.5zm1.5 5.81V14.75c0 .414.336.75.75.75h13.5a.75.75 0 00.75-.75v-2.06l-2.22-2.22a.75.75 0 00-1.06 0L9.06 15.56l-3.28-3.28a.75.75 0 00-1.06 0l-2.22 2.22zM5.5 7a1.5 1.5 0 100 3 1.5 1.5 0 000-3z" clip-rule="evenodd"/></svg>
                            <span>Bild</span>
                        </button>
                        <button class="workshop-toolbar-btn" x-on:click="addElement('image_grid')" title="Bilder-Grid">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path fill-rule="evenodd" d="M4.25 2A2.25 2.25 0 002 4.25v2.5A2.25 2.25 0 004.25 9h2.5A2.25 2.25 0 009 6.75v-2.5A2.25 2.25 0 006.75 2h-2.5zm0 9A2.25 2.25 0 002 13.25v2.5A2.25 2.25 0 004.25 18h2.5A2.25 2.25 0 009 15.75v-2.5A2.25 2.25 0 006.75 11h-2.5zm9-9A2.25 2.25 0 0011 4.25v2.5A2.25 2.25 0 0013.25 9h2.5A2.25 2.25 0 0018 6.75v-2.5A2.25 2.25 0 0015.75 2h-2.5zm0 9A2.25 2.25 0 0011 13.25v2.5A2.25 2.25 0 0013.25 18h2.5A2.25 2.25 0 0018 15.75v-2.5A2.25 2.25 0 0015.75 11h-2.5z" clip-rule="evenodd"/></svg>
                            <span>Grid</span>
                        </button>
                        <button class="workshop-toolbar-btn" x-on:click="addElement('video')" title="Video">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"/></svg>
                            <span>Video</span>
                        </button>
                    </div>

                    {{-- Board: JS owns this DOM entirely. Notes rendered by JS. --}}
                    <div x-ref="board" class="workshop-board" style="width: {{ $wBoardW }}px; height: {{ $wBoardH }}px;">
                        {{-- COREFIT Grid (read-only background) --}}
                        <div class="workshop-canvas-background" style="
                            position: absolute;
                            top: {{ $wGridTop }}px;
                            left: {{ $wGridLeft }}px;
                            width: {{ $wGridW }}px;
                            min-height: {{ $wGridH }}px;
                            display: grid;
                            grid-template-columns: repeat({{ $wGridCols }}, 1fr);
                            grid-template-rows: repeat({{ $wGridRows }}, minmax(180px, auto));
                            gap: 1.5px;
                            background: #2d2d2d;
                            border: 1.5px solid #2d2d2d;
                            border-radius: 4px;
                            overflow: hidden;
                        ">
                            @foreach($wsBlockDefs as $def)
                                <div class="workshop-grid-block">
                                    <div class="workshop-grid-block-header">
                                        <h4>{{ $def['label'] }}</h4>
                                        @svg($wsIconMap[$def['key']] ?? 'heroicon-o-square-3-stack-3d', 'w-5 h-5 text-gray-300')
                                    </div>
                                    <div class="workshop-grid-block-body">
                                        @if(!empty($def['guiding_questions']))
                                            <div class="guiding-questions">
                                                @foreach($def['guiding_questions'] as $q)
                                                    <div class="guiding-question">{{ $q }}</div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @else
            {{-- ═══ COREFIT LIST VIEW (original) ═══ --}}
            @php $metrics = $this->corefitMetrics; @endphp

            {{-- Einführung --}}
            <div class="bg-[var(--ui-info-5)] border border-[var(--ui-info-20)] rounded-lg p-4 mb-6">
                <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-1">Was ist COREFIT?</h3>
                <p class="text-sm text-[var(--ui-muted)]">
                    COREFIT klassifiziert jeden Prozessschritt nach seinem Wertbeitrag. Ziel: Core maximieren, Context reduzieren, No Fit eliminieren.
                </p>
                <div class="mt-2 flex flex-wrap gap-4 text-xs text-[var(--ui-muted)]">
                    <span><span class="inline-block w-2 h-2 rounded-full bg-[var(--ui-success)] mr-1"></span><strong>Core</strong> — Direkte Wertschöpfung für den Kunden</span>
                    <span><span class="inline-block w-2 h-2 rounded-full bg-[var(--ui-warning)] mr-1"></span><strong>Context</strong> — Notwendig, aber kein direkter Kundenwert (Admin, Compliance)</span>
                    <span><span class="inline-block w-2 h-2 rounded-full bg-[var(--ui-danger)] mr-1"></span><strong>No Fit</strong> — Kein Wertbeitrag, sollte eliminiert werden</span>
                </div>
            </div>

            {{-- Metriken-Kacheln --}}
            <div class="grid grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-lg border border-[var(--ui-border)] p-4">
                    <h3 class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Core-Steps</h3>
                    <p class="text-2xl font-bold text-[var(--ui-success)]">{{ $metrics['core']['count'] }}</p>
                    <p class="text-xs text-[var(--ui-muted)]">{{ $metrics['core']['percent'] }}% aller Schritte</p>
                </div>
                <div class="bg-white rounded-lg border border-[var(--ui-border)] p-4">
                    <h3 class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Context-Steps</h3>
                    <p class="text-2xl font-bold text-[var(--ui-warning)]">{{ $metrics['context']['count'] }}</p>
                    <p class="text-xs text-[var(--ui-muted)]">{{ $metrics['context']['percent'] }}% aller Schritte</p>
                </div>
                <div class="bg-white rounded-lg border border-[var(--ui-border)] p-4">
                    <h3 class="text-sm font-medium text-[var(--ui-secondary)] mb-1">No-Fit-Steps</h3>
                    <p class="text-2xl font-bold text-[var(--ui-danger)]">{{ $metrics['no_fit']['count'] }}</p>
                    <p class="text-xs text-[var(--ui-muted)]">{{ $metrics['no_fit']['percent'] }}% aller Schritte</p>
                </div>
                <div class="bg-white rounded-lg border border-[var(--ui-border)] p-4">
                    <h3 class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Gesamtkosten</h3>
                    <p class="text-2xl font-bold text-[var(--ui-info)]">{{ number_format($metrics['total_cost'], 2, ',', '.') }}</p>
                    <p class="text-xs text-[var(--ui-muted)]">EUR (basierend auf Stundensatz)</p>
                </div>
            </div>

            {{-- Durchlaufzeit & Effizienz --}}
            <div class="grid grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-lg border border-[var(--ui-border)] p-4">
                    <h3 class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Bearbeitungszeit</h3>
                    <p class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $metrics['total_duration'] }}</p>
                    <p class="text-xs text-[var(--ui-muted)]">Min. aktive Arbeit</p>
                </div>
                <div class="bg-white rounded-lg border border-[var(--ui-border)] p-4">
                    <h3 class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Wartezeit gesamt</h3>
                    <p class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $metrics['total_wait'] }}</p>
                    <p class="text-xs text-[var(--ui-muted)]">Min. Liegezeit</p>
                </div>
                <div class="bg-white rounded-lg border border-[var(--ui-border)] p-4">
                    <h3 class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Durchlaufzeit</h3>
                    <p class="text-2xl font-bold text-[var(--ui-info)]">{{ $metrics['lead_time'] }}</p>
                    <p class="text-xs text-[var(--ui-muted)]">Min. (Bearbeitung + Wartezeit)</p>
                </div>
                <div class="bg-white rounded-lg border border-[var(--ui-border)] p-4">
                    <h3 class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Prozesseffizienz</h3>
                    <p class="text-2xl font-bold {{ $metrics['efficiency'] >= 70 ? 'text-[var(--ui-success)]' : ($metrics['efficiency'] >= 40 ? 'text-[var(--ui-warning)]' : 'text-[var(--ui-danger)]') }}">{{ $metrics['efficiency'] }}%</p>
                    <p class="text-xs text-[var(--ui-muted)]">Anteil aktiver Arbeit an Durchlaufzeit</p>
                </div>
            </div>

            {{-- Zeitanalyse pro Klassifikation --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6 mb-6">
                <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-1">Zeitanalyse pro Klassifikation</h3>
                <p class="text-xs text-[var(--ui-muted)] mb-4">Bearbeitungszeit und Wartezeit aufgeschlüsselt nach Core, Context und No Fit.</p>
                <div class="grid grid-cols-3 gap-4">
                    @foreach([
                        'core' => ['label' => 'Core', 'color' => 'success'],
                        'context' => ['label' => 'Context', 'color' => 'warning'],
                        'no_fit' => ['label' => 'No Fit', 'color' => 'danger'],
                    ] as $cfKey => $cfMeta)
                    <div class="border border-[var(--ui-border)]/40 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="inline-block w-2 h-2 rounded-full bg-[var(--ui-{{ $cfMeta['color'] }})]"></span>
                            <h4 class="text-sm font-medium text-[var(--ui-secondary)]">{{ $cfMeta['label'] }}</h4>
                        </div>
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span class="text-[var(--ui-muted)]">Bearbeitung</span>
                                <span class="font-medium text-[var(--ui-secondary)]">{{ $metrics[$cfKey]['minutes'] }} Min.</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-[var(--ui-muted)]">Wartezeit</span>
                                <span class="font-medium text-[var(--ui-secondary)]">{{ $metrics[$cfKey]['wait'] }} Min.</span>
                            </div>
                            <div class="flex justify-between border-t border-[var(--ui-border)]/40 pt-1">
                                <span class="text-[var(--ui-muted)]">Kosten</span>
                                <span class="font-medium text-[var(--ui-{{ $cfMeta['color'] }})]">{{ number_format($metrics[$cfKey]['cost'], 2, ',', '.') }} EUR</span>
                            </div>
                            @if($metrics[$cfKey]['external_cost'] > 0)
                                <div class="flex justify-between text-[10px]">
                                    <span class="text-[var(--ui-muted)]">&nbsp;&nbsp;Arbeitszeit</span>
                                    <span class="text-[var(--ui-muted)]">{{ number_format($metrics[$cfKey]['labor_cost'], 2, ',', '.') }} EUR</span>
                                </div>
                                <div class="flex justify-between text-[10px]">
                                    <span class="text-[var(--ui-muted)]">&nbsp;&nbsp;Extern</span>
                                    <span class="text-[var(--ui-muted)]">{{ number_format($metrics[$cfKey]['external_cost'], 2, ',', '.') }} EUR</span>
                                </div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Progress Bars --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6 mb-6">
                <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-1">COREFIT-Verteilung</h3>
                <p class="text-xs text-[var(--ui-muted)] mb-4">Anteil der Schritte je Klassifikation. Idealerweise: Core hoch, Context niedrig, No Fit bei 0%.</p>
                <div class="space-y-3">
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-[var(--ui-secondary)]">Core</span>
                            <span class="font-medium text-[var(--ui-secondary)]">{{ $metrics['core']['percent'] }}%</span>
                        </div>
                        <div class="w-full bg-[var(--ui-muted-20)] rounded-full h-2">
                            <div class="bg-[var(--ui-success)] h-2 rounded-full" style="width: {{ min(100, $metrics['core']['percent']) }}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-[var(--ui-secondary)]">Context</span>
                            <span class="font-medium text-[var(--ui-secondary)]">{{ $metrics['context']['percent'] }}%</span>
                        </div>
                        <div class="w-full bg-[var(--ui-muted-20)] rounded-full h-2">
                            <div class="bg-[var(--ui-warning)] h-2 rounded-full" style="width: {{ min(100, $metrics['context']['percent']) }}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-[var(--ui-secondary)]">No Fit</span>
                            <span class="font-medium text-[var(--ui-secondary)]">{{ $metrics['no_fit']['percent'] }}%</span>
                        </div>
                        <div class="w-full bg-[var(--ui-muted-20)] rounded-full h-2">
                            <div class="bg-[var(--ui-danger)] h-2 rounded-full" style="width: {{ min(100, $metrics['no_fit']['percent']) }}%"></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Automation-Metriken --}}
            @php $autoMetrics = $this->automationMetrics; @endphp

            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6 mb-6">
                <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-1">Automatisierungsgrad</h3>
                <p class="text-xs text-[var(--ui-muted)] mb-4">Verteilung der Prozessschritte nach Automatisierungsgrad. Ziel: manuellen Anteil reduzieren, LLM-Anteil steigern.</p>

                <div class="grid grid-cols-4 gap-4 mb-4">
                    <div class="border border-[var(--ui-border)]/40 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="inline-block w-2 h-2 rounded-full bg-[var(--ui-muted)]"></span>
                            <h4 class="text-sm font-medium text-[var(--ui-secondary)]">Human</h4>
                        </div>
                        <p class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $autoMetrics['human']['count'] }}</p>
                        <p class="text-xs text-[var(--ui-muted)]">{{ $autoMetrics['human']['percent'] }}% &middot; {{ $autoMetrics['human']['minutes'] }} Min.</p>
                    </div>
                    <div class="border border-[var(--ui-border)]/40 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="inline-block w-2 h-2 rounded-full bg-[var(--ui-info)]"></span>
                            <h4 class="text-sm font-medium text-[var(--ui-secondary)]">LLM-Assisted</h4>
                        </div>
                        <p class="text-2xl font-bold text-[var(--ui-info)]">{{ $autoMetrics['llm_assisted']['count'] }}</p>
                        <p class="text-xs text-[var(--ui-muted)]">{{ $autoMetrics['llm_assisted']['percent'] }}% &middot; {{ $autoMetrics['llm_assisted']['minutes'] }} Min.</p>
                    </div>
                    <div class="border border-[var(--ui-border)]/40 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="inline-block w-2 h-2 rounded-full bg-[var(--ui-success)]"></span>
                            <h4 class="text-sm font-medium text-[var(--ui-secondary)]">LLM-Autonomous</h4>
                        </div>
                        <p class="text-2xl font-bold text-[var(--ui-success)]">{{ $autoMetrics['llm_autonomous']['count'] }}</p>
                        <p class="text-xs text-[var(--ui-muted)]">{{ $autoMetrics['llm_autonomous']['percent'] }}% &middot; {{ $autoMetrics['llm_autonomous']['minutes'] }} Min.</p>
                    </div>
                    <div class="border border-[var(--ui-border)]/40 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="inline-block w-2 h-2 rounded-full bg-[var(--ui-warning)]"></span>
                            <h4 class="text-sm font-medium text-[var(--ui-secondary)]">Hybrid</h4>
                        </div>
                        <p class="text-2xl font-bold text-[var(--ui-warning)]">{{ $autoMetrics['hybrid']['count'] }}</p>
                        <p class="text-xs text-[var(--ui-muted)]">{{ $autoMetrics['hybrid']['percent'] }}% &middot; {{ $autoMetrics['hybrid']['minutes'] }} Min.</p>
                    </div>
                </div>

                <div class="space-y-3">
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-[var(--ui-secondary)]">Human</span>
                            <span class="font-medium text-[var(--ui-secondary)]">{{ $autoMetrics['human']['percent'] }}%</span>
                        </div>
                        <div class="w-full bg-[var(--ui-muted-20)] rounded-full h-2">
                            <div class="bg-[var(--ui-muted)] h-2 rounded-full" style="width: {{ min(100, $autoMetrics['human']['percent']) }}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-[var(--ui-secondary)]">LLM-Assisted</span>
                            <span class="font-medium text-[var(--ui-secondary)]">{{ $autoMetrics['llm_assisted']['percent'] }}%</span>
                        </div>
                        <div class="w-full bg-[var(--ui-muted-20)] rounded-full h-2">
                            <div class="bg-[var(--ui-info)] h-2 rounded-full" style="width: {{ min(100, $autoMetrics['llm_assisted']['percent']) }}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-[var(--ui-secondary)]">LLM-Autonomous</span>
                            <span class="font-medium text-[var(--ui-secondary)]">{{ $autoMetrics['llm_autonomous']['percent'] }}%</span>
                        </div>
                        <div class="w-full bg-[var(--ui-muted-20)] rounded-full h-2">
                            <div class="bg-[var(--ui-success)] h-2 rounded-full" style="width: {{ min(100, $autoMetrics['llm_autonomous']['percent']) }}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-[var(--ui-secondary)]">Hybrid</span>
                            <span class="font-medium text-[var(--ui-secondary)]">{{ $autoMetrics['hybrid']['percent'] }}%</span>
                        </div>
                        <div class="w-full bg-[var(--ui-muted-20)] rounded-full h-2">
                            <div class="bg-[var(--ui-warning)] h-2 rounded-full" style="width: {{ min(100, $autoMetrics['hybrid']['percent']) }}%"></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Effizienz-Matrix --}}
            @php
                $matrix = $this->efficiencyMatrix;
                $autoLabels = [
                    'human' => 'Human',
                    'llm_assisted' => 'LLM-Assisted',
                    'llm_autonomous' => 'LLM-Autonomous',
                    'hybrid' => 'Hybrid',
                ];
                $corefitLabels = [
                    'core' => 'Core',
                    'context' => 'Context',
                    'no_fit' => 'not Fit',
                ];
                // Recommendation map: corefit => automation => [label, color-class]
                $recommendations = [
                    'core' => [
                        'human' => ['Investieren', 'bg-blue-50 border-blue-200'],
                        'llm_assisted' => ['Gut', 'bg-green-50 border-green-200'],
                        'llm_autonomous' => ['Optimal', 'bg-green-100 border-green-300'],
                        'hybrid' => ['Gut', 'bg-green-50 border-green-200'],
                    ],
                    'context' => [
                        'human' => ['Automatisieren', 'bg-orange-50 border-orange-200'],
                        'llm_assisted' => ['Akzeptabel', 'bg-yellow-50 border-yellow-200'],
                        'llm_autonomous' => ['Akzeptabel', 'bg-yellow-50 border-yellow-200'],
                        'hybrid' => ['Akzeptabel', 'bg-yellow-50 border-yellow-200'],
                    ],
                    'no_fit' => [
                        'human' => ['Eliminieren', 'bg-red-100 border-red-300'],
                        'llm_assisted' => ['Eliminieren', 'bg-red-50 border-red-200'],
                        'llm_autonomous' => ['Eliminieren', 'bg-red-50 border-red-200'],
                        'hybrid' => ['Eliminieren', 'bg-red-50 border-red-200'],
                    ],
                ];
                // Summary counts
                $summaryEliminate = 0;
                $summaryAutomate = 0;
                $summaryInvest = 0;
                $summaryOptimal = 0;
                $summaryAcceptable = 0;
                foreach ($matrix as $cf => $autos) {
                    foreach ($autos as $al => $cell) {
                        if ($cell['count'] === 0) continue;
                        $rec = $recommendations[$cf][$al][0] ?? '';
                        if ($rec === 'Eliminieren') $summaryEliminate += $cell['count'];
                        elseif ($rec === 'Automatisieren') $summaryAutomate += $cell['count'];
                        elseif ($rec === 'Investieren') $summaryInvest += $cell['count'];
                        elseif ($rec === 'Optimal') $summaryOptimal += $cell['count'];
                        elseif (in_array($rec, ['Gut', 'Akzeptabel'])) $summaryAcceptable += $cell['count'];
                    }
                }
            @endphp

            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6 mb-6">
                <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-1">Effizienz-Matrix</h3>
                <p class="text-xs text-[var(--ui-muted)] mb-4">Kreuzt COREFIT-Klassifikation mit Automatisierungsgrad. Leitprinzip: <strong>Eliminieren schlägt Automatisieren</strong> — selbst automatisierte Steps sollten weg, wenn sie keinen Wert liefern.</p>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr>
                                <th class="text-left py-2 px-3 text-xs font-semibold text-[var(--ui-muted)] uppercase tracking-wider"></th>
                                @foreach($autoLabels as $autoKey => $autoLabel)
                                    <th class="text-center py-2 px-3 text-xs font-semibold text-[var(--ui-muted)] uppercase tracking-wider">{{ $autoLabel }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($corefitLabels as $cfKey => $cfLabel)
                                <tr>
                                    <td class="py-2 px-3 font-semibold text-[var(--ui-secondary)] whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-block w-2 h-2 rounded-full {{ $cfKey === 'core' ? 'bg-[var(--ui-success)]' : ($cfKey === 'context' ? 'bg-[var(--ui-warning)]' : 'bg-[var(--ui-danger)]') }}"></span>
                                            {{ $cfLabel }}
                                        </div>
                                    </td>
                                    @foreach($autoLabels as $autoKey => $autoLabel)
                                        @php
                                            $cell = $matrix[$cfKey][$autoKey] ?? ['count' => 0, 'minutes' => 0, 'cost' => 0];
                                            $rec = $recommendations[$cfKey][$autoKey] ?? ['–', 'bg-gray-50 border-gray-200'];
                                        @endphp
                                        <td class="py-2 px-3">
                                            <div class="rounded-lg border p-3 {{ $cell['count'] > 0 ? $rec[1] : 'bg-[var(--ui-muted-5)] border-[var(--ui-border)]/40' }}">
                                                @if($cell['count'] > 0)
                                                    <div class="text-center">
                                                        <div class="text-lg font-bold text-[var(--ui-secondary)]">{{ $cell['count'] }}</div>
                                                        <div class="text-xs text-[var(--ui-muted)]">{{ $cell['minutes'] }} Min. &middot; {{ number_format($cell['cost'], 2, ',', '.') }} EUR</div>
                                                        <div class="mt-1 text-xs font-semibold {{ str_contains($rec[1], 'red') ? 'text-red-700' : (str_contains($rec[1], 'orange') ? 'text-orange-700' : (str_contains($rec[1], 'blue') ? 'text-blue-700' : (str_contains($rec[1], 'green') ? 'text-green-700' : 'text-yellow-700'))) }}">{{ $rec[0] }}</div>
                                                    </div>
                                                @else
                                                    <div class="text-center text-xs text-[var(--ui-muted)]">—</div>
                                                @endif
                                            </div>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Handlungsbedarf-Zusammenfassung --}}
                @if($metrics['total_steps'] > 0)
                    <div class="mt-4 pt-4 border-t border-[var(--ui-border)]/40">
                        <h4 class="text-xs font-semibold text-[var(--ui-secondary)] uppercase tracking-wider mb-2">Handlungsbedarf</h4>
                        <div class="flex flex-wrap gap-3">
                            @if($summaryEliminate > 0)
                                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-red-50 border border-red-200">
                                    <span class="inline-block w-2 h-2 rounded-full bg-red-500"></span>
                                    <span class="text-sm font-medium text-red-700">{{ $summaryEliminate }} Steps eliminieren</span>
                                </div>
                            @endif
                            @if($summaryAutomate > 0)
                                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-orange-50 border border-orange-200">
                                    <span class="inline-block w-2 h-2 rounded-full bg-orange-500"></span>
                                    <span class="text-sm font-medium text-orange-700">{{ $summaryAutomate }} Steps automatisieren</span>
                                </div>
                            @endif
                            @if($summaryInvest > 0)
                                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-blue-50 border border-blue-200">
                                    <span class="inline-block w-2 h-2 rounded-full bg-blue-500"></span>
                                    <span class="text-sm font-medium text-blue-700">{{ $summaryInvest }} Steps investieren</span>
                                </div>
                            @endif
                            @if($summaryOptimal > 0)
                                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-green-100 border border-green-300">
                                    <span class="inline-block w-2 h-2 rounded-full bg-green-600"></span>
                                    <span class="text-sm font-medium text-green-700">{{ $summaryOptimal }} Steps optimal</span>
                                </div>
                            @endif
                            @if($summaryAcceptable > 0)
                                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-yellow-50 border border-yellow-200">
                                    <span class="inline-block w-2 h-2 rounded-full bg-yellow-500"></span>
                                    <span class="text-sm font-medium text-yellow-700">{{ $summaryAcceptable }} Steps gut/akzeptabel</span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            {{-- Kostenbasis --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6 mb-6">
                <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-1">Kostenbasis</h3>
                <p class="text-xs text-[var(--ui-muted)] mb-4">Der Stundensatz wird mit der Dauer jedes Schritts multipliziert, um die Prozesskosten pro Klassifikation zu berechnen.</p>
                <div class="grid grid-cols-2 gap-4 max-w-lg">
                    <x-ui-input-text name="hourly_rate" label="Stundensatz (EUR/h)" type="number" wire:model.live="form.hourly_rate" min="0" step="0.01" placeholder="z.B. 85.00" />
                    <x-ui-input-select
                        name="frequency"
                        label="Häufigkeit"
                        :options="[
                            ['value' => '', 'label' => '– Keine Angabe –'],
                            ['value' => 'rare', 'label' => 'Selten (~6×/Jahr)'],
                            ['value' => 'occasional', 'label' => 'Gelegentlich (~1×/Monat)'],
                            ['value' => 'regular', 'label' => 'Regelmäßig (~1×/Woche)'],
                            ['value' => 'frequent', 'label' => 'Häufig (~1×/Tag)'],
                            ['value' => 'very_frequent', 'label' => 'Sehr häufig (mehrfach/Tag)'],
                        ]"
                        wire:model.live="form.frequency"
                    />
                </div>
            </div>

            {{-- Canvas Cards --}}
            @php
                $impByCategory = $this->improvementsByCategory;
                $canvasCards = [
                    ['field' => 'target_description', 'label' => 'Prozess & Zielbild', 'description' => 'Beschreibung des optimalen Soll-Zustands dieses Prozesses.', 'placeholder' => 'Wie soll der Prozess idealerweise aussehen?', 'category' => null],
                    ['field' => 'value_proposition', 'label' => 'Kundennutzen & Wertbeitrag', 'description' => 'Welchen konkreten Mehrwert liefert dieser Prozess an interne/externe Kunden?', 'placeholder' => 'Welchen Wert liefert der Prozess?', 'category' => 'quality'],
                    ['field' => 'process_landscape', 'label' => 'Prozesslandkarte', 'description' => 'Einordnung des Prozesses in die Gesamtlandschaft: Vor-/Nachprozesse, Schnittstellen und Abhängigkeiten zu anderen Prozessen.', 'placeholder' => 'Wo steht dieser Prozess in der Gesamtlandschaft? Welche Prozesse liefern Inputs, welche empfangen Outputs?', 'category' => null],
                    ['field' => 'corefit_classification_notes', 'label' => 'COREFIT Klassifizierung', 'description' => 'Begründung und Bewertung der COREFIT-Einstufung: Warum sind Steps Core, Context oder No Fit? Was ist die strategische Einordnung?', 'placeholder' => 'Wie wurde die COREFIT-Klassifizierung vorgenommen? Welche Kriterien wurden angelegt?', 'category' => null],
                    ['field' => 'cost_analysis', 'label' => 'Kosten & Break-Even', 'description' => 'Analyse der laufenden Kosten, Investitionen und ab wann sich Verbesserungen rechnen.', 'placeholder' => 'Kosten, Aufwand, Break-Even-Analyse', 'category' => 'cost'],
                    ['field' => 'risk_assessment', 'label' => 'Risiko & Resilienz', 'description' => 'Wo liegen Ausfallrisiken, Single Points of Failure und Schwachstellen?', 'placeholder' => 'Risiken, Single Points of Failure, Resilienz', 'category' => 'risk'],
                    ['field' => 'improvement_levers', 'label' => 'Hebel & Lösungsdesign', 'description' => 'Die wirksamsten Stellschrauben zur Verbesserung von Durchlaufzeit und Effizienz.', 'placeholder' => 'Wo liegen die größten Verbesserungshebel?', 'category' => 'speed'],
                    ['field' => 'action_plan', 'label' => 'Maßnahmenplan', 'description' => 'Konkrete nächste Schritte mit Verantwortlichkeiten und Zeitrahmen.', 'placeholder' => 'Konkrete nächste Schritte', 'category' => null],
                    ['field' => 'standardization_notes', 'label' => 'Standardisierung & Kontrolle', 'description' => 'Definierte Standards, KPIs und Kontrollmechanismen zur nachhaltigen Absicherung.', 'placeholder' => 'Standards, KPIs, Kontrollmechanismen', 'category' => 'standardization'],
                ];
            @endphp

            <div class="space-y-4">
                @foreach($canvasCards as $card)
                    <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                        <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-1">{{ $card['label'] }}</h3>
                        <p class="text-xs text-[var(--ui-muted)] mb-3">{{ $card['description'] }}</p>
                        <x-ui-input-textarea
                            name="{{ $card['field'] }}"
                            wire:model.live="form.{{ $card['field'] }}"
                            rows="3"
                            placeholder="{{ $card['placeholder'] }}"
                        />
                        @if($card['category'] && isset($impByCategory[$card['category']]))
                            @php $catData = $impByCategory[$card['category']]; @endphp
                            @if($catData['total'] > 0)
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach($catData['statuses'] as $status => $count)
                                        @php
                                            $statusLabels = [
                                                'identified' => 'identifiziert',
                                                'planned' => 'geplant',
                                                'in_progress' => 'in Arbeit',
                                                'on_hold' => 'pausiert',
                                                'completed' => 'umgesetzt',
                                                'under_observation' => 'in Beobachtung',
                                                'validated' => 'validiert',
                                                'failed' => 'wirkungslos',
                                                'rejected' => 'abgelehnt',
                                            ];
                                            $statusVariants = [
                                                'identified' => 'muted',
                                                'planned' => 'info',
                                                'in_progress' => 'warning',
                                                'on_hold' => 'muted',
                                                'completed' => 'info',
                                                'under_observation' => 'warning',
                                                'validated' => 'success',
                                                'failed' => 'danger',
                                                'rejected' => 'danger',
                                            ];
                                        @endphp
                                        <x-ui-badge variant="{{ $statusVariants[$status] ?? 'muted' }}" size="sm">
                                            {{ $count }} {{ $statusLabels[$status] ?? $status }}
                                        </x-ui-badge>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>
            @endif {{-- end corefitViewMode else (list) --}}
        @endif

        {{-- ── Tab: Steps ──────────────────────────────────── --}}
        @if($activeTab === 'steps')
            <div class="flex justify-end mb-4">
                <x-ui-button variant="primary" size="sm" wire:click="createStep">
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    <span>Neuer Schritt</span>
                </x-ui-button>
            </div>

            <x-ui-table compact="true">
                <x-ui-table-header>
                    <x-ui-table-header-cell compact="true">Pos</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Name</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Typ</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Kompl.</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Dauer</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">CoreFit</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Automation</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true"></x-ui-table-header-cell>
                </x-ui-table-header>
                <x-ui-table-body>
                    @forelse($this->steps as $step)
                        <x-ui-table-row compact="true">
                            <x-ui-table-cell compact="true">
                                <span class="text-sm font-mono">{{ $step->position }}</span>
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <div class="font-medium">{{ $step->name }}</div>
                                @if($step->description)
                                    <div class="text-xs text-[var(--ui-muted)]">{{ \Illuminate\Support\Str::limit($step->description, 60) }}</div>
                                @endif
                                @if($step->step_type === 'subprocess' && $step->sub_process_id && $step->subProcess)
                                    <div class="mt-0.5">
                                        <a href="{{ route('organization.processes.show', $step->subProcess) }}" wire:navigate
                                           class="inline-flex items-center gap-1 text-[10px] text-[var(--ui-primary)] hover:underline">
                                            @svg('heroicon-o-arrow-turn-down-right', 'w-3 h-3')
                                            <span>Sub-Prozess: {{ $step->subProcess->name }}</span>
                                        </a>
                                    </div>
                                @endif
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <x-ui-badge variant="info" size="sm">{{ ucfirst($step->step_type ?? 'task') }}</x-ui-badge>
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                @if($step->complexity)
                                    <x-ui-badge variant="secondary" size="sm">{{ strtoupper($step->complexity->value) }}</x-ui-badge>
                                @else
                                    <span class="text-xs text-[var(--ui-muted)]">–</span>
                                @endif
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                @if($step->duration_target_minutes)
                                    <span class="text-sm">{{ $step->duration_target_minutes }} min</span>
                                @else
                                    <span class="text-xs text-[var(--ui-muted)]">–</span>
                                @endif
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                @if($step->corefit_classification?->value === 'core')
                                    <x-ui-badge variant="success" size="sm">Core</x-ui-badge>
                                @elseif($step->corefit_classification?->value === 'context')
                                    <x-ui-badge variant="warning" size="sm">Context</x-ui-badge>
                                @else
                                    <x-ui-badge variant="danger" size="sm">No Fit</x-ui-badge>
                                @endif
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                @if($step->automation_level?->value === 'llm_autonomous')
                                    <x-ui-badge variant="success" size="sm">LLM-Autonomous</x-ui-badge>
                                @elseif($step->automation_level?->value === 'llm_assisted')
                                    <x-ui-badge variant="info" size="sm">LLM-Assisted</x-ui-badge>
                                @elseif($step->automation_level?->value === 'hybrid')
                                    <x-ui-badge variant="warning" size="sm">Hybrid</x-ui-badge>
                                @else
                                    <x-ui-badge variant="muted" size="sm">Human</x-ui-badge>
                                @endif
                                @if($step->llm_tools && count($step->llm_tools) > 0)
                                    <span class="text-[10px] text-[var(--ui-muted)]">{{ count($step->llm_tools) }} Tools</span>
                                @endif
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <div class="flex gap-1 justify-end">
                                    <x-ui-button size="xs" variant="secondary-outline" wire:click="editStep({{ $step->id }})">
                                        @svg('heroicon-o-pencil-square', 'w-4 h-4')
                                    </x-ui-button>
                                    <x-ui-confirm-button size="xs" variant="danger-outline" wire:click="deleteStep({{ $step->id }})" confirm-text="Schritt wirklich löschen?">
                                        @svg('heroicon-o-trash', 'w-4 h-4')
                                    </x-ui-confirm-button>
                                </div>
                            </x-ui-table-cell>
                        </x-ui-table-row>
                    @empty
                        <x-ui-table-row compact="true">
                            <x-ui-table-cell compact="true" colspan="8">
                                <div class="text-center text-[var(--ui-muted)] py-6">Keine Schritte vorhanden.</div>
                            </x-ui-table-cell>
                        </x-ui-table-row>
                    @endforelse
                </x-ui-table-body>
            </x-ui-table>
        @endif

        {{-- ── Tab: Flows ──────────────────────────────────── --}}
        @if($activeTab === 'flows')
            <div class="flex justify-end mb-4">
                <x-ui-button variant="primary" size="sm" wire:click="createFlow">
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    <span>Neuer Flow</span>
                </x-ui-button>
            </div>

            <x-ui-table compact="true">
                <x-ui-table-header>
                    <x-ui-table-header-cell compact="true">Von</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Nach</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Bedingung</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Standard</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true"></x-ui-table-header-cell>
                </x-ui-table-header>
                <x-ui-table-body>
                    @forelse($this->flows as $flow)
                        <x-ui-table-row compact="true">
                            <x-ui-table-cell compact="true">
                                <span class="text-sm font-medium">{{ $flow->fromStep?->name ?? '–' }}</span>
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <span class="text-sm font-medium">{{ $flow->toStep?->name ?? '–' }}</span>
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <span class="text-sm">{{ $flow->condition_label ?? '–' }}</span>
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                @if($flow->is_default)
                                    <x-ui-badge variant="info" size="sm">Standard</x-ui-badge>
                                @endif
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <div class="flex gap-1 justify-end">
                                    <x-ui-button size="xs" variant="secondary-outline" wire:click="editFlow({{ $flow->id }})">
                                        @svg('heroicon-o-pencil-square', 'w-4 h-4')
                                    </x-ui-button>
                                    <x-ui-confirm-button size="xs" variant="danger-outline" wire:click="deleteFlow({{ $flow->id }})" confirm-text="Flow wirklich löschen?">
                                        @svg('heroicon-o-trash', 'w-4 h-4')
                                    </x-ui-confirm-button>
                                </div>
                            </x-ui-table-cell>
                        </x-ui-table-row>
                    @empty
                        <x-ui-table-row compact="true">
                            <x-ui-table-cell compact="true" colspan="5">
                                <div class="text-center text-[var(--ui-muted)] py-6">Keine Flows vorhanden.</div>
                            </x-ui-table-cell>
                        </x-ui-table-row>
                    @endforelse
                </x-ui-table-body>
            </x-ui-table>
        @endif

        {{-- ── Tab: Triggers ───────────────────────────────── --}}
        @if($activeTab === 'triggers')
            <div class="flex justify-end mb-4">
                <x-ui-button variant="primary" size="sm" wire:click="createTrigger">
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    <span>Neuer Trigger</span>
                </x-ui-button>
            </div>

            <x-ui-table compact="true">
                <x-ui-table-header>
                    <x-ui-table-header-cell compact="true">Label</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Typ</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Quelle</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true"></x-ui-table-header-cell>
                </x-ui-table-header>
                <x-ui-table-body>
                    @forelse($this->triggers as $trigger)
                        <x-ui-table-row compact="true">
                            <x-ui-table-cell compact="true">
                                <div class="font-medium">{{ $trigger->label }}</div>
                                @if($trigger->description)
                                    <div class="text-xs text-[var(--ui-muted)]">{{ \Illuminate\Support\Str::limit($trigger->description, 60) }}</div>
                                @endif
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <x-ui-badge variant="info" size="sm">{{ ucfirst(str_replace('_', ' ', $trigger->trigger_type)) }}</x-ui-badge>
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                @if($trigger->entityType)
                                    <span class="text-sm">
                                        <x-ui-badge variant="muted" size="sm">Typ</x-ui-badge>
                                        {{ $trigger->entityType->name }}
                                    </span>
                                @elseif($trigger->entity)
                                    <span class="text-sm">{{ $trigger->entity->name }}</span>
                                @elseif($trigger->sourceProcess)
                                    <a href="{{ route('organization.processes.show', $trigger->sourceProcess) }}"
                                       class="text-sm font-medium text-[var(--ui-primary)] hover:underline inline-flex items-center gap-1"
                                       wire:navigate>
                                        @svg('heroicon-o-arrow-left-circle', 'w-3.5 h-3.5')
                                        <span>{{ $trigger->sourceProcess->name }}</span>
                                    </a>
                                @elseif($trigger->interlink)
                                    <span class="text-sm">{{ $trigger->interlink->name }}</span>
                                @elseif($trigger->schedule_expression)
                                    <code class="text-xs">{{ $trigger->schedule_expression }}</code>
                                @else
                                    <span class="text-xs text-[var(--ui-muted)]">–</span>
                                @endif
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <div class="flex gap-1 justify-end">
                                    <x-ui-button size="xs" variant="secondary-outline" wire:click="editTrigger({{ $trigger->id }})">
                                        @svg('heroicon-o-pencil-square', 'w-4 h-4')
                                    </x-ui-button>
                                    <x-ui-confirm-button size="xs" variant="danger-outline" wire:click="deleteTrigger({{ $trigger->id }})" confirm-text="Trigger wirklich löschen?">
                                        @svg('heroicon-o-trash', 'w-4 h-4')
                                    </x-ui-confirm-button>
                                </div>
                            </x-ui-table-cell>
                        </x-ui-table-row>
                    @empty
                        <x-ui-table-row compact="true">
                            <x-ui-table-cell compact="true" colspan="4">
                                <div class="text-center text-[var(--ui-muted)] py-6">Keine Triggers vorhanden.</div>
                            </x-ui-table-cell>
                        </x-ui-table-row>
                    @endforelse
                </x-ui-table-body>
            </x-ui-table>
        @endif

        {{-- ── Tab: Outputs ────────────────────────────────── --}}
        @if($activeTab === 'outputs')
            <div class="flex justify-end mb-4">
                <x-ui-button variant="primary" size="sm" wire:click="createOutput">
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    <span>Neuer Output</span>
                </x-ui-button>
            </div>

            <x-ui-table compact="true">
                <x-ui-table-header>
                    <x-ui-table-header-cell compact="true">Label</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Typ</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Ziel</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true"></x-ui-table-header-cell>
                </x-ui-table-header>
                <x-ui-table-body>
                    @forelse($this->outputs as $output)
                        <x-ui-table-row compact="true">
                            <x-ui-table-cell compact="true">
                                <div class="font-medium">{{ $output->label }}</div>
                                @if($output->description)
                                    <div class="text-xs text-[var(--ui-muted)]">{{ \Illuminate\Support\Str::limit($output->description, 60) }}</div>
                                @endif
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <x-ui-badge variant="info" size="sm">{{ ucfirst(str_replace('_', ' ', $output->output_type)) }}</x-ui-badge>
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                @if($output->entity)
                                    <span class="text-sm">{{ $output->entity->name }}</span>
                                @elseif($output->targetProcess)
                                    <a href="{{ route('organization.processes.show', $output->targetProcess) }}"
                                       class="text-sm font-medium text-[var(--ui-primary)] hover:underline inline-flex items-center gap-1"
                                       wire:navigate>
                                        @svg('heroicon-o-arrow-right-circle', 'w-3.5 h-3.5')
                                        <span>{{ $output->targetProcess->name }}</span>
                                    </a>
                                @elseif($output->interlink)
                                    <span class="text-sm">{{ $output->interlink->name }}</span>
                                @else
                                    <span class="text-xs text-[var(--ui-muted)]">–</span>
                                @endif
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <div class="flex gap-1 justify-end">
                                    <x-ui-button size="xs" variant="secondary-outline" wire:click="editOutput({{ $output->id }})">
                                        @svg('heroicon-o-pencil-square', 'w-4 h-4')
                                    </x-ui-button>
                                    <x-ui-confirm-button size="xs" variant="danger-outline" wire:click="deleteOutput({{ $output->id }})" confirm-text="Output wirklich löschen?">
                                        @svg('heroicon-o-trash', 'w-4 h-4')
                                    </x-ui-confirm-button>
                                </div>
                            </x-ui-table-cell>
                        </x-ui-table-row>
                    @empty
                        <x-ui-table-row compact="true">
                            <x-ui-table-cell compact="true" colspan="4">
                                <div class="text-center text-[var(--ui-muted)] py-6">Keine Outputs vorhanden.</div>
                            </x-ui-table-cell>
                        </x-ui-table-row>
                    @endforelse
                </x-ui-table-body>
            </x-ui-table>
        @endif

        {{-- ── Tab: Verbesserungen ──────────────────────────── --}}
        @if($activeTab === 'improvements')
            <div class="flex justify-end mb-4">
                <x-ui-button variant="primary" size="sm" wire:click="createImprovement">
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    <span>Neue Verbesserung</span>
                </x-ui-button>
            </div>

            @php $impSimulations = $this->improvementSimulations; @endphp
            <x-ui-table compact="true">
                <x-ui-table-header>
                    <x-ui-table-header-cell compact="true">Titel</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Kategorie</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Priorität</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Projektion</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true"></x-ui-table-header-cell>
                </x-ui-table-header>
                <x-ui-table-body>
                    @forelse($this->processImprovements as $imp)
                        @php $sim = $impSimulations['simulations'][$imp->id] ?? null; @endphp
                        <x-ui-table-row compact="true">
                            <x-ui-table-cell compact="true">
                                <div class="font-medium">{{ $imp->title }}</div>
                                @if($imp->target_step_id)
                                    @php $targetStepName = $this->steps->firstWhere('id', $imp->target_step_id)?->name; @endphp
                                    @if($targetStepName)
                                        <div class="text-[10px] text-[var(--ui-info)]">@svg('heroicon-o-arrow-right', 'w-3 h-3 inline') {{ $targetStepName }}</div>
                                    @endif
                                @elseif($imp->description)
                                    <div class="text-xs text-[var(--ui-muted)]">{{ \Illuminate\Support\Str::limit($imp->description, 60) }}</div>
                                @endif
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <x-ui-badge variant="info" size="sm">{{ ucfirst($imp->category) }}</x-ui-badge>
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                @if($imp->priority === 'critical')
                                    <x-ui-badge variant="danger" size="sm">Kritisch</x-ui-badge>
                                @elseif($imp->priority === 'high')
                                    <x-ui-badge variant="warning" size="sm">Hoch</x-ui-badge>
                                @elseif($imp->priority === 'medium')
                                    <x-ui-badge variant="info" size="sm">Mittel</x-ui-badge>
                                @else
                                    <x-ui-badge variant="muted" size="sm">Niedrig</x-ui-badge>
                                @endif
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                @if($imp->status === 'validated')
                                    <x-ui-badge variant="success" size="sm">Validiert</x-ui-badge>
                                @elseif($imp->status === 'failed')
                                    <x-ui-badge variant="danger" size="sm">Wirkungslos</x-ui-badge>
                                @elseif($imp->status === 'under_observation')
                                    <x-ui-badge variant="warning" size="sm">In Beobachtung</x-ui-badge>
                                @elseif($imp->status === 'completed')
                                    <x-ui-badge variant="info" size="sm">Umgesetzt</x-ui-badge>
                                @elseif($imp->status === 'in_progress')
                                    <x-ui-badge variant="warning" size="sm">In Arbeit</x-ui-badge>
                                @elseif($imp->status === 'on_hold')
                                    <x-ui-badge variant="muted" size="sm">Pausiert</x-ui-badge>
                                @elseif($imp->status === 'planned')
                                    <x-ui-badge variant="info" size="sm">Geplant</x-ui-badge>
                                @elseif($imp->status === 'rejected')
                                    <x-ui-badge variant="danger" size="sm">Abgelehnt</x-ui-badge>
                                @else
                                    <x-ui-badge variant="muted" size="sm">Identifiziert</x-ui-badge>
                                @endif
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                @if($sim)
                                    <div class="flex flex-wrap gap-1">
                                        @if($sim['score_delta'] !== 0)
                                            <x-ui-badge variant="{{ $sim['score_delta'] > 0 ? 'success' : 'danger' }}" size="sm">
                                                Score {{ $sim['score_delta'] > 0 ? '+' : '' }}{{ $sim['score_delta'] }}
                                            </x-ui-badge>
                                        @endif
                                        @if($sim['cost_reduction_per_month'] > 0)
                                            <x-ui-badge variant="success" size="sm">
                                                -{{ number_format($sim['cost_reduction_per_month'], 0, ',', '.') }} &euro;/Mo
                                            </x-ui-badge>
                                        @endif
                                        @if($sim['productivity_gain_per_month'] > 0)
                                            <x-ui-badge variant="info" size="sm">
                                                +{{ number_format($sim['productivity_gain_per_month'], 0, ',', '.') }} &euro;/Mo Prod.
                                            </x-ui-badge>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-xs text-[var(--ui-muted)]">&ndash;</span>
                                @endif
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <div class="flex gap-1 justify-end">
                                    <x-ui-button size="xs" variant="secondary-outline" wire:click="editImprovement({{ $imp->id }})">
                                        @svg('heroicon-o-pencil-square', 'w-4 h-4')
                                    </x-ui-button>
                                    <x-ui-confirm-button size="xs" variant="danger-outline" wire:click="deleteImprovement({{ $imp->id }})" confirm-text="Verbesserung wirklich löschen?">
                                        @svg('heroicon-o-trash', 'w-4 h-4')
                                    </x-ui-confirm-button>
                                </div>
                            </x-ui-table-cell>
                        </x-ui-table-row>
                    @empty
                        <x-ui-table-row compact="true">
                            <x-ui-table-cell compact="true" colspan="6">
                                <div class="text-center text-[var(--ui-muted)] py-6">Keine Verbesserungen vorhanden.</div>
                            </x-ui-table-cell>
                        </x-ui-table-row>
                    @endforelse
                </x-ui-table-body>
            </x-ui-table>

            {{-- Improvement Summary Block --}}
            @if($impSimulations['total_cost_savings_per_month'] > 0)
                <div class="mt-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                    <h4 class="text-sm font-semibold text-green-800 mb-2">Gesamtpotenzial (wenn alle Projektionen umgesetzt)</h4>
                    <div class="flex gap-6">
                        <div>
                            <span class="text-xs text-green-600">Gesamt/Monat</span>
                            <div class="text-lg font-bold text-green-700">{{ number_format($impSimulations['total_cost_savings_per_month'], 2, ',', '.') }} &euro;</div>
                        </div>
                        <div>
                            <span class="text-xs text-green-600">Gesamt/Jahr</span>
                            <div class="text-lg font-bold text-green-700">{{ number_format($impSimulations['total_cost_savings_per_year'], 2, ',', '.') }} &euro;</div>
                        </div>
                        @if($impSimulations['total_cost_reduction_per_month'] > 0)
                            <div class="border-l border-green-300 pl-4">
                                <span class="text-xs text-green-600">Echte Einsparung/Mo</span>
                                <div class="text-sm font-semibold text-green-700">{{ number_format($impSimulations['total_cost_reduction_per_month'], 2, ',', '.') }} &euro;</div>
                            </div>
                        @endif
                        @if($impSimulations['total_productivity_gain_per_month'] > 0)
                            <div class="border-l border-blue-300 pl-4">
                                <span class="text-xs text-blue-600">Produktivitätsgewinn/Mo</span>
                                <div class="text-sm font-semibold text-blue-700">{{ number_format($impSimulations['total_productivity_gain_per_month'], 2, ',', '.') }} &euro;</div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        @endif

        {{-- ── Tab: Durchläufe ────────────────────────────────── --}}
        @if($activeTab === 'runs')
            <div class="flex justify-end mb-4">
                <x-ui-button variant="primary" size="sm" wire:click="startRun">
                    @svg('heroicon-o-play', 'w-4 h-4')
                    <span>Durchlauf starten</span>
                </x-ui-button>
            </div>

            {{-- Run-Liste --}}
            <x-ui-table compact="true">
                <x-ui-table-header>
                    <x-ui-table-header-cell compact="true">Gestartet</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Fortschritt</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Aktive Zeit</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Wartezeit</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Erstellt von</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true"></x-ui-table-header-cell>
                </x-ui-table-header>
                <x-ui-table-body>
                    @forelse($this->allRuns as $run)
                        @php
                            $runTotal = $run->runSteps->count();
                            $runDone = $run->runSteps->whereIn('status', [\Platform\Organization\Enums\RunStepStatus::COMPLETED, \Platform\Organization\Enums\RunStepStatus::SKIPPED])->count();
                        @endphp
                        <x-ui-table-row compact="true" class="cursor-pointer hover:bg-[var(--ui-muted-5)]" onclick="window.Livewire.navigate('{{ route('organization.processes.runs.show', [$process, $run]) }}')">
                            <x-ui-table-cell compact="true">
                                <a href="{{ route('organization.processes.runs.show', [$process, $run]) }}" wire:navigate class="text-sm hover:underline">{{ $run->started_at->format('d.m.Y H:i') }}</a>
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <x-ui-badge variant="{{ $run->status->color() }}" size="sm">{{ $run->status->label() }}</x-ui-badge>
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <span class="text-sm">{{ $runDone }}/{{ $runTotal }}</span>
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <span class="text-sm">{{ $run->runSteps->sum('active_duration_minutes') ?? 0 }} Min.</span>
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <span class="text-sm">{{ $run->runSteps->sum('wait_duration_minutes') ?? 0 }} Min.</span>
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <span class="text-sm">{{ $run->user?->name ?? '–' }}</span>
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <div class="flex gap-1 justify-end" @click.stop>
                                    <button
                                        type="button"
                                        wire:click="deleteRun({{ $run->id }})"
                                        wire:confirm="Durchlauf wirklich löschen?"
                                        class="p-1 text-[var(--ui-muted)] hover:text-red-500 transition-colors rounded"
                                    >
                                        @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                                    </button>
                                </div>
                            </x-ui-table-cell>
                        </x-ui-table-row>
                    @empty
                        <x-ui-table-row compact="true">
                            <x-ui-table-cell compact="true" colspan="7">
                                <div class="text-center text-[var(--ui-muted)] py-6">Noch keine Durchläufe</div>
                            </x-ui-table-cell>
                        </x-ui-table-row>
                    @endforelse
                </x-ui-table-body>
            </x-ui-table>

            {{-- Analytics --}}
            @php $analytics = $this->runAnalytics; @endphp
            @if(($analytics['total_completed'] ?? 0) >= 1)
                <div class="mt-6 bg-white rounded-lg border border-[var(--ui-border)] p-5">
                    <h4 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Ist vs. Soll Analyse</h4>
                    <div class="grid grid-cols-3 gap-6">
                        <div class="text-center">
                            <p class="text-xs text-[var(--ui-muted)] mb-1">Ø Aktive Zeit</p>
                            <p class="text-lg font-bold text-[var(--ui-secondary)]">{{ $analytics['avg_active_minutes'] }} Min.</p>
                            <p class="text-[10px] text-[var(--ui-muted)]">Soll: {{ $analytics['target_active_minutes'] }} Min.</p>
                        </div>
                        <div class="text-center">
                            <p class="text-xs text-[var(--ui-muted)] mb-1">Ø Wartezeit</p>
                            <p class="text-lg font-bold text-[var(--ui-secondary)]">{{ $analytics['avg_wait_minutes'] }} Min.</p>
                            <p class="text-[10px] text-[var(--ui-muted)]">Soll: {{ $analytics['target_wait_minutes'] }} Min.</p>
                        </div>
                        <div class="text-center">
                            <p class="text-xs text-[var(--ui-muted)] mb-1">Abweichung</p>
                            <p class="text-lg font-bold {{ $analytics['efficiency_delta'] > 0 ? 'text-red-500' : 'text-green-600' }}">
                                {{ $analytics['efficiency_delta'] > 0 ? '+' : '' }}{{ $analytics['efficiency_delta'] }}%
                            </p>
                            <p class="text-[10px] text-[var(--ui-muted)]">{{ $analytics['total_completed'] }} abgeschlossene Durchläufe</p>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-[var(--ui-border)]/40 flex justify-end">
                        <x-ui-button size="sm" variant="secondary-outline" wire:click="applyRunAverages" wire:confirm="Soll-Zeiten der Steps mit den Durchschnittswerten aus {{ $analytics['total_completed'] }} abgeschlossenen Durchläufen überschreiben?">
                            @svg('heroicon-o-arrow-down-on-square', 'w-4 h-4')
                            <span>Ø in Soll-Zeiten übernehmen</span>
                        </x-ui-button>
                    </div>
                </div>
            @endif
        @endif

        {{-- ── Tab: Snapshots ────────────────────────────────── --}}
        @if($activeTab === 'snapshots')
            <div class="flex justify-end mb-4">
                <x-ui-button variant="primary" size="sm" wire:click="createSnapshot">
                    @svg('heroicon-o-camera', 'w-4 h-4')
                    <span>Snapshot erstellen</span>
                </x-ui-button>
            </div>

            <x-ui-table compact="true">
                <x-ui-table-header>
                    <x-ui-table-header-cell compact="true">Version</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Label</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Steps</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Dauer</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Erstellt</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true"></x-ui-table-header-cell>
                </x-ui-table-header>
                <x-ui-table-body>
                    @forelse($this->processSnapshots as $snap)
                        <x-ui-table-row compact="true">
                            <x-ui-table-cell compact="true">
                                <span class="text-sm font-mono font-bold">v{{ $snap->version }}</span>
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <span class="text-sm">{{ $snap->label ?? '–' }}</span>
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <span class="text-sm">{{ $snap->metrics['total_steps'] ?? 0 }}</span>
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <span class="text-sm">{{ $snap->metrics['total_duration'] ?? 0 }} min</span>
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <span class="text-sm">{{ $snap->created_at?->format('d.m.Y H:i') }}</span>
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <div class="flex gap-1 justify-end">
                                    <x-ui-confirm-button size="xs" variant="danger-outline" wire:click="deleteSnapshot({{ $snap->id }})" confirm-text="Snapshot wirklich löschen?">
                                        @svg('heroicon-o-trash', 'w-4 h-4')
                                    </x-ui-confirm-button>
                                </div>
                            </x-ui-table-cell>
                        </x-ui-table-row>
                    @empty
                        <x-ui-table-row compact="true">
                            <x-ui-table-cell compact="true" colspan="6">
                                <div class="text-center text-[var(--ui-muted)] py-6">Keine Snapshots vorhanden.</div>
                            </x-ui-table-cell>
                        </x-ui-table-row>
                    @endforelse
                </x-ui-table-body>
            </x-ui-table>
        @endif

        {{-- ── Tab: Ausweis (Certificate) ───────────────────────── --}}
        @if($activeTab === 'certificate')
            @php $certData = $this->certificateData; @endphp

            {{-- Live Preview --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-8 shadow-sm mt-6">
                {{-- Header --}}
                <div class="border-b-[3px] border-gray-800 pb-3 mb-5">
                    <h1 class="text-2xl font-bold tracking-widest text-gray-800 uppercase">Prozessausweis</h1>
                    <p class="text-base text-gray-500 mt-1">{{ $certData['process']['name'] }}</p>
                    <p class="text-xs text-gray-400 font-mono">
                        @if($certData['process']['code']){{ $certData['process']['code'] }} &middot; @endif
                        Version {{ $certData['process']['version'] }}
                    </p>
                </div>

                {{-- Meta --}}
                <div class="grid grid-cols-4 gap-0 mb-5">
                    @foreach([
                        ['label' => 'Owner', 'value' => $certData['process']['owner'] ?? '–'],
                        ['label' => 'VSM System', 'value' => $certData['process']['vsm_system'] ?? '–'],
                        ['label' => 'Status', 'value' => ucfirst($certData['process']['status'])],
                        ['label' => 'Team', 'value' => $certData['process']['team'] ?? '–'],
                    ] as $meta)
                        <div class="p-3 bg-gray-50 border border-gray-200">
                            <div class="text-[10px] uppercase tracking-wider text-gray-400 font-bold">{{ $meta['label'] }}</div>
                            <div class="text-sm font-bold text-gray-800 mt-0.5">{{ $meta['value'] }}</div>
                        </div>
                    @endforeach
                </div>

                {{-- Efficiency Scale --}}
                <div class="mb-5">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-gray-800 mb-2">Prozess-Score</h3>
                    @php
                        $scaleClasses = [
                            ['class' => 'A+', 'color' => '#16a34a'],
                            ['class' => 'A',  'color' => '#22c55e'],
                            ['class' => 'B',  'color' => '#84cc16'],
                            ['class' => 'C',  'color' => '#eab308'],
                            ['class' => 'D',  'color' => '#f97316'],
                            ['class' => 'E',  'color' => '#ef4444'],
                            ['class' => 'F',  'color' => '#dc2626'],
                            ['class' => 'G',  'color' => '#991b1b'],
                        ];
                        $currentClass = $certData['efficiency_class']['class'];
                    @endphp
                    <div class="flex h-8 rounded overflow-hidden mb-2">
                        @foreach($scaleClasses as $sc)
                            <div class="flex-1 flex items-center justify-center text-white text-xs font-bold {{ $sc['class'] === $currentClass ? 'ring-2 ring-gray-800 ring-inset text-sm' : '' }}"
                                 style="background: {{ $sc['color'] }};">
                                {{ $sc['class'] }}
                            </div>
                        @endforeach
                    </div>
                    <div class="inline-flex items-center gap-3 px-3 py-2 rounded-md border-2" style="background: {{ $certData['efficiency_class']['color'] }}15; border-color: {{ $certData['efficiency_class']['color'] }};">
                        <span class="text-3xl font-bold" style="color: {{ $certData['efficiency_class']['color'] }};">{{ $certData['efficiency_class']['class'] }}</span>
                        <span class="text-sm font-medium" style="color: {{ $certData['efficiency_class']['color'] }};">{{ $certData['efficiency_class']['label'] }}</span>
                        <span class="text-sm text-gray-500">({{ $certData['process_score'] }}%)</span>
                    </div>
                    @if($certData['has_run_data'] ?? false)
                        <div class="text-[10px] text-gray-400 mt-1">Basiert auf {{ $certData['run_count'] }} {{ $certData['run_count'] === 1 ? 'Durchlauf' : 'Durchläufen' }}</div>
                    @endif
                </div>

                {{-- Score Dimensions --}}
                @if(!empty($certData['score_dimensions']))
                    <div class="mb-5">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-800 mb-2 pb-1 border-b border-gray-200">Score-Dimensionen</h3>
                        @php
                            $dimColors = [
                                'design'     => '#8b5cf6',
                                'automation' => '#3b82f6',
                                'time'       => '#f59e0b',
                                'maturity'   => '#10b981',
                                'flow'       => '#06b6d4',
                            ];
                        @endphp
                        @foreach($certData['score_dimensions'] as $dimKey => $dim)
                            <div class="mb-2">
                                <div class="flex justify-between text-xs text-gray-600 mb-0.5">
                                    <span>{{ $dim['label'] }} <span class="text-gray-400">({{ $dim['weight'] }}%)</span></span>
                                    <span class="font-medium">{{ $dim['score'] }}</span>
                                </div>
                                <div class="w-full h-2.5 bg-gray-100 rounded-sm overflow-hidden">
                                    <div class="h-2.5 rounded-sm transition-all" style="width: {{ max(1, $dim['score']) }}%; background: {{ $dimColors[$dimKey] ?? '#94a3b8' }};"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- KPI Grid --}}
                @php
                    $certKpis = [
                        ['label' => 'Steps', 'value' => $certData['kpis']['total_steps'], 'detail' => 'Prozessschritte', 'color' => 'text-gray-800'],
                        ['label' => 'Durchlaufzeit', 'value' => $certData['kpis']['lead_time'], 'detail' => 'Min. (' . $certData['kpis']['total_duration'] . ' Arbeit + ' . $certData['kpis']['total_wait'] . ' Warten)', 'color' => 'text-gray-800'],
                        ['label' => 'Prozess-Score', 'value' => $certData['process_score'] . '%', 'detail' => ($certData['has_run_data'] ?? false) ? $certData['run_count'] . ' Durchläufe' : 'Ohne Durchlaufdaten', 'color' => ''],
                        ['label' => 'LLM-Quote', 'value' => $certData['kpis']['llm_quote'] . '%', 'detail' => $certData['kpis']['llm_count'] . ' von ' . $certData['kpis']['total_steps'] . ' Steps', 'color' => ''],
                    ];
                @endphp
                <div class="grid grid-cols-4 gap-0 mb-5">
                    @foreach($certKpis as $kpi)
                        <div class="p-3 border border-gray-200 text-center">
                            <div class="text-[10px] uppercase tracking-wider text-gray-400 font-bold">{{ $kpi['label'] }}</div>
                            <div class="text-xl font-bold {{ $kpi['color'] ?: 'text-gray-800' }} mt-1">{{ $kpi['value'] }}</div>
                            <div class="text-[10px] text-gray-500">{{ $kpi['detail'] }}</div>
                        </div>
                    @endforeach
                </div>

                {{-- Kostenanalyse --}}
                @if(isset($certData['cost_metrics']) && $certData['cost_metrics']['cost_per_run'] > 0)
                    <div class="mb-5">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-800 mb-2 pb-1 border-b border-gray-200">Kostenanalyse</h3>
                        <div class="grid grid-cols-4 gap-0">
                            <div class="p-3 border border-gray-200 text-center">
                                <div class="text-[10px] uppercase tracking-wider text-gray-400 font-bold">Häufigkeit</div>
                                <div class="text-sm font-bold text-gray-800 mt-1">{{ $certData['cost_metrics']['frequency_label'] }}</div>
                            </div>
                            <div class="p-3 border border-gray-200 text-center">
                                <div class="text-[10px] uppercase tracking-wider text-gray-400 font-bold">Kosten/Durchlauf</div>
                                <div class="text-lg font-bold text-gray-800 mt-1">{{ number_format($certData['cost_metrics']['cost_per_run'], 2, ',', '.') }} &euro;</div>
                            </div>
                            <div class="p-3 border border-gray-200 text-center">
                                <div class="text-[10px] uppercase tracking-wider text-gray-400 font-bold">Kosten/Monat</div>
                                <div class="text-lg font-bold text-gray-800 mt-1">{{ number_format($certData['cost_metrics']['cost_per_month'], 2, ',', '.') }} &euro;</div>
                            </div>
                            <div class="p-3 border border-gray-200 text-center">
                                <div class="text-[10px] uppercase tracking-wider text-gray-400 font-bold">Kosten/Jahr</div>
                                <div class="text-lg font-bold text-gray-800 mt-1">{{ number_format($certData['cost_metrics']['cost_per_year'], 2, ',', '.') }} &euro;</div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- COREFIT + Automation --}}
                <div class="grid grid-cols-2 gap-6 mb-5">
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-800 mb-2 pb-1 border-b border-gray-200">COREFIT-Verteilung</h3>
                        @php
                            $cfColors = ['core' => '#22c55e', 'context' => '#eab308', 'no_fit' => '#ef4444'];
                            $cfLabels = ['core' => 'Core', 'context' => 'Context', 'no_fit' => 'not Fit'];
                        @endphp
                        @foreach(['core', 'context', 'no_fit'] as $cf)
                            <div class="mb-2">
                                <div class="flex justify-between text-xs text-gray-600 mb-0.5">
                                    <span>{{ $cfLabels[$cf] }} ({{ $certData['corefit'][$cf]['count'] }})</span>
                                    <span class="font-medium">{{ $certData['corefit'][$cf]['percent'] }}%</span>
                                </div>
                                <div class="w-full h-3 bg-gray-100 rounded-sm overflow-hidden">
                                    <div class="h-3 rounded-sm" style="width: {{ max(1, $certData['corefit'][$cf]['percent']) }}%; background: {{ $cfColors[$cf] }};"></div>
                                </div>
                                <div class="text-[10px] text-gray-400 mt-0.5">{{ $certData['corefit'][$cf]['minutes'] }} Min.</div>
                            </div>
                        @endforeach
                    </div>
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-800 mb-2 pb-1 border-b border-gray-200">Automatisierungsgrad</h3>
                        @php
                            $alColors = ['human' => '#94a3b8', 'llm_assisted' => '#3b82f6', 'llm_autonomous' => '#22c55e', 'hybrid' => '#eab308'];
                            $alLabels = ['human' => 'Human', 'llm_assisted' => 'LLM-Assisted', 'llm_autonomous' => 'LLM-Autonomous', 'hybrid' => 'Hybrid'];
                        @endphp
                        @foreach(['human', 'llm_assisted', 'llm_autonomous', 'hybrid'] as $al)
                            <div class="mb-2">
                                <div class="flex justify-between text-xs text-gray-600 mb-0.5">
                                    <span>{{ $alLabels[$al] }} ({{ $certData['automation'][$al]['count'] }})</span>
                                    <span class="font-medium">{{ $certData['automation'][$al]['percent'] }}%</span>
                                </div>
                                <div class="w-full h-3 bg-gray-100 rounded-sm overflow-hidden">
                                    <div class="h-3 rounded-sm" style="width: {{ max(1, $certData['automation'][$al]['percent']) }}%; background: {{ $alColors[$al] }};"></div>
                                </div>
                                <div class="text-[10px] text-gray-400 mt-0.5">{{ $certData['automation'][$al]['minutes'] }} Min.</div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Handlungsbedarf --}}
                <div class="mb-5">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-gray-800 mb-2 pb-1 border-b border-gray-200">Handlungsbedarf</h3>
                    @if($certData['kpis']['total_steps'] > 0)
                        <div class="flex flex-wrap gap-2">
                            @if($certData['action_items']['eliminate'] > 0)
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-red-50 border border-red-200 text-xs font-medium text-red-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>{{ $certData['action_items']['eliminate'] }} eliminieren
                                </span>
                            @endif
                            @if($certData['action_items']['automate'] > 0)
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-orange-50 border border-orange-200 text-xs font-medium text-orange-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-orange-500"></span>{{ $certData['action_items']['automate'] }} automatisieren
                                </span>
                            @endif
                            @if($certData['action_items']['invest'] > 0)
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-blue-50 border border-blue-200 text-xs font-medium text-blue-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>{{ $certData['action_items']['invest'] }} investieren
                                </span>
                            @endif
                            @if($certData['action_items']['optimal'] > 0)
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-green-50 border border-green-200 text-xs font-medium text-green-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-600"></span>{{ $certData['action_items']['optimal'] }} optimal/gut
                                </span>
                            @endif
                        </div>
                    @else
                        <p class="text-sm text-gray-400">Keine Prozessschritte vorhanden</p>
                    @endif
                </div>

                {{-- COREFIT Analysis Texts --}}
                @php
                    $analysisBlocks = [
                        ['key' => 'target_description',    'label' => 'Prozess & Zielbild'],
                        ['key' => 'value_proposition',     'label' => 'Wertbeitrag'],
                        ['key' => 'process_landscape',     'label' => 'Prozesslandkarte'],
                        ['key' => 'corefit_classification_notes', 'label' => 'COREFIT Klassifizierung'],
                        ['key' => 'cost_analysis',         'label' => 'Kostenanalyse'],
                        ['key' => 'risk_assessment',       'label' => 'Risikobewertung'],
                        ['key' => 'improvement_levers',    'label' => 'Verbesserungshebel'],
                        ['key' => 'action_plan',           'label' => 'Maßnahmenplan'],
                        ['key' => 'standardization_notes', 'label' => 'Standardisierung'],
                    ];
                    $hasAnyText = collect($analysisBlocks)->contains(fn ($b) => !empty($certData['process'][$b['key']]));
                @endphp
                @if($hasAnyText)
                    <div class="mb-5">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-800 mb-3 pb-1 border-b border-gray-200">Analyse & Bewertung</h3>
                        <div class="space-y-3">
                            @foreach($analysisBlocks as $block)
                                @if(!empty($certData['process'][$block['key']]))
                                    <div>
                                        <div class="text-[10px] font-bold text-gray-700 mb-0.5">{{ $block['label'] }}</div>
                                        <div class="text-[10px] text-gray-500 leading-relaxed pl-1">{{ \Illuminate\Support\Str::limit($certData['process'][$block['key']], 600) }}</div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Steps List --}}
                @if(count($certData['steps_list']) > 0)
                    <div class="mb-5">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-800 mb-2 pb-1 border-b border-gray-200">Prozessschritte ({{ count($certData['steps_list']) }})</h3>
                        <table class="w-full text-left">
                            <thead>
                                <tr class="border-b border-gray-200">
                                    <th class="text-[9px] uppercase tracking-wider text-gray-400 font-bold py-1 px-1 w-6">#</th>
                                    <th class="text-[9px] uppercase tracking-wider text-gray-400 font-bold py-1 px-1">Schritt</th>
                                    <th class="text-[9px] uppercase tracking-wider text-gray-400 font-bold py-1 px-1 w-14">COREFIT</th>
                                    <th class="text-[9px] uppercase tracking-wider text-gray-400 font-bold py-1 px-1 w-20">Automation</th>
                                    <th class="text-[9px] uppercase tracking-wider text-gray-400 font-bold py-1 px-1 w-10 text-right">Min.</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($certData['steps_list'] as $step)
                                    <tr class="border-b border-gray-50">
                                        <td class="text-[10px] text-gray-400 font-mono py-0.5 px-1 text-right">{{ $step['position'] }}</td>
                                        <td class="text-[10px] text-gray-800 py-0.5 px-1">{{ \Illuminate\Support\Str::limit($step['name'], 50) }}</td>
                                        <td class="py-0.5 px-1">
                                            @if($step['corefit'] === 'core')
                                                <span class="inline-block px-1 py-px rounded text-[8px] font-bold bg-green-50 text-green-700">Core</span>
                                            @elseif($step['corefit'] === 'context')
                                                <span class="inline-block px-1 py-px rounded text-[8px] font-bold bg-yellow-50 text-yellow-700">Ctx</span>
                                            @else
                                                <span class="inline-block px-1 py-px rounded text-[8px] font-bold bg-red-50 text-red-700">NF</span>
                                            @endif
                                        </td>
                                        <td class="py-0.5 px-1">
                                            @if($step['automation'] === 'llm_autonomous')
                                                <span class="inline-block px-1 py-px rounded text-[8px] font-bold bg-green-50 text-green-700">Autonom</span>
                                            @elseif($step['automation'] === 'llm_assisted')
                                                <span class="inline-block px-1 py-px rounded text-[8px] font-bold bg-blue-50 text-blue-700">Assisted</span>
                                            @elseif($step['automation'] === 'hybrid')
                                                <span class="inline-block px-1 py-px rounded text-[8px] font-bold bg-yellow-50 text-yellow-700">Hybrid</span>
                                            @else
                                                <span class="inline-block px-1 py-px rounded text-[8px] font-bold bg-gray-50 text-gray-500">Human</span>
                                            @endif
                                        </td>
                                        <td class="text-[10px] text-gray-500 py-0.5 px-1 text-right">{{ $step['duration'] ?? '–' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                {{-- Improvements --}}
                @if(count($certData['improvements_list']) > 0)
                    <div class="mb-5">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-800 mb-2 pb-1 border-b border-gray-200">Verbesserungen ({{ count($certData['improvements_list']) }})</h3>
                        @php
                            $catLabels = ['cost' => 'Kosten', 'quality' => 'Qualität', 'speed' => 'Speed', 'risk' => 'Risiko', 'standardization' => 'Standard'];
                            $statusLabels = ['identified' => 'Erkannt', 'planned' => 'Geplant', 'in_progress' => 'In Arbeit', 'on_hold' => 'Pausiert', 'completed' => 'Umgesetzt', 'under_observation' => 'In Beobachtung', 'validated' => 'Validiert', 'failed' => 'Wirkungslos', 'rejected' => 'Abgelehnt'];
                        @endphp
                        <table class="w-full text-left">
                            <thead>
                                <tr class="border-b border-gray-200">
                                    <th class="text-[9px] uppercase tracking-wider text-gray-400 font-bold py-1 px-1">Titel</th>
                                    <th class="text-[9px] uppercase tracking-wider text-gray-400 font-bold py-1 px-1 w-16">Kategorie</th>
                                    <th class="text-[9px] uppercase tracking-wider text-gray-400 font-bold py-1 px-1 w-14">Priorität</th>
                                    <th class="text-[9px] uppercase tracking-wider text-gray-400 font-bold py-1 px-1 w-16">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($certData['improvements_list'] as $imp)
                                    <tr class="border-b border-gray-50">
                                        <td class="text-[10px] text-gray-800 py-0.5 px-1">{{ \Illuminate\Support\Str::limit($imp['title'], 55) }}</td>
                                        <td class="text-[10px] text-gray-500 py-0.5 px-1">{{ $catLabels[$imp['category']] ?? $imp['category'] }}</td>
                                        <td class="py-0.5 px-1">
                                            @if($imp['priority'] === 'critical')
                                                <span class="inline-block px-1 py-px rounded text-[8px] font-bold bg-red-50 text-red-700">Critical</span>
                                            @elseif($imp['priority'] === 'high')
                                                <span class="inline-block px-1 py-px rounded text-[8px] font-bold bg-orange-50 text-orange-700">High</span>
                                            @elseif($imp['priority'] === 'medium')
                                                <span class="inline-block px-1 py-px rounded text-[8px] font-bold bg-yellow-50 text-yellow-700">Medium</span>
                                            @else
                                                <span class="inline-block px-1 py-px rounded text-[8px] font-bold bg-gray-50 text-gray-500">Low</span>
                                            @endif
                                        </td>
                                        <td class="text-[9px] text-gray-500 py-0.5 px-1">{{ $statusLabels[$imp['status']] ?? $imp['status'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                {{-- Footer --}}
                <div class="border-t-2 border-gray-800 pt-2 flex justify-between text-[10px] text-gray-400">
                    <span>Erstellt am {{ $certData['meta']['generated_at_formatted'] }}</span>
                    <span>Prozessausweis &middot; {{ $certData['process']['team'] ?? '' }}</span>
                </div>
                <div class="text-[9px] text-gray-300 font-mono mt-1 break-all">
                    Prüfsumme: {{ $certData['meta']['checksum'] }}
                </div>
            </div>
        @endif
    </x-ui-page-container>

    {{-- ── Step Modal ──────────────────────────────────────── --}}
    <x-ui-modal wire:model="stepModalShow" size="lg">
        <x-slot name="header">
            {{ $editingStepId ? 'Schritt bearbeiten' : 'Neuer Schritt' }}
        </x-slot>

        <form wire:submit.prevent="storeStep" class="space-y-4">
            <div class="grid grid-cols-4 gap-4">
                <div class="col-span-1">
                    <x-ui-input-text name="position" label="Position" type="number" wire:model.live="stepForm.position" required min="1" placeholder="#" />
                </div>
                <div class="col-span-3">
                    <x-ui-input-text name="step_name" label="Name" wire:model.live="stepForm.name" required placeholder="Was passiert in diesem Schritt?" />
                </div>
            </div>

            <x-ui-input-textarea name="step_description" label="Beschreibung" wire:model.live="stepForm.description" rows="2" placeholder="Detailbeschreibung: Wer macht was, mit welchem Ergebnis?" />

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <x-ui-input-select
                        name="step_type"
                        label="Schritttyp"
                        :options="[
                            ['value' => 'task', 'label' => 'Task'],
                            ['value' => 'decision', 'label' => 'Decision'],
                            ['value' => 'event', 'label' => 'Event'],
                            ['value' => 'subprocess', 'label' => 'Subprocess'],
                        ]"
                        wire:model.live="stepForm.step_type"
                    />
                    <p class="text-xs text-[var(--ui-muted)] mt-1">Task = Arbeitsschritt, Decision = Entscheidung, Event = Ereignis, Subprocess = eingebetteter Teilprozess</p>
                </div>
                <div>
                    <x-ui-input-select
                        name="corefit_classification"
                        label="COREFIT Klassifikation"
                        :options="[
                            ['value' => 'core', 'label' => 'Core'],
                            ['value' => 'context', 'label' => 'Context'],
                            ['value' => 'no_fit', 'label' => 'not Fit'],
                        ]"
                        wire:model.live="stepForm.corefit_classification"
                    />
                    <p class="text-xs text-[var(--ui-muted)] mt-1">Core = Wertschöpfend, Context = Notwendig aber nicht wertschöpfend, No Fit = Eliminieren</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <x-ui-input-select
                        name="automation_level"
                        label="Automatisierungsgrad"
                        :options="[
                            ['value' => 'human', 'label' => 'Human'],
                            ['value' => 'llm_assisted', 'label' => 'LLM-Assisted'],
                            ['value' => 'llm_autonomous', 'label' => 'LLM-Autonomous'],
                            ['value' => 'hybrid', 'label' => 'Hybrid'],
                        ]"
                        wire:model.live="stepForm.automation_level"
                    />
                    <p class="text-xs text-[var(--ui-muted)] mt-1">Human = Mensch, LLM-Assisted = KI-unterstützt, LLM-Autonomous = KI-autonom, Hybrid = Mensch + KI gemeinsam</p>
                </div>
                <div>
                    <x-ui-input-select
                        name="complexity"
                        label="Komplexität"
                        :options="[
                            ['value' => '', 'label' => '– Keine –'],
                            ['value' => 'xs', 'label' => 'XS – Trivial (1)'],
                            ['value' => 's', 'label' => 'S – Einfach (2)'],
                            ['value' => 'm', 'label' => 'M – Mittel (3)'],
                            ['value' => 'l', 'label' => 'L – Komplex (5)'],
                            ['value' => 'xl', 'label' => 'XL – Sehr komplex (8)'],
                            ['value' => 'xxl', 'label' => 'XXL – Extrem komplex (13)'],
                        ]"
                        wire:model.live="stepForm.complexity"
                    />
                    <p class="text-xs text-[var(--ui-muted)] mt-1">T-Shirt-Größe mit Fibonacci-Punkten. Beeinflusst den Automation-Score.</p>
                </div>
            </div>

            @if(in_array($stepForm['automation_level'], ['llm_assisted', 'llm_autonomous', 'hybrid']))
                <div class="border border-[var(--ui-border)]/40 rounded-lg p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <label class="text-sm font-medium text-[var(--ui-secondary)]">MCP Tools</label>
                        <button type="button" wire:click="addLlmTool" class="text-xs text-[var(--ui-primary)] hover:underline">+ Tool hinzufügen</button>
                    </div>
                    @foreach($stepForm['llm_tools'] as $i => $tool)
                        <div wire:key="llm-tool-{{ $i }}" class="grid grid-cols-12 gap-2 items-start">
                            <div class="col-span-4">
                                <input type="text" wire:model="stepForm.llm_tools.{{ $i }}.tool_name" placeholder="Tool-Name (z.B. planner.projects.GET)" class="w-full text-sm rounded-md border-[var(--ui-border)] bg-[var(--ui-bg)] text-[var(--ui-fg)] shadow-sm focus:border-[var(--ui-primary)] focus:ring focus:ring-[var(--ui-primary)]/20 px-2.5 py-1.5" />
                                @error("stepForm.llm_tools.{$i}.tool_name") <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-span-4">
                                <input type="text" wire:model="stepForm.llm_tools.{{ $i }}.description" placeholder="Beschreibung" class="w-full text-sm rounded-md border-[var(--ui-border)] bg-[var(--ui-bg)] text-[var(--ui-fg)] shadow-sm focus:border-[var(--ui-primary)] focus:ring focus:ring-[var(--ui-primary)]/20 px-2.5 py-1.5" />
                            </div>
                            <div class="col-span-3">
                                <input type="text" wire:model="stepForm.llm_tools.{{ $i }}.mcp_server" placeholder="MCP Server" class="w-full text-sm rounded-md border-[var(--ui-border)] bg-[var(--ui-bg)] text-[var(--ui-fg)] shadow-sm focus:border-[var(--ui-primary)] focus:ring focus:ring-[var(--ui-primary)]/20 px-2.5 py-1.5" />
                            </div>
                            <div class="col-span-1 flex justify-center pt-1.5">
                                <button type="button" wire:click="removeLlmTool({{ $i }})" class="text-red-400 hover:text-red-600">
                                    @svg('heroicon-o-x-mark', 'w-4 h-4')
                                </button>
                            </div>
                        </div>
                    @endforeach
                    @if(empty($stepForm['llm_tools']))
                        <p class="text-xs text-[var(--ui-muted)] text-center py-2">Keine MCP Tools konfiguriert. Klicke oben auf "+ Tool hinzufügen".</p>
                    @endif
                </div>
            @endif

            <div class="grid grid-cols-3 gap-4">
                <x-ui-input-text name="duration_target_minutes" label="Dauer (Min.)" type="number" wire:model.live="stepForm.duration_target_minutes" min="0" placeholder="Aktive Bearbeitungszeit" />
                <x-ui-input-text name="wait_target_minutes" label="Wartezeit (Min.)" type="number" wire:model.live="stepForm.wait_target_minutes" min="0" placeholder="Liegezeit bis zum nächsten Schritt" />
                <x-ui-input-text name="external_cost_per_run" label="Externe Kosten/Lauf (&euro;)" type="number" wire:model.live="stepForm.external_cost_per_run" min="0" step="0.01" placeholder="Lizenzen, Material, Outsourcing" />
            </div>

            <div class="flex items-center">
                <input type="checkbox" wire:model.live="stepForm.is_active" id="step_is_active" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                <label for="step_is_active" class="ml-2 text-sm text-[var(--ui-secondary)]">Aktiv</label>
            </div>
        </form>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button type="button" variant="secondary-outline" wire:click="$set('stepModalShow', false)">Abbrechen</x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="storeStep">
                    @svg('heroicon-o-check', 'w-4 h-4 mr-2')
                    {{ $editingStepId ? 'Speichern' : 'Erstellen' }}
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>

    {{-- ── Flow Modal ──────────────────────────────────────── --}}
    <x-ui-modal wire:model="flowModalShow" size="lg">
        <x-slot name="header">
            {{ $editingFlowId ? 'Flow bearbeiten' : 'Neuer Flow' }}
        </x-slot>

        <form wire:submit.prevent="storeFlow" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <x-ui-input-select
                    name="from_step_id"
                    label="Von Schritt"
                    :options="$this->steps->map(fn($s) => ['value' => (string) $s->id, 'label' => $s->position . '. ' . $s->name])->toArray()"
                    nullable
                    nullLabel="– Auswählen –"
                    wire:model.live="flowForm.from_step_id"
                    required
                />
                <x-ui-input-select
                    name="to_step_id"
                    label="Nach Schritt"
                    :options="$this->steps->map(fn($s) => ['value' => (string) $s->id, 'label' => $s->position . '. ' . $s->name])->toArray()"
                    nullable
                    nullLabel="– Auswählen –"
                    wire:model.live="flowForm.to_step_id"
                    required
                />
            </div>

            <x-ui-input-text name="condition_label" label="Bedingung (optional)" wire:model.live="flowForm.condition_label" placeholder="z.B. Ja / Nein" />

            <div class="flex items-center">
                <input type="checkbox" wire:model.live="flowForm.is_default" id="flow_is_default" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                <label for="flow_is_default" class="ml-2 text-sm text-[var(--ui-secondary)]">Standard-Flow</label>
            </div>
        </form>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button type="button" variant="secondary-outline" wire:click="$set('flowModalShow', false)">Abbrechen</x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="storeFlow">
                    @svg('heroicon-o-check', 'w-4 h-4 mr-2')
                    {{ $editingFlowId ? 'Speichern' : 'Erstellen' }}
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>

    {{-- ── Trigger Modal ───────────────────────────────────── --}}
    <x-ui-modal wire:model="triggerModalShow" size="lg">
        <x-slot name="header">
            {{ $editingTriggerId ? 'Trigger bearbeiten' : 'Neuer Trigger' }}
        </x-slot>

        <form wire:submit.prevent="storeTrigger" class="space-y-4">
            <x-ui-input-text name="trigger_label" label="Label" wire:model.live="triggerForm.label" required />
            <x-ui-input-textarea name="trigger_description" label="Beschreibung" wire:model.live="triggerForm.description" rows="2" />

            <x-ui-input-select
                name="trigger_type"
                label="Trigger-Typ"
                :options="[
                    ['value' => 'manual', 'label' => 'Manuell'],
                    ['value' => 'scheduled', 'label' => 'Geplant'],
                    ['value' => 'event', 'label' => 'Event'],
                    ['value' => 'process_output', 'label' => 'Prozess-Output'],
                    ['value' => 'interlink', 'label' => 'Interlink'],
                ]"
                wire:model.live="triggerForm.trigger_type"
            />

            @if($triggerForm['trigger_type'] === 'scheduled')
                <x-ui-input-text name="schedule_expression" label="Schedule-Ausdruck (Cron)" wire:model.live="triggerForm.schedule_expression" placeholder="z.B. 0 8 * * MON" />
            @endif

            @if($triggerForm['trigger_type'] === 'event')
                <x-ui-input-select
                    name="entity_scope"
                    label="Quell-Zuordnung"
                    :options="[
                        ['value' => 'none', 'label' => 'Keine'],
                        ['value' => 'entity_type', 'label' => 'Entity-Typ (generisch)'],
                        ['value' => 'entity', 'label' => 'Konkrete Entity'],
                    ]"
                    wire:model.live="triggerForm.entity_scope"
                />
                <p class="text-xs text-[var(--ui-muted)] -mt-2">Entity-Typ = alle Entitäten dieses Typs lösen den Trigger aus. Konkrete Entity = nur eine bestimmte Entität.</p>

                @if($triggerForm['entity_scope'] === 'entity_type')
                    <x-ui-input-select
                        name="trigger_entity_type_id"
                        label="Entity-Typ"
                        :options="$this->availableEntityTypes->map(fn($t) => ['value' => (string) $t->id, 'label' => $t->name])->toArray()"
                        nullable
                        nullLabel="– Auswählen –"
                        wire:model.live="triggerForm.entity_type_id"
                        required
                    />
                @endif

                @if($triggerForm['entity_scope'] === 'entity')
                    <x-ui-input-select
                        name="trigger_entity_id"
                        label="Quell-Entity"
                        :options="$this->groupedEntityOptions"
                        nullable
                        nullLabel="– Auswählen –"
                        wire:model.live="triggerForm.entity_id"
                        required
                    />
                @endif
            @endif

            @if($triggerForm['trigger_type'] === 'process_output')
                <x-ui-input-select
                    name="source_process_id"
                    label="Quell-Prozess"
                    :options="$this->availableProcesses->map(fn($p) => ['value' => (string) $p->id, 'label' => $p->name])->toArray()"
                    nullable
                    nullLabel="– Auswählen –"
                    wire:model.live="triggerForm.source_process_id"
                />
            @endif
        </form>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button type="button" variant="secondary-outline" wire:click="$set('triggerModalShow', false)">Abbrechen</x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="storeTrigger">
                    @svg('heroicon-o-check', 'w-4 h-4 mr-2')
                    {{ $editingTriggerId ? 'Speichern' : 'Erstellen' }}
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>

    {{-- ── Output Modal ────────────────────────────────────── --}}
    <x-ui-modal wire:model="outputModalShow" size="lg">
        <x-slot name="header">
            {{ $editingOutputId ? 'Output bearbeiten' : 'Neuer Output' }}
        </x-slot>

        <form wire:submit.prevent="storeOutput" class="space-y-4">
            <x-ui-input-text name="output_label" label="Label" wire:model.live="outputForm.label" required />
            <x-ui-input-textarea name="output_description" label="Beschreibung" wire:model.live="outputForm.description" rows="2" />

            <x-ui-input-select
                name="output_type"
                label="Output-Typ"
                :options="[
                    ['value' => 'document', 'label' => 'Dokument'],
                    ['value' => 'data', 'label' => 'Daten'],
                    ['value' => 'notification', 'label' => 'Benachrichtigung'],
                    ['value' => 'process_trigger', 'label' => 'Prozess-Trigger'],
                    ['value' => 'interlink', 'label' => 'Interlink'],
                ]"
                wire:model.live="outputForm.output_type"
            />

            @if($outputForm['output_type'] === 'process_trigger')
                <x-ui-input-select
                    name="target_process_id"
                    label="Ziel-Prozess"
                    :options="$this->availableProcesses->map(fn($p) => ['value' => (string) $p->id, 'label' => $p->name])->toArray()"
                    nullable
                    nullLabel="– Auswählen –"
                    wire:model.live="outputForm.target_process_id"
                />
            @endif

            @if(in_array($outputForm['output_type'], ['document', 'data', 'notification']))
                <x-ui-input-select
                    name="output_entity_id"
                    label="Ziel-Entity (optional)"
                    :options="$this->groupedEntityOptions"
                    nullable
                    nullLabel="– Auswählen –"
                    wire:model.live="outputForm.entity_id"
                />
            @endif
        </form>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button type="button" variant="secondary-outline" wire:click="$set('outputModalShow', false)">Abbrechen</x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="storeOutput">
                    @svg('heroicon-o-check', 'w-4 h-4 mr-2')
                    {{ $editingOutputId ? 'Speichern' : 'Erstellen' }}
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>

    {{-- ── Snapshot Modal ──────────────────────────────────── --}}
    <x-ui-modal wire:model="snapshotModalShow" size="md">
        <x-slot name="header">
            Snapshot erstellen
        </x-slot>

        <form wire:submit.prevent="storeSnapshot" class="space-y-4">
            <x-ui-input-text name="snapshot_label" label="Label (optional)" wire:model.live="snapshotLabel" placeholder="z.B. Baseline, Nach Optimierung" />
            <p class="text-sm text-[var(--ui-muted)]">
                Ein Snapshot friert den aktuellen Zustand des Prozesses ein (inkl. Steps, Flows, Triggers, Outputs und strategische Felder).
            </p>
        </form>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button type="button" variant="secondary-outline" wire:click="$set('snapshotModalShow', false)">Abbrechen</x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="storeSnapshot">
                    @svg('heroicon-o-camera', 'w-4 h-4 mr-2')
                    Snapshot erstellen
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>

    {{-- ── Improvement Modal ───────────────────────────────── --}}
    <x-ui-modal wire:model="improvementModalShow" size="lg">
        <x-slot name="header">
            {{ $editingImprovementId ? 'Verbesserung bearbeiten' : 'Neue Verbesserung' }}
        </x-slot>

        <form wire:submit.prevent="storeImprovement" class="space-y-4">
            <x-ui-input-text name="imp_title" label="Titel" wire:model.live="improvementForm.title" required placeholder="z.B. Rechnungsprüfung automatisieren" />

            <div class="grid grid-cols-2 gap-4">
                <x-ui-input-select
                    name="imp_target_step"
                    label="Ziel-Step"
                    :options="array_merge(
                        [['value' => '', 'label' => '– Step wählen –']],
                        $this->steps->map(fn($s) => ['value' => (string) $s->id, 'label' => '#' . $s->position . ' ' . $s->name])->toArray()
                    )"
                    wire:model.live="improvementForm.target_step_id"
                />
                <x-ui-input-select
                    name="imp_category"
                    label="Kategorie"
                    :options="[
                        ['value' => 'speed', 'label' => 'Geschwindigkeit'],
                        ['value' => 'cost', 'label' => 'Kosten'],
                        ['value' => 'quality', 'label' => 'Qualität'],
                        ['value' => 'risk', 'label' => 'Risiko'],
                        ['value' => 'standardization', 'label' => 'Standardisierung'],
                    ]"
                    wire:model.live="improvementForm.category"
                />
            </div>

            {{-- Aktueller Step-Zustand (nur wenn Step gewählt) --}}
            @if($improvementForm['target_step_id'] !== '')
                @php
                    $targetStep = $this->steps->firstWhere('id', (int) $improvementForm['target_step_id']);
                @endphp
                @if($targetStep)
                    <div class="p-3 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                        <p class="text-xs font-medium text-[var(--ui-muted)] mb-2 uppercase tracking-wider">Aktuell: {{ $targetStep->name }}</p>
                        <div class="grid grid-cols-4 gap-3 text-sm">
                            <div>
                                <span class="text-[var(--ui-muted)]">Dauer:</span>
                                <span class="font-medium text-[var(--ui-secondary)]">{{ $targetStep->duration_target_minutes ?? '–' }} Min.</span>
                            </div>
                            <div>
                                <span class="text-[var(--ui-muted)]">Automation:</span>
                                <span class="font-medium text-[var(--ui-secondary)]">{{ $targetStep->automation_level?->label() ?? '–' }}</span>
                            </div>
                            <div>
                                <span class="text-[var(--ui-muted)]">Komplexität:</span>
                                <span class="font-medium text-[var(--ui-secondary)]">{{ $targetStep->complexity ? strtoupper($targetStep->complexity->value) : '–' }}</span>
                            </div>
                            <div>
                                <span class="text-[var(--ui-muted)]">Ext. Kosten:</span>
                                <span class="font-medium text-[var(--ui-secondary)]">{{ $targetStep->external_cost_per_run ? number_format((float) $targetStep->external_cost_per_run, 2, ',', '.') . ' €' : '–' }}</span>
                            </div>
                        </div>
                    </div>
                @endif
            @endif

            {{-- Projektion: Was ändert sich? (immer sichtbar) --}}
            <div class="grid grid-cols-2 gap-3">
                <x-ui-input-text name="proj_duration" label="Neue Dauer (Min.)" type="number" wire:model.live="improvementForm.projected_duration_target_minutes" min="0" placeholder="{{ $improvementForm['target_step_id'] !== '' ? ($this->steps->firstWhere('id', (int) $improvementForm['target_step_id'])?->duration_target_minutes ?? 'Unverändert') : 'Unverändert' }}" />
                <x-ui-input-text name="proj_hourly_rate" label="Neuer Stundensatz (&euro;)" type="number" wire:model.live="improvementForm.projected_hourly_rate" min="0" step="0.01" placeholder="{{ $this->form['hourly_rate'] !== '' ? $this->form['hourly_rate'] . ' (aktuell)' : 'Unverändert' }}" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <x-ui-input-select
                    name="savings_type"
                    label="Art der Einsparung"
                    :options="[
                        ['value' => '', 'label' => '– Nicht definiert –'],
                        ['value' => 'cost_reduction', 'label' => 'Echte Kosteneinsparung'],
                        ['value' => 'productivity_gain', 'label' => 'Produktivitätsgewinn'],
                        ['value' => 'both', 'label' => 'Beides'],
                    ]"
                    wire:model.live="improvementForm.savings_type"
                />
                <x-ui-input-text name="proj_external_cost" label="Neue externe Kosten/Lauf (&euro;)" type="number" wire:model.live="improvementForm.projected_external_cost_per_run" min="0" step="0.01" placeholder="{{ $improvementForm['target_step_id'] !== '' ? ($this->steps->firstWhere('id', (int) $improvementForm['target_step_id'])?->external_cost_per_run ?? '0.00') . ' (aktuell)' : 'Unverändert' }}" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <x-ui-input-select
                    name="proj_automation"
                    label="Neuer Automationsgrad"
                    :options="[
                        ['value' => '', 'label' => 'Unverändert'],
                        ['value' => 'human', 'label' => 'Human'],
                        ['value' => 'llm_assisted', 'label' => 'LLM-Assisted'],
                        ['value' => 'llm_autonomous', 'label' => 'LLM-Autonomous'],
                        ['value' => 'hybrid', 'label' => 'Hybrid'],
                    ]"
                    wire:model.live="improvementForm.projected_automation_level"
                />
                <x-ui-input-select
                    name="proj_complexity"
                    label="Neue Komplexität"
                    :options="[
                        ['value' => '', 'label' => 'Unverändert'],
                        ['value' => 'xs', 'label' => 'XS – Trivial'],
                        ['value' => 's', 'label' => 'S – Einfach'],
                        ['value' => 'm', 'label' => 'M – Mittel'],
                        ['value' => 'l', 'label' => 'L – Komplex'],
                        ['value' => 'xl', 'label' => 'XL – Sehr komplex'],
                        ['value' => 'xxl', 'label' => 'XXL – Extrem komplex'],
                    ]"
                    wire:model.live="improvementForm.projected_complexity"
                />

            <div class="grid grid-cols-2 gap-4">
                <x-ui-input-select
                    name="imp_priority"
                    label="Priorität"
                    :options="[
                        ['value' => 'low', 'label' => 'Niedrig'],
                        ['value' => 'medium', 'label' => 'Mittel'],
                        ['value' => 'high', 'label' => 'Hoch'],
                        ['value' => 'critical', 'label' => 'Kritisch'],
                    ]"
                    wire:model.live="improvementForm.priority"
                />
                <x-ui-input-select
                    name="imp_status"
                    label="Status"
                    :options="[
                        ['value' => 'identified', 'label' => 'Identifiziert'],
                        ['value' => 'planned', 'label' => 'Geplant'],
                        ['value' => 'in_progress', 'label' => 'In Arbeit'],
                        ['value' => 'on_hold', 'label' => 'Pausiert'],
                        ['value' => 'completed', 'label' => 'Umgesetzt'],
                        ['value' => 'under_observation', 'label' => 'In Beobachtung'],
                        ['value' => 'validated', 'label' => 'Validiert'],
                        ['value' => 'failed', 'label' => 'Wirkungslos'],
                        ['value' => 'rejected', 'label' => 'Abgelehnt'],
                    ]"
                    wire:model.live="improvementForm.status"
                />
            </div>
        </form>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button type="button" variant="secondary-outline" wire:click="$set('improvementModalShow', false)">Abbrechen</x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="storeImprovement">
                    @svg('heroicon-o-check', 'w-4 h-4 mr-2')
                    {{ $editingImprovementId ? 'Speichern' : 'Erstellen' }}
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>

</x-ui-page>
