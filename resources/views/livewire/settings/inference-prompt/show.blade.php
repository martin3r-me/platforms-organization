@php
    $showClass = \Platform\Organization\Livewire\Settings\InferencePrompt\Show::class;
@endphp
<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$prompt->name" icon="heroicon-o-sparkles" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Einstellungen" width="w-64" :defaultOpen="true" side="left">
            @include('organization::livewire.settings.partials.nav', ['aktiv' => 'inference-prompts'])
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organisation', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Inference-Prompts', 'href' => route('organization.settings.inference-prompts.index')],
            ['label' => $prompt->name],
        ]">
            <div class="flex items-center gap-2">
                @if ($this->isDirty)
                    <x-nx-button variant="primary" size="sm" wire:click="save">
                        @svg('heroicon-o-check', 'w-4 h-4')
                        <span>Speichern</span>
                    </x-nx-button>
                @endif
                <x-nx-dropdown>
                    @if ($this->isDirty)
                        <x-nx-dropdown-item wire:click="loadForm">
                            @svg('heroicon-o-arrow-uturn-left', 'w-4 h-4')
                            Änderungen verwerfen
                        </x-nx-dropdown-item>
                        <x-nx-dropdown-divider />
                    @endif
                    @if ($prompt->is_active)
                        <x-nx-dropdown-item variant="danger" wire:click="toggleActive" wire:confirm="Prompt wirklich deaktivieren?">
                            @svg('heroicon-o-pause', 'w-4 h-4')
                            Deaktivieren
                        </x-nx-dropdown-item>
                    @else
                        <x-nx-dropdown-item wire:click="toggleActive" wire:confirm="Prompt wieder aktivieren?">
                            @svg('heroicon-o-play', 'w-4 h-4')
                            Aktivieren
                        </x-nx-dropdown-item>
                    @endif
                </x-nx-dropdown>
            </div>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
        <div class="space-y-5">
            @if (session()->has('message'))
                <x-nx-callout variant="success">{{ session('message') }}</x-nx-callout>
            @endif
            @if (session()->has('error'))
                <x-nx-callout variant="danger">{{ session('error') }}</x-nx-callout>
            @endif

            {{-- Kopf: Identität + Zustand --}}
            @php
                $health = $prompt->health_status;
                $healthVariant = match ($health) {
                    'healthy' => 'success',
                    'stale' => 'warning',
                    'error' => 'danger',
                    default => 'neutral',
                };
            @endphp
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="m-0 text-2xl font-semibold tracking-tight text-[color:var(--nx-text)]">{{ $prompt->name }}</h1>
                <x-nx-badge>{{ $showClass::VSM_OPTIONS[$prompt->vsm_system] ?? strtoupper($prompt->vsm_system) }}</x-nx-badge>
                <x-nx-badge :variant="$prompt->is_active ? 'success' : 'neutral'" dot>{{ $prompt->is_active ? 'Aktiv' : 'Inaktiv' }}</x-nx-badge>
                <x-nx-badge :variant="$healthVariant" dot title="Health-Status">{{ $health }}</x-nx-badge>
                @if ($prompt->agentEntity)
                    <x-nx-badge :href="route('organization.entities.show', $prompt->agentEntity)">
                        @svg('heroicon-o-cpu-chip', 'w-3.5 h-3.5')
                        {{ $prompt->agentEntity->name }}
                    </x-nx-badge>
                @endif
                <span class="ml-auto text-xs text-[color:var(--nx-faint)]">angelegt {{ $prompt->created_at?->format('d.m.Y') }}</span>
            </div>

            <div class="grid grid-cols-2 gap-4 text-sm md:grid-cols-4">
                <div>
                    <div class="text-xs text-[color:var(--nx-muted)]">Intervall</div>
                    <div class="font-medium tabular-nums text-[color:var(--nx-text)]">{{ $prompt->schedule_interval_hours ?? 72 }} h</div>
                </div>
                <div>
                    <div class="text-xs text-[color:var(--nx-muted)]">Letzter Run</div>
                    <div class="font-medium text-[color:var(--nx-text)]" title="{{ $prompt->last_evaluated_at?->format('d.m.Y H:i:s') }}">
                        {{ $prompt->last_evaluated_at?->diffForHumans() ?? '–' }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-[color:var(--nx-muted)]">Runs gesamt</div>
                    <div class="font-medium tabular-nums text-[color:var(--nx-text)]">{{ $prompt->run_count ?? 0 }}</div>
                </div>
                <div>
                    <div class="text-xs text-[color:var(--nx-muted)]">Severity (Default)</div>
                    <div class="font-medium text-[color:var(--nx-text)]">{{ $prompt->default_severity }}</div>
                </div>
            </div>

            @if ($prompt->last_error)
                <x-nx-callout variant="danger" title="Letzter Fehler">
                    <pre class="m-0 mt-1 whitespace-pre-wrap break-words font-mono text-xs text-[color:var(--nx-text)]">{{ $prompt->last_error }}</pre>
                    <div class="mt-2">
                        <x-nx-button variant="danger" size="sm" wire:click="clearLastError" wire:confirm="Fehler-Marker entfernen? Health-Status springt wieder auf healthy/stale.">
                            @svg('heroicon-o-x-mark', 'w-4 h-4')
                            <span>Fehler entfernen</span>
                        </x-nx-button>
                    </div>
                </x-nx-callout>
            @endif

            <x-nx-panel title="Grunddaten">
                <div class="space-y-3 p-4">
                    <x-nx-input-text name="name" label="Name" wire:model.live="form.name" errorKey="form.name" required />
                    <x-nx-input-textarea name="description" label="Beschreibung" wire:model.live="form.description" errorKey="form.description" rows="2" placeholder="Was prüft dieser Prompt?" />
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                        <x-nx-input-select name="vsm_system" label="VSM-System" wire:model.live="form.vsm_system" errorKey="form.vsm_system" :options="$showClass::VSM_OPTIONS" />
                        <x-nx-input-select name="default_severity" label="Default-Severity" wire:model.live="form.default_severity" errorKey="form.default_severity" :options="$showClass::SEVERITY_OPTIONS" />
                        <x-nx-input-number name="schedule_interval_hours" label="Intervall (Stunden)" wire:model.live="form.schedule_interval_hours" errorKey="form.schedule_interval_hours" min="1" max="8760" placeholder="z. B. 72" />
                    </div>
                </div>
            </x-nx-panel>

            <x-nx-panel title="Prompt-Template" subtitle="Die diagnostische Frage, die das LLM beantworten soll">
                <div class="p-4">
                    <x-nx-input-textarea name="prompt_template" wire:model.live="form.prompt_template" errorKey="form.prompt_template" rows="16" class="font-mono" placeholder="Die diagnostische Frage, die das LLM beantworten soll…" />
                </div>
            </x-nx-panel>

            <x-nx-panel title="Datenquellen">
                <div class="p-4">
                    <div class="grid grid-cols-2 gap-2.5 md:grid-cols-4">
                        @foreach ($showClass::DATA_SOURCE_OPTIONS as $code => $label)
                            <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-[color:var(--nx-text)]">
                                <input type="checkbox" value="{{ $code }}" wire:model.live="form.data_sources" class="h-4 w-4 rounded-[4px] accent-[var(--nx-accent)]" />
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('form.data_sources')
                        <p class="mt-1 text-xs text-[color:var(--nx-danger)]">{{ $message }}</p>
                    @enderror
                </div>
            </x-nx-panel>

            <x-nx-panel title="Geltungsbereich">
                <div class="p-4">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                        <x-nx-input-select name="scope_type" label="Scope-Typ" wire:model.live="form.scope_type" errorKey="form.scope_type" :options="$showClass::SCOPE_OPTIONS" />
                        <x-nx-input-text name="dimension" label="Dimension (optional)" wire:model.live="form.dimension" errorKey="form.dimension" placeholder="z. B. quality, energy, …" />
                        <x-nx-input-select name="agent_entity_id" label="Agent" wire:model.live="form.agent_entity_id" errorKey="form.agent_entity_id" :options="$this->agentOptions" nullable nullLabel="– kein Agent –" />
                    </div>
                </div>
            </x-nx-panel>

            {{-- Letzte Runs --}}
            <x-nx-panel title="Letzte Runs" :subtitle="$this->recentRuns->count() . ' Runs'" flush>
                @if ($this->recentRuns->isEmpty())
                    <x-nx-empty icon="heroicon-o-play">Noch keine Runs für diesen Prompt.</x-nx-empty>
                @else
                    <div class="divide-y divide-[color:var(--nx-line)]">
                        @foreach ($this->recentRuns as $run)
                            @php
                                $runVariant = match ($run->status) {
                                    'completed' => 'success',
                                    'failed' => 'danger',
                                    'running' => 'warning',
                                    default => 'neutral',
                                };
                            @endphp
                            <a href="{{ route('organization.inference-runs.show', $run) }}" wire:key="run-{{ $run->id }}" class="flex items-center gap-3 px-4 py-2.5 text-sm transition-colors hover:bg-[color:var(--nx-hover)]">
                                <span class="w-14 font-mono text-xs tabular-nums text-[color:var(--nx-faint)]">#{{ $run->id }}</span>
                                <x-nx-badge :variant="$runVariant" dot>{{ $run->status }}</x-nx-badge>
                                <span class="text-xs text-[color:var(--nx-muted)]" title="{{ $run->created_at->format('d.m.Y H:i:s') }}">{{ $run->created_at->diffForHumans() }}</span>
                                <div class="ml-auto flex items-center gap-3 text-xs text-[color:var(--nx-muted)]">
                                    <span>{{ $run->signals_created ?? 0 }} sig</span>
                                    <span>{{ $run->entities_analyzed ?? 0 }} ent</span>
                                    @if ($run->duration_ms > 0)
                                        <span class="tabular-nums">{{ $run->duration_ms < 1000 ? $run->duration_ms.'ms' : number_format($run->duration_ms / 1000, 1, ',', '').'s' }}</span>
                                    @endif
                                </div>
                                @svg('heroicon-o-chevron-right', 'w-3.5 h-3.5 text-[color:var(--nx-faint)]')
                            </a>
                        @endforeach
                    </div>
                @endif
            </x-nx-panel>

            {{-- Erzeugte Signale --}}
            <x-nx-panel title="Erzeugte Signale" :subtitle="(string) $this->recentSignals->count()" flush>
                @if ($this->recentSignals->isEmpty())
                    <x-nx-empty icon="heroicon-o-signal">Noch keine Signale aus diesem Prompt.</x-nx-empty>
                @else
                    <div class="divide-y divide-[color:var(--nx-line)]">
                        @foreach ($this->recentSignals as $signal)
                            @php
                                $sevVariant = match ($signal->severity) {
                                    'algedonic', 'critical' => 'danger',
                                    'warning' => 'warning',
                                    default => 'neutral',
                                };
                            @endphp
                            <div wire:key="sig-{{ $signal->id }}" class="flex items-start gap-3 px-4 py-2.5 text-sm">
                                <x-nx-badge :variant="$sevVariant" class="mt-0.5 shrink-0">{{ $signal->severity }}</x-nx-badge>
                                @if ($signal->entity)
                                    <a href="{{ route('organization.entities.show', $signal->entity) }}" class="mt-0.5 shrink-0 text-xs font-medium text-[color:var(--nx-text)] hover:underline">{{ $signal->entity->name }}</a>
                                @endif
                                <span class="line-clamp-2 flex-1 text-xs text-[color:var(--nx-text)]">{{ $signal->message }}</span>
                                <span class="mt-0.5 shrink-0 text-xs text-[color:var(--nx-faint)]">{{ $signal->created_at->diffForHumans() }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-nx-panel>
        </div>
    </x-ui-page-container>
</x-ui-page>
