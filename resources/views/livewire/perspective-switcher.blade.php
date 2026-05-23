<div class="px-3 py-2">
    <label class="block text-[0.625rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider mb-1 px-1">Perspektive</label>
    <select wire:change="switchPerspective($event.target.value)"
        class="w-full text-xs rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] text-[var(--ui-secondary)] px-3 py-2 focus:border-[var(--ui-primary)] focus:ring-1 focus:ring-[var(--ui-primary)] transition">
        @foreach($perspectives as $perspective)
            <option value="{{ $perspective['id'] }}" @selected($perspective['is_active'])>
                {{ $perspective['name'] }}{{ $perspective['is_default'] ? ' (Standard)' : '' }}
            </option>
        @endforeach
    </select>
</div>
