<div class="px-3 py-2">
    <label class="block text-[0.625rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider mb-1 px-1">Perspektive (Carrier-Entity)</label>
    <select wire:change="switchPerspective($event.target.value)"
        class="w-full text-xs rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] text-[var(--ui-secondary)] px-3 py-2 focus:border-[var(--ui-primary)] focus:ring-1 focus:ring-[var(--ui-primary)] transition">
        @foreach($carriers as $carrier)
            <option value="{{ $carrier['id'] }}" @selected($carrier['is_active'])>
                {{ $carrier['name'] }}{{ $carrier['is_root'] ? ' (Root)' : '' }} · {{ $carrier['type_name'] }}
            </option>
        @endforeach
    </select>
</div>
