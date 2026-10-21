@php
    $inquiry = $recipient->inquiry;
    $feld = 'block w-full rounded-[6px] border border-[color:var(--nx-line-strong)] bg-[color:var(--nx-surface)] px-3 py-2 text-sm text-[color:var(--nx-text)] transition-colors focus:border-[color:var(--nx-accent)] focus:outline-none focus:ring-1 focus:ring-[color:var(--nx-accent)]';
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Rückfrage beantworten" icon="heroicon-o-inbox" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organisation', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Meine Rückfragen', 'href' => route('organization.my-inquiries.index')],
            ['label' => 'Beantworten'],
        ]">
            <x-nx-button variant="primary" size="sm" wire:click="submit">
                @svg('heroicon-o-paper-airplane', 'w-4 h-4')
                <span>Antwort absenden</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
        <div class="mx-auto max-w-2xl space-y-5">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="m-0 text-2xl font-semibold tracking-tight text-[color:var(--nx-text)]">{{ $inquiry->entity?->name ?? 'Rückfrage' }}</h1>
                @if ($inquiry->inquiry_type)
                    <x-nx-badge>{{ $inquiry->inquiry_type }}</x-nx-badge>
                @endif
                @if ($inquiry->depth > 0)
                    <x-nx-badge variant="info">Runde {{ $inquiry->depth + 1 }}</x-nx-badge>
                @endif
                @if ($inquiry->due_date)
                    <span class="ml-auto text-xs {{ $inquiry->due_date->isPast() ? 'font-medium text-[color:var(--nx-danger)]' : 'text-[color:var(--nx-faint)]' }}">
                        fällig {{ $inquiry->due_date->format('d.m.Y') }}
                    </span>
                @endif
            </div>

            @if ($inquiry->context_summary)
                <x-nx-callout variant="neutral" icon="heroicon-o-chat-bubble-left-ellipsis">{{ $inquiry->context_summary }}</x-nx-callout>
            @endif

            <x-nx-panel title="Antwort">
                <form wire:submit="submit" class="space-y-4">
                    @forelse ($inquiry->fields ?? [] as $field)
                        <div>
                            @if ($field['type'] !== 'boolean')
                                <label for="resp-{{ $field['key'] }}" class="mb-1 block text-xs font-medium text-[color:var(--nx-text)]">
                                    {{ $field['label'] }}
                                    @if (empty($field['optional']))
                                        <span class="text-[color:var(--nx-danger)]">*</span>
                                    @endif
                                </label>
                            @endif

                            @switch($field['type'])
                                @case('select')
                                    <select id="resp-{{ $field['key'] }}" wire:model="responses.{{ $field['key'] }}" class="{{ $feld }}">
                                        <option value="">Bitte wählen…</option>
                                        @foreach ($field['options'] ?? [] as $option)
                                            <option value="{{ $option }}">{{ $option }}</option>
                                        @endforeach
                                    </select>
                                    @break

                                @case('text')
                                    <textarea id="resp-{{ $field['key'] }}" wire:model="responses.{{ $field['key'] }}" rows="3" class="{{ $feld }}" placeholder="Deine Antwort…"></textarea>
                                    @break

                                @case('boolean')
                                    <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-[color:var(--nx-text)]">
                                        <input type="checkbox" wire:model="responses.{{ $field['key'] }}" class="h-4 w-4 rounded-[4px] accent-[var(--nx-accent)]" />
                                        <span>{{ $field['label'] }}</span>
                                    </label>
                                    @break

                                @case('number')
                                    <input id="resp-{{ $field['key'] }}" type="number" wire:model="responses.{{ $field['key'] }}" class="{{ $feld }}" placeholder="0" />
                                    @break

                                @case('date')
                                    <input id="resp-{{ $field['key'] }}" type="date" wire:model="responses.{{ $field['key'] }}" class="{{ $feld }}" />
                                    @break
                            @endswitch

                            @error("responses.{$field['key']}")
                                <p class="mt-1 text-xs text-[color:var(--nx-danger)]">{{ $message }}</p>
                            @enderror
                        </div>
                    @empty
                        <p class="text-sm text-[color:var(--nx-muted)]">Diese Rückfrage hat keine Felder.</p>
                    @endforelse

                    <div class="flex items-center justify-end gap-2 border-t border-[color:var(--nx-line)] pt-4">
                        <x-nx-button variant="ghost" :href="route('organization.my-inquiries.index')" wire:navigate>Abbrechen</x-nx-button>
                        <x-nx-button variant="primary" type="submit">Antwort absenden</x-nx-button>
                    </div>
                </form>
            </x-nx-panel>
        </div>
    </x-ui-page-container>
</x-ui-page>
