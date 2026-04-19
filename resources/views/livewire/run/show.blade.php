<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Prozesse', 'href' => route('organization.processes.index')],
            ['label' => $process->name, 'href' => route('organization.processes.show', $process)],
            ['label' => 'Durchlauf ' . $run->started_at->format('d.m.Y H:i')],
        ]">
            <div class="flex-1"></div>

            @if($this->isActive)
                <x-ui-button variant="danger-outline" size="sm" wire:click="cancelRun" wire:confirm="Durchlauf wirklich abbrechen?">
                    @svg('heroicon-o-x-mark', 'w-4 h-4')
                    <span>Abbrechen</span>
                </x-ui-button>
            @endif

            <x-ui-button variant="danger-outline" size="sm" wire:click="deleteRun" wire:confirm="Durchlauf wirklich löschen?">
                @svg('heroicon-o-trash', 'w-4 h-4')
                <span>Löschen</span>
            </x-ui-button>

            <a href="{{ route('organization.processes.show', $process) }}?tab=runs" wire:navigate>
                <x-ui-button variant="secondary-outline" size="sm">
                    @svg('heroicon-o-arrow-left', 'w-4 h-4')
                    <span>Zurück zum Prozess</span>
                </x-ui-button>
            </a>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <div class="space-y-6">
            {{-- Status --}}
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-[var(--ui-{{ $run->status->color() }})]/10 mb-3">
                    @if($run->status === \Platform\Organization\Enums\RunStatus::ACTIVE)
                        @svg('heroicon-o-play', 'w-8 h-8 text-[var(--ui-warning)]')
                    @elseif($run->status === \Platform\Organization\Enums\RunStatus::COMPLETED)
                        @svg('heroicon-o-check-circle', 'w-8 h-8 text-[var(--ui-success)]')
                    @else
                        @svg('heroicon-o-x-circle', 'w-8 h-8 text-[var(--ui-muted)]')
                    @endif
                </div>
                <x-ui-badge variant="{{ $run->status->color() }}" size="lg">{{ $run->status->label() }}</x-ui-badge>
            </div>

            {{-- Progress --}}
            @php $prog = $this->progress; @endphp
            <div>
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-medium text-[var(--ui-muted)]">Fortschritt</span>
                    <span class="text-sm font-bold text-[var(--ui-secondary)]">{{ $prog['percent'] }}%</span>
                </div>
                <div class="w-full bg-[var(--ui-muted-20)] rounded-full h-3">
                    <div class="h-3 rounded-full bg-[var(--ui-{{ $run->status->color() }})] transition-all" style="width: {{ $prog['percent'] }}%"></div>
                </div>
                <p class="text-xs text-[var(--ui-muted)] mt-1 text-center">{{ $prog['done'] }} / {{ $prog['total'] }} Schritte</p>
            </div>

            {{-- Timestamps --}}
            <div class="space-y-3">
                <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                    <span class="text-xs text-[var(--ui-muted)]">Gestartet am</span>
                    <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $run->started_at->format('d.m.Y H:i') }}</div>
                </div>
                @if($run->completed_at)
                    <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                        <span class="text-xs text-[var(--ui-muted)]">Abgeschlossen am</span>
                        <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $run->completed_at->format('d.m.Y H:i') }}</div>
                    </div>
                @endif
                @if($run->cancelled_at)
                    <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                        <span class="text-xs text-[var(--ui-muted)]">Abgebrochen am</span>
                        <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $run->cancelled_at->format('d.m.Y H:i') }}</div>
                    </div>
                @endif
                @if($run->user)
                    <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                        <span class="text-xs text-[var(--ui-muted)]">Erstellt von</span>
                        <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $run->user->name }}</div>
                    </div>
                @endif
            </div>

            {{-- Time Summary --}}
            <div>
                <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Zeiten</h3>
                <div class="space-y-2">
                    <div class="flex items-center justify-between py-2 px-3 bg-[var(--ui-muted-5)] rounded-lg">
                        <span class="text-xs text-[var(--ui-muted)]">Aktive Zeit</span>
                        <div class="text-right">
                            <span class="text-sm font-bold text-[var(--ui-secondary)]">{{ $this->totalActive }} Min.</span>
                            @if($this->targetActive > 0)
                                <span class="text-[10px] text-[var(--ui-muted)] block">Soll: {{ $this->targetActive }} Min.</span>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center justify-between py-2 px-3 bg-[var(--ui-muted-5)] rounded-lg">
                        <span class="text-xs text-[var(--ui-muted)]">Wartezeit</span>
                        <div class="text-right">
                            <span class="text-sm font-bold text-[var(--ui-secondary)]">{{ $this->totalWait }} Min.</span>
                            @if($this->targetWait > 0)
                                <span class="text-[10px] text-[var(--ui-muted)] block">Soll: {{ $this->targetWait }} Min.</span>
                            @endif
                        </div>
                    </div>
                    @if($this->targetActive > 0)
                        @php
                            $activeDelta = $this->totalActive - $this->targetActive;
                            $waitDelta = $this->totalWait - $this->targetWait;
                        @endphp
                        <div class="flex items-center justify-between py-2 px-3 bg-[var(--ui-muted-5)] rounded-lg">
                            <span class="text-xs text-[var(--ui-muted)]">Abweichung (aktiv)</span>
                            <span class="text-sm font-bold {{ $activeDelta > 0 ? 'text-red-500' : 'text-green-600' }}">
                                {{ $activeDelta > 0 ? '+' : '' }}{{ $activeDelta }} Min.
                            </span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Notes --}}
            @if($run->notes)
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-2">Notizen</h3>
                    <p class="text-sm text-[var(--ui-muted)]">{{ $run->notes }}</p>
                </div>
            @endif
        </div>
    </x-slot>

    {{-- Main content: Step Checklist --}}
    <div class="space-y-3">
        @foreach($this->runSteps as $rs)
            @php
                $isCompleted = $rs->status === \Platform\Organization\Enums\RunStepStatus::COMPLETED;
                $isSkipped = $rs->status === \Platform\Organization\Enums\RunStepStatus::SKIPPED;
                $isPending = $rs->status === \Platform\Organization\Enums\RunStepStatus::PENDING;
            @endphp
            <div
                x-data="{ editing: false, activeDur: '', waitDur: '' }"
                class="bg-white rounded-xl border border-[var(--ui-border)]/60 {{ $isCompleted ? 'bg-green-50/30 border-green-200/60' : ($isSkipped ? 'bg-[var(--ui-muted-5)]/50 border-[var(--ui-border)]/40' : '') }} {{ $isPending && $this->isActive ? 'hover:border-[var(--ui-info)] hover:shadow-sm cursor-pointer' : '' }} transition-all"
                @if($isPending && $this->isActive) @click="editing = !editing" @endif
            >
                <div class="flex items-start gap-4 px-6 py-5">
                    {{-- Status indicator --}}
                    <div class="flex-shrink-0 mt-0.5">
                        @if($isCompleted)
                            <div class="w-9 h-9 rounded-full bg-[var(--ui-success)] flex items-center justify-center shadow-sm">
                                @svg('heroicon-s-check', 'w-5 h-5 text-white')
                            </div>
                        @elseif($isSkipped)
                            <div class="w-9 h-9 rounded-full bg-[var(--ui-muted)]/60 flex items-center justify-center">
                                @svg('heroicon-s-minus', 'w-5 h-5 text-white')
                            </div>
                        @elseif($isPending && $this->isActive)
                            <div class="w-9 h-9 rounded-full border-2 border-[var(--ui-border)] hover:border-[var(--ui-success)] transition-colors flex items-center justify-center">
                                <span class="text-xs font-bold text-[var(--ui-muted)]">{{ $rs->position }}</span>
                            </div>
                        @else
                            <div class="w-9 h-9 rounded-full border-2 border-[var(--ui-border)]/50 flex items-center justify-center">
                                <span class="text-xs text-[var(--ui-muted)]/50">{{ $rs->position }}</span>
                            </div>
                        @endif
                    </div>

                    {{-- Content --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="text-base font-medium {{ $isSkipped ? 'line-through text-[var(--ui-muted)]' : ($isCompleted ? 'text-[var(--ui-muted)]' : 'text-[var(--ui-secondary)]') }}">
                                    {{ $rs->processStep?->name ?? 'Step' }}
                                </span>
                                @if($isSkipped)
                                    <span class="text-xs text-[var(--ui-muted)] italic">Übersprungen</span>
                                @endif
                            </div>
                            @if($rs->processStep?->duration_target_minutes)
                                <span class="text-xs text-[var(--ui-muted)] flex-shrink-0 ml-2 flex items-center gap-1">
                                    @svg('heroicon-o-clock', 'w-3.5 h-3.5')
                                    Soll: {{ $rs->processStep->duration_target_minutes }} Min.
                                    @if($rs->processStep->wait_target_minutes)
                                        <span class="text-[var(--ui-muted)]/60">+ {{ $rs->processStep->wait_target_minutes }} Warten</span>
                                    @endif
                                </span>
                            @endif
                        </div>

                        @if($rs->processStep?->description)
                            <p class="text-sm text-[var(--ui-muted)] mt-1">{{ $rs->processStep->description }}</p>
                        @endif

                        {{-- Completed/skipped details --}}
                        @if($isCompleted || $isSkipped)
                            <div class="flex flex-wrap gap-4 mt-2.5">
                                @if($rs->active_duration_minutes !== null)
                                    <span class="text-xs text-[var(--ui-muted)] flex items-center gap-1.5 py-1 px-2.5 bg-[var(--ui-muted-5)] rounded-md">
                                        @svg('heroicon-o-clock', 'w-3.5 h-3.5')
                                        Aktiv: {{ $rs->active_duration_minutes }} Min.
                                    </span>
                                @endif
                                @if($rs->wait_duration_minutes !== null)
                                    <span class="text-xs text-[var(--ui-muted)] flex items-center gap-1.5 py-1 px-2.5 bg-[var(--ui-muted-5)] rounded-md">
                                        @svg('heroicon-o-pause', 'w-3.5 h-3.5')
                                        Wartezeit: {{ $rs->wait_duration_minutes }} Min.{{ $rs->wait_override ? ' (manuell)' : '' }}
                                    </span>
                                @endif
                                @if($rs->processStep?->duration_target_minutes && $rs->active_duration_minutes !== null)
                                    @php $delta = $rs->active_duration_minutes - $rs->processStep->duration_target_minutes; @endphp
                                    <span class="text-xs flex items-center gap-1.5 py-1 px-2.5 rounded-md {{ $delta > 0 ? 'text-red-600 bg-red-50' : 'text-green-600 bg-green-50' }}">
                                        @svg($delta > 0 ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down', 'w-3.5 h-3.5')
                                        {{ $delta > 0 ? '+' : '' }}{{ $delta }} Min.
                                    </span>
                                @endif
                                @if($rs->checked_at)
                                    <span class="text-xs text-[var(--ui-muted)] flex items-center gap-1.5 py-1 px-2.5">
                                        {{ $rs->checked_at->format('H:i') }}
                                    </span>
                                @endif
                            </div>
                        @endif

                        {{-- Inline edit for pending steps --}}
                        @if($isPending && $this->isActive)
                            <div x-show="editing" x-transition @click.stop class="mt-4 p-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/60">
                                <div class="flex items-end gap-3 flex-wrap">
                                    <div>
                                        <label class="text-xs font-medium text-[var(--ui-muted)] block mb-1.5">Aktive Zeit (Min.)</label>
                                        <input type="number" x-model="activeDur" min="0" placeholder="0" class="w-28 text-sm px-3 py-2 rounded-lg border border-[var(--ui-border)] focus:border-[var(--ui-info)] focus:ring-1 focus:ring-[var(--ui-info)] bg-white" />
                                    </div>
                                    <div>
                                        <label class="text-xs font-medium text-[var(--ui-muted)] block mb-1.5">Wartezeit (Min., opt.)</label>
                                        <input type="number" x-model="waitDur" min="0" placeholder="auto" class="w-28 text-sm px-3 py-2 rounded-lg border border-[var(--ui-border)] focus:border-[var(--ui-info)] focus:ring-1 focus:ring-[var(--ui-info)] bg-white" />
                                    </div>
                                    <button
                                        type="button"
                                        @click="$wire.completeStep({{ $rs->id }}, activeDur ? parseInt(activeDur) : null, waitDur ? parseInt(waitDur) : null); editing = false"
                                        class="px-5 py-2 text-sm font-medium bg-[var(--ui-success)] text-white rounded-lg hover:bg-[var(--ui-success)]/90 transition-colors flex items-center gap-2"
                                    >
                                        @svg('heroicon-o-check', 'w-4 h-4')
                                        Erledigt
                                    </button>
                                    <button
                                        type="button"
                                        @click="$wire.skipStep({{ $rs->id }}); editing = false"
                                        class="px-4 py-2 text-sm text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors"
                                    >
                                        Überspringen
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</x-ui-page>
