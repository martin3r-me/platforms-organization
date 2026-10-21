@php
    use Platform\Organization\Models\OrganizationRole;

    $tree = $this->itemTree;
    $statusOptions = [
        ['value' => 'active', 'label' => 'Aktiv'],
        ['value' => 'archived', 'label' => 'Archiviert'],
    ];
    $vsmOptions = collect(OrganizationRole::VSM_LABELS)->map(fn ($l, $c) => ['value' => $c, 'label' => $l])->values()->all();
    $stageOptions = collect(OrganizationRole::STAGES)->map(fn ($s) => ['value' => $s, 'label' => $s])->values()->all();
    $ownerOptions = $this->availableEntities->map(fn ($e) => ['value' => $e->id, 'label' => $e->name])->values()->all();
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Rollen" icon="heroicon-o-identification" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organisation', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Rollen'],
        ]">
            <x-slot name="left">
                <div class="flex items-center gap-3">
                    <div class="w-56">
                        <x-nx-input-text name="search" size="sm" placeholder="Name, Slug, Beschreibung…" wire:model.live.debounce.400ms="search" />
                    </div>
                    <div class="w-36">
                        <x-nx-input-select name="statusFilter" size="sm" wire:model.live="statusFilter"
                            :options="$statusOptions" nullable nullLabel="Alle" />
                    </div>
                </div>
            </x-slot>
            <x-nx-button variant="primary" size="sm" wire:click="create">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neue Rolle</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
        <div class="space-y-5">
            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <h1 class="m-0 text-2xl font-semibold tracking-tight text-[color:var(--nx-text)]">Rollen</h1>
                <span class="text-sm text-[color:var(--nx-muted)]">nach Owner-Einheit</span>
            </div>

            <x-nx-card flush>
                @if (count($tree) === 0 && $this->roles->isEmpty())
                    <x-nx-empty icon="heroicon-o-identification">
                        Keine Rollen gefunden.
                        <x-slot name="action">
                            <x-nx-button size="sm" wire:click="create">Rolle anlegen</x-nx-button>
                        </x-slot>
                    </x-nx-empty>
                @else
                    <div class="[&>*:last-child]:border-b-0">
                        @foreach ($tree as $node)
                            @include('organization::livewire.role.partials.owner-node', ['node' => $node])
                        @endforeach
                    </div>
                @endif
            </x-nx-card>
        </div>
    </x-ui-page-container>

    <x-nx-modal size="md" wire:model="modalShow">
        <x-slot name="header">{{ $editingId ? 'Rolle bearbeiten' : 'Neue Rolle' }}</x-slot>

        <form wire:submit.prevent="store" class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <x-nx-input-text name="name" label="Name" wire:model.live="form.name" errorKey="form.name" required placeholder="z. B. Projektleitung" />
                <x-nx-input-text name="slug" label="Slug" wire:model.live="form.slug" errorKey="form.slug" placeholder="wird automatisch erzeugt" />
            </div>
            <x-nx-input-textarea name="description" label="Beschreibung" wire:model.live="form.description" rows="3" placeholder="Optional" />

            <x-nx-input-select name="vsm_system" label="VSM-Funktion" hint="z. B. GF → S3, Inhaber → S5" wire:model.live="form.vsm_system" errorKey="form.vsm_system"
                :options="$vsmOptions" nullable nullLabel="Keine VSM-Bedeutung" />

            <div class="grid grid-cols-2 gap-3">
                <x-nx-input-select name="owner_entity_id" label="Owner" wire:model.live="form.owner_entity_id" errorKey="form.owner_entity_id"
                    :options="$ownerOptions" nullable nullLabel="Kein Owner" />
                <x-nx-input-select name="status" label="Status" wire:model.live="form.status" errorKey="form.status"
                    :options="$statusOptions" required />
            </div>

            <x-nx-input-select name="stage" label="Stufe" hint="Dev-Loop einer Agent-Rolle, optional" wire:model.live="form.stage" errorKey="form.stage"
                :options="$stageOptions" nullable nullLabel="Keine" />

            <div class="space-y-2 border-t border-[color:var(--nx-line)] pt-3">
                <div class="text-xs font-medium text-[color:var(--nx-text)]">Berechtigungen</div>
                <p class="m-0 text-xs text-[color:var(--nx-muted)]">
                    Wird die Rolle einer Person an einer Kontext-Einheit zugewiesen, erhält sie diese Stufe auf die Einheit und ihren Teilbaum.
                    Ohne Auswahl entsteht kein Zugriff; die höchste Stufe gewinnt.
                </p>
                <div class="flex flex-col items-start gap-2">
                    <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-[color:var(--nx-text)]">
                        <input type="checkbox" value="read" wire:model.live="form.capabilities" class="h-4 w-4 rounded-[4px] accent-[var(--nx-accent)]" />
                        <span>Lesen <span class="text-xs text-[color:var(--nx-muted)]">— Objekte im Teilbaum sehen</span></span>
                    </label>
                    <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-[color:var(--nx-text)]">
                        <input type="checkbox" value="write" wire:model.live="form.capabilities" class="h-4 w-4 rounded-[4px] accent-[var(--nx-accent)]" />
                        <span>Bearbeiten <span class="text-xs text-[color:var(--nx-muted)]">— sehen und ändern</span></span>
                    </label>
                    <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-[color:var(--nx-text)]">
                        <input type="checkbox" value="manage" wire:model.live="form.capabilities" class="h-4 w-4 rounded-[4px] accent-[var(--nx-accent)]" />
                        <span>Verwalten <span class="text-xs text-[color:var(--nx-muted)]">— voller Zugriff inkl. Löschen</span></span>
                    </label>
                </div>
                @error('form.capabilities.*')
                    <p class="m-0 text-xs text-[color:var(--nx-danger)]">{{ $message }}</p>
                @enderror
            </div>
        </form>

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-nx-button variant="ghost" wire:click="$set('modalShow', false)">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="store">{{ $editingId ? 'Speichern' : 'Anlegen' }}</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>
</x-ui-page>
