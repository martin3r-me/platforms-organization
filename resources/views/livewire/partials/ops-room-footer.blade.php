{{--
    Shared Cybersyn-Footer.
    Erwartete Variablen: $p (Entity|null), $perspectiveEntityId (int|null)
--}}
<footer class="flex-shrink-0 border-t border-zinc-800 px-6 py-1.5 flex items-center justify-between text-[9px] tracking-[0.35em] text-zinc-600 uppercase">
    <div class="flex items-center gap-3">
        <span>Stafford Beer · Santiago de Chile · 1972</span>
        <span class="text-zinc-700">—</span>
        <span class="text-zinc-500">{{ $p?->name ?? '—' }} · 2026</span>
    </div>
    <div class="flex items-center gap-2 font-mono">
        <span class="w-1.5 h-1.5 bg-emerald-500 animate-pulse rounded-full"></span>
        <span class="text-zinc-500">Live</span>
        <span class="text-zinc-700">·</span>
        <span class="text-zinc-600">Persp/{{ str_pad((string) ($perspectiveEntityId ?? 0), 4, '0', STR_PAD_LEFT) }}</span>
    </div>
</footer>
