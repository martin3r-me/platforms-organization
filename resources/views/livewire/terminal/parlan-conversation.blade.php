<div class="flex flex-col h-full text-[var(--t-text)]">
    <div class="flex-1 min-h-0 overflow-y-auto p-4">
        <div class="max-w-md mx-auto w-full space-y-3">
            @if($this->hasContext())
                <div class="text-[13px] leading-relaxed text-[var(--t-text-muted)]">
                    Gespräche zu <span class="text-[var(--t-text)] font-medium">{{ $contextSubject ?? ('#'.$contextId) }}</span>
                    laufen in Parlan — ein Raum pro Objekt, für alle, die es sehen dürfen.
                    Was dort besprochen und entschieden wird, fließt in die Organisation zurück.
                </div>
                <a href="{{ $jump }}" target="_blank" rel="noopener"
                    class="w-full inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-[13px] font-semibold bg-[var(--t-accent,#17794c)] text-white hover:opacity-90">
                    In Parlan besprechen
                    <span aria-hidden="true">↗</span>
                </a>
            @else
                <div class="text-[12px] text-[var(--t-text-muted)]">Kein Objekt im Kontext.</div>
            @endif
        </div>
    </div>
</div>
