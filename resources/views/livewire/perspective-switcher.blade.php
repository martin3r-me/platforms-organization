<div class="px-3 py-2.5">
    @php
        $options = collect($carriers)->map(fn ($c) => [
            'value' => $c['id'],
            'label' => $c['name'] . ($c['is_root'] ? ' (Root)' : '') . ' · ' . $c['type_name'],
        ])->all();
        $active = collect($carriers)->firstWhere('is_active', true)['id'] ?? null;
    @endphp
    <x-nx-input-select name="org-perspective" label="Perspektive" size="sm"
        :options="$options" :value="$active"
        wire:change="switchPerspective($event.target.value)" />
</div>
