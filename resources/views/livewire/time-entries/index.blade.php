@php
    use Platform\Organization\Models\OrganizationTimeEntry as TE;

    $opts = fn ($items) => $items->map(fn ($i) => ['value' => $i->id, 'label' => $i->name])->values()->all();
    $hours = fn ($m) => TE::formatMinutesAsHours($m);
    $euro = fn ($c) => number_format($c / 100, 2, ',', '.') . ' €';
    $icon = fn ($i) => 'heroicon-o-' . (app('safe-svg')->resolve($i ?? null, 'heroicon-o-') ?? 'cube');

    $einheiten = $this->timeEntriesGroupedByEntity->sortByDesc('total_minutes')->values();
    $max = max(1, $einheiten->max('total_minutes') ?? 0);
    $tage = $this->visibleDateGroups;
    $alleTage = $this->timeEntriesGroupedByDateAndEntity->count();
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Ist-Zeiten" icon="heroicon-o-clock" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-64" :defaultOpen="true" side="left">
            <div class="flex flex-col gap-3 p-3">
                <x-nx-input-date name="dateFrom" label="Von" size="sm" wire:model.live="dateFrom" :value="$dateFrom" />
                <x-nx-input-date name="dateTo" label="Bis" size="sm" wire:model.live="dateTo" :value="$dateTo" />
                <x-nx-input-select name="selectedUserId" label="Person" size="sm" wire:model.live="selectedUserId"
                    :options="$opts($this->availableUsers)" nullable nullLabel="Alle" />
                <x-nx-input-select name="selectedEntityTypeId" label="Einheitstyp" size="sm" wire:model.live="selectedEntityTypeId"
                    :options="$opts($this->availableEntityTypes)" nullable nullLabel="Alle" />
                <x-nx-input-select name="selectedEntityId" label="Einheit" size="sm" wire:model.live="selectedEntityId"
                    :options="$opts($this->availableEntities)" nullable nullLabel="Alle" />
                <div class="border-t border-[color:var(--nx-line)] pt-3">
                    <x-nx-input-checkbox label="Nur abgerechnete" wire:model.live="showBilledOnly" />
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organisation', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Ist-Zeiten'],
        ]">
            <x-slot name="left">
                <div class="w-64">
                    <x-nx-input-text name="search" size="sm" placeholder="Notiz oder Person suchen…" wire:model.live.debounce.400ms="search" />
                </div>
            </x-slot>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
        <div class="space-y-5">
            <div class="flex flex-wrap items-baseline gap-2">
                <h1 class="m-0 text-2xl font-semibold tracking-tight text-[color:var(--nx-text)]">Ist-Zeiten</h1>
                <span class="text-sm text-[color:var(--nx-muted)]">
                    {{ \Carbon\Carbon::parse($dateFrom)->format('d.m.Y') }} – {{ \Carbon\Carbon::parse($dateTo)->format('d.m.Y') }}
                </span>
            </div>

            <x-nx-stat-grid cols="4">
                <x-nx-stat label="Erfasst" :value="$hours($this->totalMinutes)" icon="heroicon-o-clock" />
                <x-nx-stat label="Abgerechnet" :value="$hours($this->totalBilledMinutes)" :hint="$euro($this->totalBilledAmountCents)" icon="heroicon-o-check-circle" />
                <x-nx-stat label="Offen" :value="$hours($this->totalUnbilledMinutes)" :hint="$euro($this->totalUnbilledAmountCents)" icon="heroicon-o-inbox" />
                <x-nx-stat label="Betrag gesamt" :value="$euro($this->totalAmountCents)" icon="heroicon-o-banknotes" />
            </x-nx-stat-grid>

            @if ($einheiten->isEmpty())
                <x-nx-card flush>
                    <x-nx-empty icon="heroicon-o-clock">Keine Zeiteinträge im Zeitraum.</x-nx-empty>
                </x-nx-card>
            @else
                {{-- Nach Einheit: kompakte Rangliste statt Kachel-Wand --}}
                <x-nx-panel flush title="Nach Einheit" :subtitle="$einheiten->count() . ' ' . ($einheiten->count() === 1 ? 'Einheit' : 'Einheiten')">
                    <div x-data="{ alle: false }">
                        <table class="w-full text-sm">
                            <tbody>
                                @foreach ($einheiten as $g)
                                    <tr class="border-b border-[color:var(--nx-line)] last:border-0" @if ($loop->index >= 8) x-show="alle" x-cloak @endif>
                                        <td class="px-4 py-2">
                                            <div class="flex items-center gap-2 min-w-0">
                                                @svg($icon($g['entity_type_icon']), 'w-4 h-4 shrink-0 text-[color:var(--nx-faint)]')
                                                @if ($g['entity_model'])
                                                    <a href="{{ route('organization.entities.show', $g['entity_model']) }}" wire:navigate class="truncate font-medium text-[color:var(--nx-text)] hover:underline">{{ $g['entity_name'] }}</a>
                                                @else
                                                    <span class="truncate text-[color:var(--nx-muted)]">{{ $g['entity_name'] }}</span>
                                                @endif
                                                @if ($g['entity_type'])
                                                    <span class="shrink-0 text-xs text-[color:var(--nx-faint)]">{{ $g['entity_type']->name }}</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="hidden w-40 px-4 py-2 sm:table-cell">
                                            <div class="h-1.5 rounded-full bg-[color:var(--nx-accent-soft)]">
                                                <div class="h-1.5 rounded-full bg-[color:var(--nx-accent)]" style="width: {{ round($g['total_minutes'] / $max * 100) }}%"></div>
                                            </div>
                                        </td>
                                        <td class="w-24 px-4 py-2 text-right font-medium tabular-nums text-[color:var(--nx-text)]">{{ $hours($g['total_minutes']) }}</td>
                                        <td class="w-28 px-4 py-2 text-right tabular-nums text-[color:var(--nx-muted)]">{{ $g['total_amount_cents'] > 0 ? $euro($g['total_amount_cents']) : '–' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if ($einheiten->count() > 8)
                            <div class="border-t border-[color:var(--nx-line)] px-4 py-2">
                                <button type="button" x-on:click="alle = !alle" class="text-xs font-medium text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]"
                                    x-text="alle ? 'Weniger anzeigen' : 'Alle {{ $einheiten->count() }} anzeigen'"></button>
                            </div>
                        @endif
                    </div>
                </x-nx-panel>

                {{-- Einträge: Tag → Einheit → Einträge. Bewusst schlichtes Markup, keine
                     Komponente pro Zelle — das sind schnell mehrere hundert Zeilen. --}}
                <x-nx-card flush>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-xs text-[color:var(--nx-muted)]">
                                    <th class="border-b border-[color:var(--nx-line)] px-4 py-2.5 text-left font-medium">Person</th>
                                    <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-left font-medium">Kontext</th>
                                    <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-left font-medium">Notiz</th>
                                    <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-right font-medium">Zeit</th>
                                    <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-right font-medium">Betrag</th>
                                    <th class="border-b border-[color:var(--nx-line)] px-4 py-2.5 text-right font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($tage as $tag)
                                    <tr wire:key="d-{{ $tag['date_key'] }}" class="bg-[color:var(--nx-bg)]">
                                        <td colspan="3" class="border-b border-[color:var(--nx-line)] px-4 py-2">
                                            <span class="font-semibold text-[color:var(--nx-text)]">{{ $tag['date']->locale('de')->isoFormat('dddd, D. MMMM') }}</span>
                                        </td>
                                        <td class="border-b border-[color:var(--nx-line)] px-3 py-2 text-right font-semibold tabular-nums text-[color:var(--nx-text)]">{{ $hours($tag['total_minutes']) }}</td>
                                        <td class="border-b border-[color:var(--nx-line)] px-3 py-2 text-right tabular-nums text-[color:var(--nx-muted)]">{{ $tag['total_amount_cents'] > 0 ? $euro($tag['total_amount_cents']) : '' }}</td>
                                        <td class="border-b border-[color:var(--nx-line)]"></td>
                                    </tr>
                                    @foreach ($tag['entity_groups'] as $g)
                                        <tr>
                                            <td colspan="6" class="px-4 pb-1 pt-3">
                                                <div class="flex items-center gap-2 text-xs">
                                                    @svg($icon($g['entity_type_icon']), 'w-3.5 h-3.5 shrink-0 text-[color:var(--nx-faint)]')
                                                    <span class="font-medium text-[color:var(--nx-text)]">{{ $g['entity_name'] }}</span>
                                                    @if ($g['entity_type'])
                                                        <span class="text-[color:var(--nx-faint)]">{{ $g['entity_type']->name }}</span>
                                                    @endif
                                                    <span class="ml-auto tabular-nums text-[color:var(--nx-muted)]">{{ $hours($g['total_minutes']) }}</span>
                                                </div>
                                            </td>
                                        </tr>
                                        @foreach ($g['entries'] as $e)
                                            @php
                                                $ctx = $e->context;
                                                $ctxName = $ctx
                                                    ? ($ctx instanceof \Platform\Core\Contracts\HasDisplayName ? $ctx->getDisplayName() : ($ctx->name ?? $ctx->title ?? null))
                                                    : null;
                                            @endphp
                                            <tr class="border-b border-[color:var(--nx-line)] hover:bg-[color:var(--nx-hover)]">
                                                <td class="whitespace-nowrap px-4 py-1.5">
                                                    <div class="flex items-center gap-2">
                                                        @if ($e->user?->avatar)
                                                            <img src="{{ $e->user->avatarUrl() }}" loading="lazy" alt="" class="h-6 w-6 rounded-[6px] object-cover" />
                                                        @else
                                                            <span class="flex h-6 w-6 items-center justify-center rounded-[6px] bg-[color:var(--nx-accent-soft)] text-[10px] font-medium">{{ mb_strtoupper(mb_substr($e->user->name ?? '?', 0, 1)) }}</span>
                                                        @endif
                                                        <span class="text-[color:var(--nx-text)]">{{ $e->user->name ?? 'Unbekannt' }}</span>
                                                    </div>
                                                </td>
                                                <td class="max-w-[16rem] px-3 py-1.5">
                                                    @if ($ctx)
                                                        <div class="truncate text-[color:var(--nx-text)]" title="{{ $ctxName }}">{{ $ctxName ?? 'Unbekannt' }}</div>
                                                        <div class="truncate text-xs text-[color:var(--nx-faint)]">{{ class_basename($e->context_type) }}@if ($e->source_module_title) · {{ $e->source_module_title }}@endif</div>
                                                    @else
                                                        <span class="text-[color:var(--nx-faint)]">–</span>
                                                    @endif
                                                </td>
                                                <td class="max-w-xs px-3 py-1.5">
                                                    @if ($e->note)
                                                        <div class="truncate text-[color:var(--nx-muted)]" title="{{ $e->note }}">{{ $e->note }}</div>
                                                    @else
                                                        <span class="text-[color:var(--nx-faint)]">–</span>
                                                    @endif
                                                </td>
                                                <td class="whitespace-nowrap px-3 py-1.5 text-right font-medium tabular-nums text-[color:var(--nx-text)]">{{ TE::formatMinutes($e->minutes) }}</td>
                                                <td class="whitespace-nowrap px-3 py-1.5 text-right tabular-nums text-[color:var(--nx-muted)]">
                                                    @if ($e->amount_cents)
                                                        <span title="{{ $e->rate_cents ? $euro($e->rate_cents) . '/h' : '' }}">{{ $euro($e->amount_cents) }}</span>
                                                    @else
                                                        –
                                                    @endif
                                                </td>
                                                <td class="whitespace-nowrap px-4 py-1.5 text-right">
                                                    @if ($e->is_billed)
                                                        <x-nx-badge variant="success" dot>Abgerechnet</x-nx-badge>
                                                    @else
                                                        <x-nx-badge dot>Offen</x-nx-badge>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($alleTage > $tage->count())
                        <div class="flex items-center justify-between border-t border-[color:var(--nx-line)] px-4 py-2.5">
                            <span class="text-xs text-[color:var(--nx-faint)]">{{ $tage->count() }} von {{ $alleTage }} Tagen</span>
                            <x-nx-button size="sm" wire:click="loadMoreDays">
                                <span wire:loading.remove wire:target="loadMoreDays">Weitere Tage laden</span>
                                <span wire:loading wire:target="loadMoreDays">Lädt…</span>
                            </x-nx-button>
                        </div>
                    @endif
                </x-nx-card>
            @endif
        </div>
    </x-ui-page-container>
</x-ui-page>
