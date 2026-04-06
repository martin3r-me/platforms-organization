<div x-data="{ activeTab: $wire.entangle('activeTab') }">
<x-ui-modal size="xl" wire:model="open" :closeButton="true">
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div class="flex-shrink-0">
                <div class="w-12 h-12 bg-gradient-to-br from-[var(--ui-primary-10)] to-[var(--ui-primary-5)] rounded-xl flex items-center justify-center shadow-sm">
                    @svg('heroicon-o-clock', 'w-6 h-6 text-[var(--ui-primary)]')
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="text-xl font-bold text-[var(--ui-secondary)]">Zeiterfassung</h3>
                @if($contextType && $contextId && $this->contextBreadcrumb)
                    <div class="flex items-center gap-2 mt-1 flex-wrap">
                        @foreach($this->contextBreadcrumb as $index => $crumb)
                            <div class="flex items-center gap-2">
                                @if($index > 0)
                                    @svg('heroicon-o-chevron-right', 'w-3 h-3 text-[var(--ui-muted)]')
                                @endif
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-xs font-medium bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] border border-[var(--ui-border)]/40">
                                    <span class="text-[var(--ui-muted)]">{{ $crumb['type'] }}:</span>
                                    <span class="font-semibold">{{ $crumb['label'] }}</span>
                                </span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-[var(--ui-muted)] mt-1">Zeiten erfassen und verwalten</p>
                @endif
            </div>
        </div>
    </x-slot>

    <div>
        @if(!$contextType || !$contextId)
            <!-- Kein Kontext -->
            <div class="px-6 py-16 text-center">
                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-[var(--ui-muted-5)] flex items-center justify-center">
                    @svg('heroicon-o-information-circle', 'w-8 h-8 text-[var(--ui-muted)]')
                </div>
                <p class="text-sm font-medium text-[var(--ui-secondary)]">Kein Kontext ausgewählt</p>
                <p class="text-xs text-[var(--ui-muted)] mt-2 max-w-sm mx-auto">In diesem Kontext stehen keine Funktionen zur Verfügung. Öffnen Sie eine Aufgabe oder ein Projekt, um Zeiten zu erfassen und Budgets zu verwalten.</p>
            </div>
        @else
            <!-- Tabs -->
            <div class="flex gap-1 border-b border-[var(--ui-border)]/60 mb-8">
                @if($allowTimeEntry)
                <button
                    @click="activeTab = 'entry'"
                    :class="activeTab === 'entry'
                        ? 'text-[var(--ui-primary)] border-b-2 border-[var(--ui-primary)] font-semibold bg-[var(--ui-primary-5)]/30'
                        : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]'"
                    class="px-5 py-3 text-sm transition-all duration-200 rounded-t-lg"
                >
                    <span class="inline-flex items-center gap-2">
                        @svg('heroicon-o-plus-circle', 'w-4 h-4')
                        Erfassen
                    </span>
                </button>
                <button
                    @click="activeTab = 'overview'"
                    :class="activeTab === 'overview'
                        ? 'text-[var(--ui-primary)] border-b-2 border-[var(--ui-primary)] font-semibold bg-[var(--ui-primary-5)]/30'
                        : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]'"
                    class="px-5 py-3 text-sm transition-all duration-200 rounded-t-lg"
                >
                    <span class="inline-flex items-center gap-2">
                        @svg('heroicon-o-list-bullet', 'w-4 h-4')
                        Übersicht
                        @if($entries && $entries->count() > 0)
                            <span class="ml-1 px-2 py-0.5 text-xs font-semibold rounded-full bg-[var(--ui-primary-10)] text-[var(--ui-primary)]">
                                {{ $entries->count() }}
                            </span>
                        @endif
                    </span>
                </button>
                @endif
            </div>

            <!-- Tab Content: Entry -->
            @if($allowTimeEntry)
            <div x-show="activeTab === 'entry'" x-cloak>
                <div class="space-y-5">
                    <!-- Datum & Dauer -->
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-ui-input-date-select
                            name="workDate"
                            label="Datum"
                            wire:model.live="workDate"
                            :errorKey="'workDate'"
                        />

                        <x-ui-input-text
                            name="rate"
                            label="Stundensatz"
                            hint="Optional"
                            wire:model.live="rate"
                            placeholder="z. B. 95,00"
                            :errorKey="'rate'"
                        />
                    </div>

                    <!-- Dauer -->
                    <x-ui-input-select
                        name="minutes"
                        label="Dauer"
                        wire:model.live="minutes"
                        displayMode="badges"
                        badgeSize="sm"
                        :options="[
                            15 => '15m',
                            30 => '30m',
                            45 => '45m',
                            60 => '1h',
                            90 => '1,5h',
                            120 => '2h',
                            180 => '3h',
                            240 => '4h',
                            360 => '6h',
                            480 => '8h',
                        ]"
                        :errorKey="'minutes'"
                    />

                    <!-- Notiz -->
                    <x-ui-input-textarea
                        name="note"
                        label="Anmerkung"
                        wire:model.live="note"
                        rows="2"
                        placeholder="Optionaler Kommentar zur erfassten Zeit"
                        :errorKey="'note'"
                    />

                    <!-- Preview -->
                    @if($rate && $minutes)
                        @php
                            $normalizedRate = str_replace([' ', "'"], '', $rate);
                            $normalizedRate = str_replace(',', '.', $normalizedRate);
                            $rateFloat = is_numeric($normalizedRate) && (float)$normalizedRate > 0 ? (float)$normalizedRate : null;
                            $amountCents = $rateFloat !== null ? (int) round($rateFloat * 100 * ($minutes / 60)) : null;
                        @endphp
                        @if($amountCents)
                            <div class="flex items-center justify-between px-4 py-3 rounded-lg bg-[var(--ui-primary-5)] border border-[var(--ui-primary)]/20">
                                <span class="text-sm text-[var(--ui-secondary)]">Geschätzter Betrag</span>
                                <span class="text-lg font-bold text-[var(--ui-primary)]">{{ number_format($amountCents / 100, 2, ',', '.') }} €</span>
                            </div>
                        @endif
                    @endif

                    <!-- Divider -->
                    <div class="border-t border-[var(--ui-border)]/40"></div>

                    <!-- Collapsible Budget Section -->
                    <div x-data="{ budgetOpen: false }">
                        <button
                            type="button"
                            @click="budgetOpen = !budgetOpen"
                            class="flex items-center justify-between w-full px-5 py-3 rounded-xl border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]/50 hover:bg-[var(--ui-muted-5)] transition-colors"
                        >
                            <span class="inline-flex items-center gap-2 text-sm font-semibold text-[var(--ui-secondary)]">
                                @svg('heroicon-o-calculator', 'w-4 h-4 text-[var(--ui-primary)]')
                                Budget hinzufügen
                            </span>
                            <span :class="budgetOpen ? 'rotate-180' : ''" class="transition-transform duration-200">
                                @svg('heroicon-o-chevron-down', 'w-4 h-4 text-[var(--ui-muted)]')
                            </span>
                        </button>

                        <div x-show="budgetOpen" x-collapse x-cloak class="mt-4 space-y-4">
                            <!-- Quick Hour Buttons -->
                            <x-ui-input-select
                                name="plannedMinutes"
                                label="Stunden"
                                wire:model.live="plannedMinutes"
                                displayMode="badges"
                                badgeSize="sm"
                                :options="[
                                    60 => '1h', 120 => '2h', 180 => '3h', 240 => '4h',
                                    300 => '5h', 360 => '6h', 420 => '7h', 480 => '8h',
                                ]"
                            />
                            <!-- Quick Day Buttons -->
                            <x-ui-input-select
                                name="plannedMinutesDays"
                                label="Tage (à 8h)"
                                wire:model.live="plannedMinutes"
                                displayMode="badges"
                                badgeSize="sm"
                                :options="[
                                    480 => '1d', 960 => '2d', 2400 => '5d', 4800 => '10d', 9600 => '20d',
                                ]"
                            />
                            <!-- Minutes Input -->
                            <div>
                                <x-ui-input-number
                                    name="plannedMinutesManual"
                                    label="Oder Minuten eingeben"
                                    wire:model.live="plannedMinutes"
                                    placeholder="z. B. 120 für 2 Stunden"
                                    :errorKey="'plannedMinutes'"
                                />
                                @if($plannedMinutes)
                                    <p class="mt-1.5 text-xs text-[var(--ui-muted)]">
                                        = {{ number_format($plannedMinutes / 60, 2, ',', '.') }} Stunden
                                        ({{ number_format($plannedMinutes / 480, 2, ',', '.') }} Tage)
                                    </p>
                                @endif
                            </div>
                            <!-- Note -->
                            <div>
                                <x-ui-input-textarea
                                    name="plannedNote"
                                    label="Grund für Budget"
                                    wire:model.live="plannedNote"
                                    rows="2"
                                    placeholder="z. B. Initiales Budget, Nachverhandlung, ..."
                                    :errorKey="'plannedNote'"
                                />
                            </div>
                            <!-- Inline Save Button -->
                            <x-ui-button variant="primary" wire:click="savePlanned" wire:loading.attr="disabled" class="w-full">
                                <span wire:loading.remove wire:target="savePlanned" class="inline-flex items-center gap-2">
                                    @svg('heroicon-o-plus', 'w-4 h-4')
                                    Budget hinzufügen
                                </span>
                                <span wire:loading wire:target="savePlanned" class="inline-flex items-center gap-2">
                                    @svg('heroicon-o-arrow-path', 'w-4 h-4 animate-spin')
                                    Speichern…
                                </span>
                            </x-ui-button>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Tab Content: Overview -->
            @if($allowTimeEntry)
            <div x-show="activeTab === 'overview'" x-cloak>
                <div class="space-y-6">
                    <!-- Progress Bar: Ist vs Soll -->
                    @php
                        $totalPlanned = $this->totalPlannedMinutes;
                        $totalActual = $this->totalMinutes;
                    @endphp
                    @if($totalPlanned)
                        <div class="p-5 bg-gradient-to-br from-[var(--ui-primary-5)] via-[var(--ui-primary-10)] to-[var(--ui-primary-5)] rounded-xl border-2 border-[var(--ui-primary)]/30 shadow-sm">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-sm font-semibold text-[var(--ui-secondary)] uppercase tracking-wide">Budget-Auslastung</span>
                                <span class="text-2xl font-bold text-[var(--ui-primary)]">{{ number_format($totalPlanned / 60, 2, ',', '.') }} <span class="text-base">h</span></span>
                            </div>
                            @php
                                $progress = min(100, ($totalActual / $totalPlanned) * 100);
                                $isOver = $totalActual > $totalPlanned;
                                $diff = $totalActual - $totalPlanned;
                            @endphp
                            <div class="flex items-center justify-between text-xs font-medium text-[var(--ui-secondary)] mb-2">
                                <span>Erfasst: {{ number_format($totalActual / 60, 2, ',', '.') }} h / Budget: {{ number_format($totalPlanned / 60, 2, ',', '.') }} h</span>
                                <span class="font-bold {{ $isOver ? 'text-[var(--ui-danger)]' : 'text-[var(--ui-success)]' }}">
                                    {{ $isOver ? '+' : '' }}{{ number_format($diff / 60, 2, ',', '.') }} h
                                </span>
                            </div>
                            <div class="w-full bg-[var(--ui-muted-5)] rounded-full h-3 overflow-hidden shadow-inner">
                                <div
                                    class="h-3 rounded-full transition-all duration-500 {{ $isOver ? 'bg-gradient-to-r from-[var(--ui-danger)] to-[var(--ui-danger)]' : 'bg-gradient-to-r from-[var(--ui-primary)] to-[var(--ui-primary)]' }}"
                                    style="width: {{ min(100, $progress) }}%"
                                ></div>
                            </div>
                        </div>
                    @endif

                    <!-- Filter -->
                    <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center">
                        <!-- Personen-Filter -->
                        <div class="flex-1 min-w-0 w-full sm:w-auto">
                            <label class="block text-sm font-semibold text-[var(--ui-secondary)] mb-2">Person:</label>
                            <select
                                name="selectedUserId"
                                wire:model.live="selectedUserId"
                                class="w-full px-4 py-2 text-sm rounded-xl border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/20 focus:border-[var(--ui-primary)]"
                            >
                                <option value="">Alle</option>
                                @foreach($this->availableUsers as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Zeitraum-Filter -->
                        <div class="flex-1 min-w-0 w-full sm:w-auto">
                            <label class="block text-sm font-semibold text-[var(--ui-secondary)] mb-2">Zeitraum:</label>
                            <div class="flex gap-2 flex-wrap">
                                @foreach([
                                    'all' => 'Alle',
                                    'current_week' => 'Diese Woche',
                                    'current_month' => 'Dieser Monat',
                                    'current_year' => 'Dieses Jahr',
                                    'last_week' => 'Letzte Woche',
                                    'last_month' => 'Letzter Monat',
                                ] as $rangeKey => $rangeLabel)
                                    <button
                                        wire:click="$set('overviewTimeRange', '{{ $rangeKey }}')"
                                        wire:loading.attr="disabled"
                                        :class="$wire.overviewTimeRange === '{{ $rangeKey }}'
                                            ? 'bg-[var(--ui-primary)] text-[var(--ui-on-primary)] border-[var(--ui-primary)] shadow-md'
                                            : 'bg-[var(--ui-surface)] text-[var(--ui-secondary)] border-[var(--ui-border)]/60 hover:border-[var(--ui-primary)]/60'"
                                        class="px-3 py-1.5 rounded-lg border-2 text-xs font-semibold transition-all"
                                    >
                                        {{ $rangeLabel }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Statistics -->
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="p-5 bg-gradient-to-br from-[var(--ui-surface)] to-[var(--ui-muted-5)] rounded-xl border border-[var(--ui-border)]/60 shadow-sm">
                            <div class="text-xs font-semibold text-[var(--ui-muted)] uppercase tracking-wide mb-2">Gesamt</div>
                            <div class="text-3xl font-bold text-[var(--ui-secondary)]">{{ number_format($this->totalMinutes / 60, 2, ',', '.') }} <span class="text-lg text-[var(--ui-muted)]">h</span></div>
                        </div>
                        <div class="p-5 bg-gradient-to-br from-[var(--ui-success-5)] to-[var(--ui-success-10)] rounded-xl border border-[var(--ui-success)]/30 shadow-sm">
                            <div class="text-xs font-semibold text-[var(--ui-success)] uppercase tracking-wide mb-2">Abgerechnet</div>
                            <div class="text-3xl font-bold text-[var(--ui-success)]">{{ number_format($this->billedMinutes / 60, 2, ',', '.') }} <span class="text-lg text-[var(--ui-success)]/70">h</span></div>
                        </div>
                        <div class="p-5 bg-gradient-to-br from-[var(--ui-warning-5)] to-[var(--ui-warning-10)] rounded-xl border border-[var(--ui-warning)]/30 shadow-sm">
                            <div class="text-xs font-semibold text-[var(--ui-warning)] uppercase tracking-wide mb-2">Offen</div>
                            <div class="text-3xl font-bold text-[var(--ui-warning)]">{{ number_format($this->unbilledMinutes / 60, 2, ',', '.') }} <span class="text-lg text-[var(--ui-warning)]/70">h</span></div>
                        </div>
                        @if($this->unbilledAmountCents)
                            <div class="p-5 bg-gradient-to-br from-[var(--ui-primary-5)] to-[var(--ui-primary-10)] rounded-xl border border-[var(--ui-primary)]/30 shadow-sm">
                                <div class="text-xs font-semibold text-[var(--ui-primary)] uppercase tracking-wide mb-2">Offener Wert</div>
                                <div class="text-3xl font-bold text-[var(--ui-primary)]">{{ number_format($this->unbilledAmountCents / 100, 2, ',', '.') }} <span class="text-lg text-[var(--ui-primary)]/70">€</span></div>
                            </div>
                        @endif
                    </div>

                    <!-- Budget List -->
                    @if($plannedEntries && count($plannedEntries) > 0)
                        <div class="rounded-xl border border-[var(--ui-border)]/60 overflow-hidden shadow-sm">
                            <div class="px-6 py-3 bg-[var(--ui-muted-5)] border-b border-[var(--ui-border)]/60 flex items-center justify-between">
                                <h4 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wide">Budgets</h4>
                                @if($totalPlanned)
                                    <span class="text-xs font-semibold text-[var(--ui-primary)]">Gesamt: {{ number_format($totalPlanned / 60, 2, ',', '.') }} h</span>
                                @endif
                            </div>
                            <div class="divide-y divide-[var(--ui-border)]/40 bg-[var(--ui-surface)]">
                                @foreach($plannedEntries as $planned)
                                    <div class="flex flex-col gap-2 px-6 py-3 {{ $planned->is_active ? 'hover:bg-[var(--ui-muted-5)]/50' : 'opacity-50' }} transition-colors sm:flex-row sm:items-center sm:justify-between">
                                        <div class="flex-1 flex flex-col gap-1">
                                            <div class="flex items-center gap-3">
                                                <span class="text-base font-bold text-[var(--ui-secondary)]">{{ number_format($planned->planned_minutes / 60, 2, ',', '.') }} h</span>
                                                @if($planned->is_active)
                                                    <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[10px] font-semibold bg-[var(--ui-success-10)] text-[var(--ui-success)] ring-1 ring-inset ring-[var(--ui-success)]/20">
                                                        Aktiv
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[10px] font-semibold bg-[var(--ui-muted-5)] text-[var(--ui-muted)] ring-1 ring-inset ring-[var(--ui-border)]/20">
                                                        Deaktiviert
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="text-xs text-[var(--ui-muted)] flex flex-wrap items-center gap-2">
                                                <span>{{ $planned->created_at?->format('d.m.Y H:i') }}</span>
                                                <span>·</span>
                                                <span class="font-medium">{{ $planned->user?->name ?? 'Unbekannt' }}</span>
                                            </div>
                                            @if($planned->note)
                                                <div class="text-xs text-[var(--ui-muted)] italic pl-4 border-l-2 border-[var(--ui-border)]/40">{{ $planned->note }}</div>
                                            @endif
                                        </div>
                                        @if($planned->is_active)
                                            <button
                                                wire:click="deletePlannedEntry({{ $planned->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="deletePlannedEntry({{ $planned->id }})"
                                                class="p-1.5 text-[var(--ui-danger)] hover:bg-[var(--ui-danger-5)] rounded-lg transition-colors shrink-0"
                                                title="Budget deaktivieren"
                                            >
                                                @svg('heroicon-o-x-mark', 'w-4 h-4')
                                            </button>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Entries Table -->
                    <div class="flow-root">
                        <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                                <div class="overflow-hidden rounded-xl border border-[var(--ui-border)]/60 shadow-sm">
                                    <table class="min-w-full divide-y divide-[var(--ui-border)]/40">
                                        <thead class="bg-[var(--ui-muted-5)]">
                                            <tr>
                                                <th scope="col" class="py-4 pl-6 pr-3 text-left text-xs font-bold uppercase tracking-wider text-[var(--ui-secondary)]">
                                                    Datum
                                                </th>
                                                <th scope="col" class="px-3 py-4 text-left text-xs font-bold uppercase tracking-wider text-[var(--ui-secondary)]">
                                                    Dauer
                                                </th>
                                                <th scope="col" class="px-3 py-4 text-left text-xs font-bold uppercase tracking-wider text-[var(--ui-secondary)]">
                                                    Betrag
                                                </th>
                                                <th scope="col" class="px-3 py-4 text-left text-xs font-bold uppercase tracking-wider text-[var(--ui-secondary)]">
                                                    Benutzer
                                                </th>
                                                <th scope="col" class="px-3 py-4 text-left text-xs font-bold uppercase tracking-wider text-[var(--ui-secondary)]">
                                                    Status
                                                </th>
                                                <th scope="col" class="relative py-4 pl-3 pr-6">
                                                    <span class="sr-only">Aktionen</span>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-[var(--ui-border)]/40 bg-[var(--ui-surface)]">
                                            @forelse($entries ?? [] as $entry)
                                                <tr class="hover:bg-[var(--ui-muted-5)]/50 transition-colors">
                                                    <td class="whitespace-nowrap py-5 pl-6 pr-3 text-sm">
                                                        <div class="font-semibold text-[var(--ui-secondary)]">{{ $entry->work_date?->format('d.m.Y') }}</div>
                                                        <div class="text-xs text-[var(--ui-muted)] mt-0.5">{{ $entry->work_date?->format('l') }}</div>
                                                    </td>
                                                    <td class="whitespace-nowrap px-3 py-5 text-sm">
                                                        <div class="font-medium text-[var(--ui-secondary)]">{{ \Platform\Organization\Models\OrganizationTimeEntry::formatMinutes($entry->minutes) }}</div>
                                                        @if($entry->rate_cents)
                                                            <div class="text-xs text-[var(--ui-muted)] mt-0.5">{{ number_format($entry->rate_cents / 100, 2, ',', '.') }} €/h</div>
                                                        @endif
                                                    </td>
                                                    <td class="whitespace-nowrap px-3 py-5 text-sm">
                                                        @if($entry->amount_cents)
                                                            <div class="font-semibold text-[var(--ui-secondary)]">{{ number_format($entry->amount_cents / 100, 2, ',', '.') }} €</div>
                                                        @else
                                                            <span class="text-[var(--ui-muted)]">–</span>
                                                        @endif
                                                    </td>
                                                    <td class="whitespace-nowrap px-3 py-5 text-sm">
                                                        <div class="flex items-center">
                                                            <div class="size-9 shrink-0 rounded-full bg-gradient-to-br from-[var(--ui-primary-10)] to-[var(--ui-primary-5)] flex items-center justify-center border border-[var(--ui-border)]/40">
                                                                <span class="text-xs font-bold text-[var(--ui-primary)]">
                                                                    {{ strtoupper(substr($entry->user?->name ?? 'U', 0, 1)) }}
                                                                </span>
                                                            </div>
                                                            <div class="ml-3">
                                                                <div class="font-medium text-[var(--ui-secondary)]">{{ $entry->user?->name ?? 'Unbekannt' }}</div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="whitespace-nowrap px-3 py-5 text-sm">
                                                        <span class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $entry->is_billed ? 'bg-[var(--ui-success-10)] text-[var(--ui-success)] ring-[var(--ui-success)]/20' : 'bg-[var(--ui-warning-10)] text-[var(--ui-warning)] ring-[var(--ui-warning)]/20' }}">
                                                            @if($entry->is_billed)
                                                                @svg('heroicon-o-check-circle', 'w-3.5 h-3.5')
                                                                Abgerechnet
                                                            @else
                                                                @svg('heroicon-o-exclamation-circle', 'w-3.5 h-3.5')
                                                                Offen
                                                            @endif
                                                        </span>
                                                    </td>
                                                    <td class="relative whitespace-nowrap py-5 pl-3 pr-6 text-right text-sm font-medium">
                                                        <div class="flex items-center justify-end gap-2">
                                                            <button
                                                                wire:click="toggleBilled({{ $entry->id }})"
                                                                wire:loading.attr="disabled"
                                                                wire:target="toggleBilled({{ $entry->id }})"
                                                                class="px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors {{ $entry->is_billed ? 'bg-[var(--ui-surface)] text-[var(--ui-secondary)] border-[var(--ui-border)]/60 hover:bg-[var(--ui-muted-5)]' : 'bg-[var(--ui-success-5)] text-[var(--ui-success)] border-[var(--ui-success)]/40 hover:bg-[var(--ui-success-10)]' }}"
                                                            >
                                                                {{ $entry->is_billed ? 'Als offen' : 'Abrechnen' }}
                                                            </button>
                                                            <button
                                                                wire:click="deleteEntry({{ $entry->id }})"
                                                                wire:loading.attr="disabled"
                                                                wire:target="deleteEntry({{ $entry->id }})"
                                                                class="p-1.5 text-[var(--ui-danger)] hover:bg-[var(--ui-danger-5)] rounded-lg transition-colors"
                                                                title="Eintrag löschen"
                                                            >
                                                                @svg('heroicon-o-trash', 'w-4 h-4')
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                                @if($entry->note)
                                                    <tr>
                                                        <td colspan="6" class="px-6 py-2 bg-[var(--ui-muted-5)]/30">
                                                            <div class="text-xs text-[var(--ui-muted)] italic">{{ $entry->note }}</div>
                                                        </td>
                                                    </tr>
                                                @endif
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="px-6 py-12 text-center">
                                                        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-[var(--ui-muted-5)] flex items-center justify-center">
                                                            @svg('heroicon-o-clock', 'w-8 h-8 text-[var(--ui-muted)]')
                                                        </div>
                                                        <p class="text-sm font-medium text-[var(--ui-secondary)]">Noch keine Zeiten erfasst</p>
                                                        <p class="text-xs text-[var(--ui-muted)] mt-1">Wechsle zum Tab "Erfassen" um zu beginnen.</p>
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

        @endif
    </div>

    <x-slot name="footer">
        <div class="flex justify-between items-center">
            @if($contextType && $contextId)
                <div class="text-xs text-[var(--ui-muted)]" x-show="activeTab === 'overview'">
                    @if($entries && $entries->count() > 0)
                        <span class="font-medium">{{ $entries->count() }}</span> {{ $entries->count() === 1 ? 'Eintrag' : 'Einträge' }}
                    @endif
                </div>
            @else
                <div></div>
            @endif
            <div class="flex justify-end gap-3 ml-auto">
                <x-ui-button variant="secondary" wire:click="close">
                    Schließen
                </x-ui-button>
                @if($contextType && $contextId)
                    <x-ui-button
                        variant="primary"
                        wire:click="save"
                        wire:loading.attr="disabled"
                        x-show="activeTab === 'entry'"
                    >
                        <span wire:loading.remove wire:target="save">Zeit erfassen</span>
                        <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                            @svg('heroicon-o-arrow-path', 'w-4 h-4 animate-spin')
                            Speichern…
                        </span>
                    </x-ui-button>
                @endif
            </div>
        </div>
    </x-slot>
</x-ui-modal>
</div>
