<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Meine Inquiries', 'href' => route('organization.my-inquiries.index')],
            ['label' => 'Beantworten'],
        ]">
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
        @php $inquiry = $recipient->inquiry; @endphp

        {{-- Inquiry Info Header --}}
        <div class="mb-6 p-4 bg-[var(--ui-surface)] border border-[var(--ui-border)] rounded-lg space-y-3">
            <div class="flex items-center gap-4">
                <div>
                    <span class="text-xs uppercase tracking-wider text-[var(--ui-muted)]">Entity</span>
                    <p class="font-medium text-[var(--ui-primary)]">{{ $inquiry->entity?->name ?? '–' }}</p>
                </div>
                <div>
                    <span class="text-xs uppercase tracking-wider text-[var(--ui-muted)]">Typ</span>
                    <p><x-ui-badge variant="info">{{ $inquiry->inquiry_type }}</x-ui-badge></p>
                </div>
                <div>
                    <span class="text-xs uppercase tracking-wider text-[var(--ui-muted)]">Fällig am</span>
                    <p class="text-sm {{ $inquiry->due_date?->isPast() ? 'text-red-600 font-bold' : '' }}">
                        {{ $inquiry->due_date?->format('d.m.Y') ?? '–' }}
                    </p>
                </div>
                @if($inquiry->depth > 0)
                    <div>
                        <span class="text-xs uppercase tracking-wider text-[var(--ui-muted)]">Runde</span>
                        <p class="text-sm">{{ $inquiry->depth + 1 }}</p>
                    </div>
                @endif
            </div>

            @if($inquiry->context_summary)
                <div class="pt-3 border-t border-[var(--ui-border)]">
                    <span class="text-xs uppercase tracking-wider text-[var(--ui-muted)]">Kontext</span>
                    <p class="text-sm text-[var(--ui-secondary)] mt-1">{{ $inquiry->context_summary }}</p>
                </div>
            @endif
        </div>

        {{-- Response Form --}}
        <form wire:submit="submit" class="space-y-6">
            @foreach($inquiry->fields ?? [] as $field)
                <div>
                    <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">
                        {{ $field['label'] }}
                        @if(empty($field['optional']))
                            <span class="text-red-500">*</span>
                        @endif
                    </label>

                    @switch($field['type'])
                        @case('select')
                            <select wire:model="responses.{{ $field['key'] }}"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 text-sm">
                                <option value="">Bitte wählen...</option>
                                @foreach($field['options'] ?? [] as $option)
                                    <option value="{{ $option }}">{{ $option }}</option>
                                @endforeach
                            </select>
                            @break

                        @case('text')
                            <textarea wire:model="responses.{{ $field['key'] }}"
                                      rows="3"
                                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 text-sm"
                                      placeholder="Ihre Antwort..."></textarea>
                            @break

                        @case('boolean')
                            <label class="inline-flex items-center gap-2">
                                <input type="checkbox" wire:model="responses.{{ $field['key'] }}"
                                       class="rounded border-gray-300 text-primary focus:ring-primary">
                                <span class="text-sm text-[var(--ui-secondary)]">Ja</span>
                            </label>
                            @break

                        @case('number')
                            <input type="number" wire:model="responses.{{ $field['key'] }}"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 text-sm"
                                   placeholder="0">
                            @break

                        @case('date')
                            <input type="date" wire:model="responses.{{ $field['key'] }}"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 text-sm">
                            @break
                    @endswitch

                    @error("responses.{$field['key']}")
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach

            <div class="flex items-center gap-4 pt-4 border-t border-[var(--ui-border)]">
                <button type="submit"
                        class="px-4 py-2 bg-[var(--ui-primary)] text-[var(--ui-on-primary)] rounded-md font-medium text-sm hover:opacity-90 transition">
                    Antwort absenden
                </button>
                <a href="{{ route('organization.my-inquiries.index') }}" class="text-sm text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]" wire:navigate>
                    Abbrechen
                </a>
            </div>
        </form>
    </x-ui-page-container>
</x-ui-page>
