<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Einstellungen'],
            ['label' => 'Synthesis-Prompts', 'href' => route('organization.settings.synthesis-prompts.index')],
            ['label' => $definition->name],
        ]">
            <x-ui-button variant="primary" size="sm" wire:click="save">
                @svg('heroicon-o-check', 'w-4 h-4')
                <span>Speichern</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
        <div class="max-w-4xl mx-auto space-y-6">
            @if($savedMessage)
                <div class="p-3 rounded-md bg-green-50 border border-green-200 text-sm text-green-800 inline-flex items-center gap-2">
                    @svg('heroicon-o-check-circle', 'w-4 h-4')
                    {{ $savedMessage }}
                </div>
            @endif

            {{-- Metadata --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Metadaten</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Name</label>
                        <input wire:model="name" type="text" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                        @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Beschreibung (optional)</label>
                        <input wire:model="description" type="text" class="w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="Kurze Notiz wofür dieser Prompt verwendet wird">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Report-Typ</label>
                        <select wire:model="report_type" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="weekly">Wöchentlich</option>
                            <option value="monthly">Monatlich</option>
                            <option value="quarterly">Quartalsweise</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Status</label>
                        <label class="flex items-center gap-2 mt-2 text-sm text-[var(--ui-secondary)]">
                            <input wire:model="is_active" type="checkbox" class="rounded border-gray-300 text-[var(--ui-primary)] focus:ring-[var(--ui-primary)]">
                            Prompt ist aktiv
                        </label>
                    </div>
                </div>
            </div>

            {{-- LLM Settings --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">LLM-Einstellungen</h2>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Model (leer = Default)</label>
                        <input wire:model="model" type="text" class="w-full rounded-md border-gray-300 shadow-sm text-sm font-mono" placeholder="claude-sonnet-4-6">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Max. Signale</label>
                        <input wire:model="max_signals" type="number" min="1" max="1000" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                        @error('max_signals') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Max. Tokens</label>
                        <input wire:model="max_tokens" type="number" min="512" max="32768" step="512" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                        @error('max_tokens') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- System Prompt --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-[var(--ui-secondary)]">System-Prompt</h2>
                    <button wire:click="resetToDefault('system_prompt')" type="button" class="text-xs text-[var(--ui-muted)] hover:text-[var(--ui-primary)] inline-flex items-center gap-1">
                        @svg('heroicon-o-arrow-uturn-left', 'w-3.5 h-3.5')
                        Auf Default zurücksetzen
                    </button>
                </div>
                <p class="text-xs text-[var(--ui-muted)] mb-2">Definiert die Rolle und Aufgabe des LLM (z.B. „Du bist ein strategischer Analyst…"). Markdown im Output.</p>
                <textarea wire:model="system_prompt" rows="8" class="w-full rounded-md border-gray-300 shadow-sm text-sm font-mono"></textarea>
                @error('system_prompt') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- User Message Template --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-[var(--ui-secondary)]">User-Message-Template</h2>
                    <button wire:click="resetToDefault('user_message_template')" type="button" class="text-xs text-[var(--ui-muted)] hover:text-[var(--ui-primary)] inline-flex items-center gap-1">
                        @svg('heroicon-o-arrow-uturn-left', 'w-3.5 h-3.5')
                        Auf Default zurücksetzen
                    </button>
                </div>
                <p class="text-xs text-[var(--ui-muted)] mb-2">
                    Die Nachricht an das LLM. Verfügbare Platzhalter:
                    <code class="px-1 bg-[var(--ui-muted-5)] rounded">{report_type}</code>
                    <code class="px-1 bg-[var(--ui-muted-5)] rounded">{period_start}</code>
                    <code class="px-1 bg-[var(--ui-muted-5)] rounded">{period_end}</code>
                    <code class="px-1 bg-[var(--ui-muted-5)] rounded">{signals_count}</code>
                    <code class="px-1 bg-[var(--ui-muted-5)] rounded">{signals_json}</code>
                </p>
                <textarea wire:model="user_message_template" rows="8" class="w-full rounded-md border-gray-300 shadow-sm text-sm font-mono"></textarea>
                @error('user_message_template') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>
