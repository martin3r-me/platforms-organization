<div class="space-y-4">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="text-sm font-semibold text-[var(--ui-secondary)]">Zeiterfassung</h3>
            <div class="text-xs text-[var(--ui-muted)] flex flex-wrap items-center gap-2">
                <span>{{ number_format($this->totalMinutes / 60, 2, ',', '.') }} h gesamt</span>
                <span>• Abgerechnet: {{ number_format($this->billedMinutes / 60, 2, ',', '.') }} h</span>
                <span>• Offen: {{ number_format($this->unbilledMinutes / 60, 2, ',', '.') }} h</span>
                @if($this->unbilledAmountCents)
                    <span>• Offener Wert: {{ number_format($this->unbilledAmountCents / 100, 2, ',', '.') }} €</span>
                @endif
                @if($plannedMinutes ?? false)
                    <span>• Plan: {{ number_format($plannedMinutes / 60, 2, ',', '.') }} h</span>
                @endif
            </div>
        </div>
        <x-ui-button variant="primary" size="sm" wire:click="openModal">
            <span class="inline-flex items-center gap-2">
                @svg('heroicon-o-plus', 'w-4 h-4')
                Zeit erfassen
            </span>
        </x-ui-button>
    </div>

    <div class="rounded-lg border border-[var(--ui-border)]/60">
        <div class="divide-y divide-[var(--ui-border)]/40">
            @forelse($entries as $entry)
                <div class="flex flex-col gap-2 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex flex-col gap-1">
                        <span class="font-medium text-[var(--ui-secondary)]">{{ $entry->work_date?->format('d.m.Y') }}</span>
                        <div class="text-xs text-[var(--ui-muted)]">
                            {{ number_format($entry->minutes / 60, 2, ',', '.') }} h
                            @if($entry->amount_cents)
                                • {{ number_format($entry->amount_cents / 100, 2, ',', '.') }} €
                            @elseif($entry->rate_cents)
                                • {{ number_format($entry->rate_cents / 100, 2, ',', '.') }} €/h
                            @endif
                            • {{ $entry->user?->name }}
                        </div>
                        @if($entry->note)
                            <div class="text-xs text-[var(--ui-muted)]">{{ $entry->note }}</div>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-semibold {{ $entry->is_billed ? 'bg-[var(--ui-success-10)] border-[var(--ui-success)]/40 text-[var(--ui-success)]' : 'bg-[var(--ui-warning-10)] border-[var(--ui-warning)]/40 text-[var(--ui-warning)]' }}">
                            @if($entry->is_billed)
                                @svg('heroicon-o-check-circle', 'w-3 h-3')
                            @else
                                @svg('heroicon-o-exclamation-circle', 'w-3 h-3')
                            @endif
                            {{ $entry->is_billed ? 'Abgerechnet' : 'Offen' }}
                        </span>
                        <x-ui-button variant="secondary" size="xs" wire:click="toggleBilled({{ $entry->id }})" wire:loading.attr="disabled" wire:target="toggleBilled({{ $entry->id }})">
                            {{ $entry->is_billed ? 'Als offen markieren' : 'Abrechnen' }}
                        </x-ui-button>
                        <x-ui-button variant="danger-outline" size="xs" wire:click="deleteEntry({{ $entry->id }})" wire:loading.attr="disabled" wire:target="deleteEntry({{ $entry->id }})">
                            @svg('heroicon-o-trash', 'w-4 h-4')
                            <span class="sr-only">Eintrag löschen</span>
                        </x-ui-button>
                    </div>
                </div>
            @empty
                <div class="px-4 py-6 text-sm text-[var(--ui-muted)]">Noch keine Zeiten erfasst.</div>
            @endforelse
        </div>
    </div>
</div>

