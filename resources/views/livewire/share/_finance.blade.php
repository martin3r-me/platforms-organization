{{-- Kunden-Tor · Rechnungen & Zahlungen mit Belegen. Daten: $finance (ProvidesShareFinance), $compact (Übersicht: nur die neuesten). --}}
@php
    $cur = $finance['currency'] ?? 'EUR';
    $sum = $finance['summary'];
    $invoices = collect($finance['invoices'])->when($compact, fn ($c) => $c->take(5));
    $variant = ['paid' => 'success', 'partial' => 'warning', 'open' => 'info', 'overdue' => 'danger', 'credit' => 'neutral'];
    $paidPct = $sum['invoiced'] > 0 ? (int) round($sum['paid'] / $sum['invoiced'] * 100) : null;
@endphp
<div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <div class="gp-card p-4">
        <div class="text-xs text-[color:var(--nx-muted)]">In Rechnung gestellt</div>
        <div class="mt-1.5 text-xl font-semibold tabular-nums tracking-tight">{{ $money($sum['invoiced'], $cur) }}</div>
        <div class="mt-0.5 text-xs text-[color:var(--nx-faint)]">{{ count($finance['invoices']) }} {{ count($finance['invoices']) === 1 ? 'Beleg' : 'Belege' }} · brutto</div>
    </div>
    <div class="gp-card p-4">
        <div class="text-xs text-[color:var(--nx-muted)]">Bezahlt</div>
        <div class="mt-1.5 text-xl font-semibold tabular-nums tracking-tight text-[color:var(--nx-success)]">{{ $money($sum['paid'], $cur) }}</div>
        @if ($paidPct !== null)
            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-[color:var(--nx-line)]"><div class="h-full rounded-full bg-[color:var(--nx-success)]" style="width: {{ min(100, $paidPct) }}%"></div></div>
        @endif
    </div>
    <div class="gp-card p-4">
        <div class="text-xs text-[color:var(--nx-muted)]">Offen</div>
        <div class="mt-1.5 text-xl font-semibold tabular-nums tracking-tight">{{ $money($sum['open'], $cur) }}</div>
        <div class="mt-0.5 text-xs text-[color:var(--nx-faint)]">{{ $sum['open'] > 0 ? collect($finance['invoices'])->whereIn('status', ['open', 'partial', 'overdue'])->count() . ' Rechnung(en)' : 'alles beglichen' }}</div>
    </div>
    <div class="gp-card p-4">
        <div class="text-xs text-[color:var(--nx-muted)]">Überfällig</div>
        <div @class(['mt-1.5 text-xl font-semibold tabular-nums tracking-tight', 'text-[color:var(--nx-danger)]' => $sum['overdue'] > 0])>{{ $money($sum['overdue'], $cur) }}</div>
        <div class="mt-0.5 text-xs text-[color:var(--nx-faint)]">{{ $sum['overdue'] > 0 ? 'über dem Zahlungsziel' : 'nichts überfällig' }}</div>
    </div>
</div>

@if ($invoices->isNotEmpty())
    <div class="gp-card mt-3 overflow-hidden">
        <div class="hidden grid-cols-[1fr_110px_110px_130px_150px_40px] gap-3 border-b border-[color:var(--nx-line)] bg-[color:var(--nx-bg)] px-4 py-2 text-[11px] font-medium uppercase tracking-wide text-[color:var(--nx-faint)] md:grid">
            <span>Beleg</span><span>Datum</span><span>Fällig</span><span class="text-right">Betrag</span><span>Status</span><span></span>
        </div>
        <div class="divide-y divide-[color:var(--nx-line)]">
            @foreach ($invoices as $inv)
                <div class="grid grid-cols-[1fr_auto] items-center gap-x-3 gap-y-1 px-4 py-3 md:grid-cols-[1fr_110px_110px_130px_150px_40px]">
                    <div class="min-w-0">
                        <div class="truncate text-sm font-medium">{{ $inv['kind'] }} {{ $inv['number'] }}</div>
                        <div class="text-xs text-[color:var(--nx-faint)] md:hidden">{{ $de($inv['date'], 'D. MMM YYYY') }}{{ $inv['due'] && $inv['open'] > 0 ? ' · fällig ' . $de($inv['due'], 'D. MMM') : '' }}</div>
                    </div>
                    <span class="hidden text-sm tabular-nums text-[color:var(--nx-muted)] md:block">{{ $de($inv['date'], 'D. MMM YYYY') }}</span>
                    <span class="hidden text-sm tabular-nums text-[color:var(--nx-muted)] md:block">{{ $inv['due'] ? $de($inv['due'], 'D. MMM YYYY') : '–' }}</span>
                    <div class="text-right">
                        <div class="text-sm font-medium tabular-nums">{{ $money($inv['gross'], $cur) }}</div>
                        @if ($inv['paid'] > 0 && $inv['open'] > 0)<div class="text-[11px] tabular-nums text-[color:var(--nx-faint)]">{{ $money($inv['open'], $cur) }} offen</div>@endif
                    </div>
                    <div class="col-span-2 flex items-center gap-2 md:col-span-1">
                        <x-nx-badge :variant="$variant[$inv['status']] ?? 'neutral'" dot>{{ $inv['status_label'] }}</x-nx-badge>
                        @if ($inv['status'] === 'paid' && $inv['paid_at'])<span class="text-[11px] text-[color:var(--nx-faint)]">{{ $de($inv['paid_at'], 'D. MMM') }}</span>@endif
                    </div>
                    <div class="col-span-2 md:col-span-1 md:text-right">
                        @if ($inv['has_document'])
                            <a href="{{ $document($inv['ref']) }}" target="_blank" rel="noopener" title="Beleg als PDF"
                               class="inline-flex items-center gap-1 rounded-[6px] px-1.5 py-1 text-xs text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)] hover:text-[color:var(--nx-text)]">
                                @svg('heroicon-o-arrow-down-tray', 'w-4 h-4')<span class="md:hidden">Beleg (PDF)</span>
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @if ($compact && count($finance['invoices']) > $invoices->count())
        <a href="{{ $pageLink('rechnungen') }}" wire:navigate class="mt-2 inline-flex items-center gap-1 text-sm text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]">Alle {{ count($finance['invoices']) }} Belege @svg('heroicon-o-arrow-right', 'w-3.5 h-3.5')</a>
    @endif
@endif

@if (! $compact && $finance['payments'])
    <div class="mt-8">
        <h2 class="gp-h2 mb-3">Zahlungseingänge</h2>
        <div class="gp-card divide-y divide-[color:var(--nx-line)]">
            @foreach ($finance['payments'] as $p)
                <div class="flex items-center gap-3 px-4 py-3">
                    <span class="text-[color:var(--nx-success)]">@svg('heroicon-s-banknotes', 'w-4 h-4')</span>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm">{{ $p['label'] }}</div>
                        <div class="text-xs text-[color:var(--nx-faint)]">{{ $de($p['at'], 'D. MMMM YYYY') }}</div>
                    </div>
                    <span class="shrink-0 text-sm font-medium tabular-nums text-[color:var(--nx-success)]">+ {{ $money($p['amount'], $cur) }}</span>
                </div>
            @endforeach
        </div>
    </div>
@endif
