<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Inference-Prompts', 'href' => route('organization.settings.inference-prompts.index')],
            ['label' => $prompt->name],
        ]">
            @if($this->isDirty)
                <x-ui-button variant="secondary-ghost" size="sm" wire:click="loadForm">
                    @svg('heroicon-o-x-mark', 'w-4 h-4')
                    <span>Abbrechen</span>
                </x-ui-button>
                <x-ui-button variant="primary" size="sm" wire:click="save">
                    @svg('heroicon-o-check', 'w-4 h-4')
                    <span>Speichern</span>
                </x-ui-button>
            @else
                <x-ui-button
                    variant="{{ $prompt->is_active ? 'danger-outline' : 'success' }}"
                    size="sm"
                    wire:click="toggleActive"
                    wire:confirm="{{ $prompt->is_active ? 'Prompt wirklich deaktivieren?' : 'Prompt wieder aktivieren?' }}"
                >
                    @if($prompt->is_active)
                        @svg('heroicon-o-pause', 'w-4 h-4')
                        <span>Deaktivieren</span>
                    @else
                        @svg('heroicon-o-play', 'w-4 h-4')
                        <span>Aktivieren</span>
                    @endif
                </x-ui-button>
            @endif
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
        @if(session()->has('message'))
            <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">{{ session('message') }}</div>
        @endif
        @if(session()->has('error'))
            <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">{{ session('error') }}</div>
        @endif

        <div class="space-y-6">
            {{-- Hero --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $prompt->name }}</h1>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-600/10">
                        {{ \Platform\Organization\Livewire\Settings\InferencePrompt\Show::VSM_OPTIONS[$prompt->vsm_system] ?? strtoupper($prompt->vsm_system) }}
                    </span>
                    @if($prompt->is_active)
                        <x-ui-badge variant="success" size="sm">Aktiv</x-ui-badge>
                    @else
                        <x-ui-badge variant="danger" size="sm">Inaktiv</x-ui-badge>
                    @endif
                    @php
                        $health = $prompt->health_status;
                        $healthDot = match($health) {
                            'healthy' => 'bg-emerald-500',
                            'stale' => 'bg-amber-500',
                            'error' => 'bg-red-500',
                            default => 'bg-slate-400',
                        };
                    @endphp
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-medium bg-[var(--ui-muted-5)] ring-1 ring-inset ring-[var(--ui-border)]/40" title="Health-Status">
                        <span class="w-2 h-2 rounded-full {{ $healthDot }}"></span>
                        {{ $health }}
                    </span>
                    @if($prompt->agentEntity)
                        <a href="{{ route('organization.entities.show', $prompt->agentEntity) }}" class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700 ring-1 ring-inset ring-slate-600/15 hover:bg-slate-200 transition">
                            @svg('heroicon-o-cpu-chip', 'w-3.5 h-3.5')
                            {{ $prompt->agentEntity->name }}
                        </a>
                    @endif
                </div>

                <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <div class="text-xs text-[var(--ui-muted)]">Interval</div>
                        <div class="font-medium tabular-nums text-[var(--ui-secondary)]">{{ $prompt->schedule_interval_hours ?? 72 }}h</div>
                    </div>
                    <div>
                        <div class="text-xs text-[var(--ui-muted)]">Letzter Run</div>
                        <div class="font-medium text-[var(--ui-secondary)]" title="{{ $prompt->last_evaluated_at?->format('d.m.Y H:i:s') }}">
                            {{ $prompt->last_evaluated_at?->diffForHumans() ?? '–' }}
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-[var(--ui-muted)]">Runs gesamt</div>
                        <div class="font-medium tabular-nums text-[var(--ui-secondary)]">{{ $prompt->run_count ?? 0 }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-[var(--ui-muted)]">Severity (Default)</div>
                        <div class="font-medium text-[var(--ui-secondary)]">{{ $prompt->default_severity }}</div>
                    </div>
                </div>

                @if($prompt->last_error)
                    <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded">
                        <div class="flex items-start justify-between gap-2 mb-1">
                            <div class="text-[10px] font-bold text-red-900 uppercase tracking-wider">Letzter Fehler</div>
                            <button type="button" wire:click="clearLastError" wire:confirm="Fehler-Marker entfernen? Health-Status springt wieder auf healthy/stale." class="text-[10px] text-red-700 hover:text-red-900 underline">Entfernen</button>
                        </div>
                        <pre class="text-xs text-red-900 font-mono whitespace-pre-wrap break-words">{{ $prompt->last_error }}</pre>
                    </div>
                @endif
            </div>

            {{-- Edit Form --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Konfiguration</h2>

                <div class="space-y-4">
                    <x-ui-input-text name="name" label="Name" wire:model.live="form.name" required />

                    <x-ui-input-textarea name="description" label="Beschreibung" wire:model.live="form.description" rows="2" placeholder="Was prüft dieser Prompt?" />

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">VSM-System</label>
                            <select wire:model.live="form.vsm_system" class="w-full rounded-md border-[var(--ui-border)] text-sm">
                                @foreach(\Platform\Organization\Livewire\Settings\InferencePrompt\Show::VSM_OPTIONS as $code => $label)
                                    <option value="{{ $code }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Default-Severity</label>
                            <select wire:model.live="form.default_severity" class="w-full rounded-md border-[var(--ui-border)] text-sm">
                                @foreach(\Platform\Organization\Livewire\Settings\InferencePrompt\Show::SEVERITY_OPTIONS as $code => $label)
                                    <option value="{{ $code }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <x-ui-input-text name="schedule_interval_hours" label="Interval (Stunden)" wire:model.live="form.schedule_interval_hours" type="number" placeholder="z.B. 72" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Prompt-Template</label>
                        <textarea wire:model.live="form.prompt_template" rows="8" class="w-full rounded-md border-[var(--ui-border)] text-sm font-mono" placeholder="Die diagnostische Frage, die der LLM beantworten soll..."></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">Data Sources</label>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                            @foreach(\Platform\Organization\Livewire\Settings\InferencePrompt\Show::DATA_SOURCE_OPTIONS as $code => $label)
                                <label class="flex items-center gap-2 text-sm cursor-pointer">
                                    <input type="checkbox" wire:model.live="form.data_sources" value="{{ $code }}" class="rounded border-[var(--ui-border)]" />
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Scope-Type</label>
                            <select wire:model.live="form.scope_type" class="w-full rounded-md border-[var(--ui-border)] text-sm">
                                @foreach(\Platform\Organization\Livewire\Settings\InferencePrompt\Show::SCOPE_OPTIONS as $code => $label)
                                    <option value="{{ $code }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <x-ui-input-text name="dimension" label="Dimension (optional)" wire:model.live="form.dimension" placeholder="z.B. quality, energy, …" />

                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Agent</label>
                            <select wire:model.live="form.agent_entity_id" class="w-full rounded-md border-[var(--ui-border)] text-sm">
                                <option value="">– kein Agent –</option>
                                @foreach($this->agentOptions as $option)
                                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Recent Runs --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-[var(--ui-secondary)]">Letzte Runs</h2>
                    <span class="text-xs text-[var(--ui-muted)]">{{ $this->recentRuns->count() }} Runs</span>
                </div>

                @if($this->recentRuns->isEmpty())
                    <div class="text-sm text-[var(--ui-muted)] py-3">Noch keine Runs für diesen Prompt.</div>
                @else
                    <div class="space-y-1.5">
                        @foreach($this->recentRuns as $run)
                            @php
                                $statusVariant = match($run->status) {
                                    'completed' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'ring' => 'ring-emerald-600/20'],
                                    'failed' => ['bg' => 'bg-red-50', 'text' => 'text-red-700', 'ring' => 'ring-red-600/20'],
                                    'running' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-700', 'ring' => 'ring-amber-600/20'],
                                    default => ['bg' => 'bg-slate-50', 'text' => 'text-slate-600', 'ring' => 'ring-slate-600/15'],
                                };
                            @endphp
                            <a href="{{ route('organization.inference-runs.show', $run) }}" class="block border border-[var(--ui-border)]/40 rounded-md p-2.5 hover:border-[var(--ui-primary)]/60 hover:bg-[var(--ui-primary-5)] transition">
                                <div class="flex items-center gap-3 text-sm">
                                    <span class="text-xs font-mono text-[var(--ui-muted)] tabular-nums w-14">#{{ $run->id }}</span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium {{ $statusVariant['bg'] }} {{ $statusVariant['text'] }} ring-1 ring-inset {{ $statusVariant['ring'] }}">{{ $run->status }}</span>
                                    <span class="text-xs text-[var(--ui-muted)]" title="{{ $run->created_at->format('d.m.Y H:i:s') }}">{{ $run->created_at->diffForHumans() }}</span>
                                    <div class="flex items-center gap-3 ml-auto text-[11px] text-[var(--ui-muted)]">
                                        <span>{{ $run->signals_created ?? 0 }} sig</span>
                                        <span>{{ $run->entities_analyzed ?? 0 }} ent</span>
                                        @if($run->duration_ms > 0)
                                            <span class="tabular-nums">{{ $run->duration_ms < 1000 ? $run->duration_ms.'ms' : number_format($run->duration_ms / 1000, 1, ',', '').'s' }}</span>
                                        @endif
                                    </div>
                                    @svg('heroicon-o-chevron-right', 'w-3.5 h-3.5 text-[var(--ui-muted)]')
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Recent Signals --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-[var(--ui-secondary)]">Erzeugte Signale</h2>
                    <span class="text-xs text-[var(--ui-muted)]">{{ $this->recentSignals->count() }}</span>
                </div>

                @if($this->recentSignals->isEmpty())
                    <div class="text-sm text-[var(--ui-muted)] py-3">Noch keine Signale aus diesem Prompt.</div>
                @else
                    <div class="space-y-1.5">
                        @foreach($this->recentSignals as $signal)
                            @php
                                $sevVariant = match($signal->severity) {
                                    'algedonic' => ['bg' => 'bg-red-100', 'text' => 'text-red-800', 'ring' => 'ring-red-600/30'],
                                    'critical' => ['bg' => 'bg-red-50', 'text' => 'text-red-700', 'ring' => 'ring-red-600/20'],
                                    'warning' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-700', 'ring' => 'ring-amber-600/20'],
                                    default => ['bg' => 'bg-slate-50', 'text' => 'text-slate-600', 'ring' => 'ring-slate-600/15'],
                                };
                            @endphp
                            <div class="border border-[var(--ui-border)]/40 rounded-md p-2.5">
                                <div class="flex items-start gap-3 text-sm">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium {{ $sevVariant['bg'] }} {{ $sevVariant['text'] }} ring-1 ring-inset {{ $sevVariant['ring'] }} flex-shrink-0 mt-0.5">{{ $signal->severity }}</span>
                                    @if($signal->entity)
                                        <a href="{{ route('organization.entities.show', $signal->entity) }}" class="text-xs font-medium text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] hover:underline flex-shrink-0 mt-0.5">{{ $signal->entity->name }}</a>
                                    @endif
                                    <span class="text-xs text-[var(--ui-secondary)] flex-1 line-clamp-2">{{ $signal->message }}</span>
                                    <span class="text-[10px] text-[var(--ui-muted)] flex-shrink-0 mt-0.5">{{ $signal->created_at->diffForHumans() }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>
