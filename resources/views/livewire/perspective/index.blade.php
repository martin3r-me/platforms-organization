<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Perspektiven'],
        ]">
            <x-ui-button variant="primary" size="sm" wire:click="create">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neue Perspektive</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Suche</h3>
                    <x-ui-input-text name="search" wire:model.live="search" placeholder="Suche Perspektiven..." class="w-full" size="sm" />
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitaeten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-6 text-sm text-[var(--ui-muted)]">Keine Aktivitaeten verfuegbar</div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container>
        <x-ui-table compact="true">
            <x-ui-table-header>
                <x-ui-table-header-cell compact="true">Name</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Beschreibung</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Standard</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Erstellt am</x-ui-table-header-cell>
            </x-ui-table-header>

            <x-ui-table-body>
                @foreach($this->perspectives as $perspective)
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true">
                            <a href="{{ route('organization.perspectives.show', $perspective) }}" class="link">{{ $perspective->name }}</a>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">{{ Str::limit($perspective->description, 80) }}</x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            @if($perspective->is_default)
                                <x-ui-badge variant="success">Standard</x-ui-badge>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">{{ $perspective->created_at->format('d.m.Y') }}</x-ui-table-cell>
                    </x-ui-table-row>
                @endforeach
            </x-ui-table-body>
        </x-ui-table>
    </x-ui-page-container>

    <!-- Create Perspective Modal -->
    <x-ui-modal
        wire:model="modalShow"
        size="lg"
    >
        <x-slot name="header">
            Neue Perspektive erstellen
        </x-slot>

        <div class="space-y-4">
            <form wire:submit.prevent="store" class="space-y-4">
                <div class="grid grid-cols-1 gap-4">
                    <x-ui-input-text
                        name="name"
                        label="Name"
                        wire:model.live="form.name"
                        required
                        placeholder="Name der Perspektive"
                    />

                    <x-ui-input-textarea
                        name="description"
                        label="Beschreibung"
                        wire:model.live="form.description"
                        placeholder="Optionale Beschreibung"
                        rows="3"
                    />
                </div>

                <div class="flex items-center">
                    <input
                        type="checkbox"
                        wire:model.live="form.is_default"
                        id="is_default"
                        class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50"
                    />
                    <label for="is_default" class="ml-2 text-sm text-gray-700">Als Standard-Perspektive setzen</label>
                </div>
            </form>
        </div>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button
                    type="button"
                    variant="secondary-outline"
                    wire:click="$set('modalShow', false)"
                >
                    Abbrechen
                </x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="store">
                    @svg('heroicon-o-plus', 'w-4 h-4 mr-2')
                    Erstellen
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>
</x-ui-page>
