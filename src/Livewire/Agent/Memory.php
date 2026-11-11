<?php

namespace Platform\Organization\Livewire\Agent;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Organization\Models\OrganizationAgentCuration;
use Platform\Organization\Models\OrganizationAgentMemoryDoc;
use Platform\Organization\Models\OrganizationEntity;

/**
 * Gedächtnis-Reiter eines Engrams: die steuernden Vault-Dateien (Arbeitsweise, Index, offene Fragen,
 * Playbooks), wie der Daemon sie spiegelt — und Kuratieren als Auftrag. Die Org schreibt nie direkt ins
 * Vault: ein Auftrag (ersetzen/stilllegen) wird vom Engram beim nächsten Takt angewendet, nur auf dem
 * Stand, den man hier gesehen hat, mit Sicherung und Quittung.
 */
class Memory extends Component
{
    public OrganizationEntity $entity;

    public string $search = '';

    public ?string $selected = null;

    public bool $editing = false;

    public string $draft = '';

    public string $note = '';

    public bool $confirmRetire = false;

    public ?string $flash = null;

    private const KIND_LABELS = [
        'routine' => 'Arbeitsweise',
        'index' => 'Index',
        'questions' => 'Offene Fragen',
        'playbook' => 'Notizen',
    ];

    public function mount(OrganizationEntity $entity): void
    {
        $this->entity = $entity;
        $this->selected = $this->docs()->firstWhere('kind', 'routine')?->path ?? $this->docs()->first()?->path;
    }

    #[Computed]
    public function docs()
    {
        $order = array_flip(OrganizationAgentMemoryDoc::KINDS);

        return OrganizationAgentMemoryDoc::where('organization_entity_id', $this->entity->id)
            ->orderBy('path')->get()
            ->sortBy(fn ($d) => $order[$d->kind] ?? 9, SORT_NUMERIC)->values();
    }

    /** Gruppiert + gefiltert für die linke Liste (Suche trifft Pfad, Titel und Inhalt). */
    #[Computed]
    public function groups(): array
    {
        $q = mb_strtolower(trim($this->search));
        $out = [];
        foreach ($this->docs as $d) {
            $hits = 0;
            if ($q !== '') {
                $hits = mb_substr_count(mb_strtolower($d->content), $q);
                if ($hits === 0 && ! str_contains(mb_strtolower($d->path . ' ' . $d->title), $q)) {
                    continue;
                }
            }
            $out[self::KIND_LABELS[$d->kind] ?? $d->kind][] = ['doc' => $d, 'hits' => $hits];
        }

        return $out;
    }

    #[Computed]
    public function current(): ?OrganizationAgentMemoryDoc
    {
        return $this->selected ? $this->docs->firstWhere('path', $this->selected) : null;
    }

    #[Computed]
    public function curations()
    {
        return OrganizationAgentCuration::where('organization_entity_id', $this->entity->id)
            ->orderByDesc('id')->limit(30)->get();
    }

    public function pendingFor(string $path): ?OrganizationAgentCuration
    {
        return $this->curations->first(fn ($c) => $c->path === $path && $c->status === 'pending');
    }

    public function select(string $path): void
    {
        $this->selected = $path;
        $this->reset(['editing', 'draft', 'note', 'confirmRetire', 'flash']);
    }

    public function edit(): void
    {
        $doc = $this->current;
        if (! $doc || $this->pendingFor($doc->path)) {
            return;
        }
        $this->draft = $doc->content;
        $this->note = '';
        $this->editing = true;
        $this->confirmRetire = false;
    }

    public function cancelEdit(): void
    {
        $this->reset(['editing', 'draft', 'note', 'confirmRetire']);
    }

    public function submitEdit(): void
    {
        $doc = $this->current;
        if (! $doc || $this->pendingFor($doc->path)) {
            return;
        }
        $this->validate(['draft' => 'present|string|max:400000', 'note' => 'nullable|string|max:1000']);
        if (str_replace("\r\n", "\n", $this->draft) === $doc->content) {
            $this->flash = 'Keine Änderung — nichts zu senden.';

            return;
        }
        $this->order($doc, 'replace', str_replace("\r\n", "\n", $this->draft));
        $this->reset(['editing', 'draft', 'note']);
        $this->flash = 'Auftrag gesendet — das Engram übernimmt ihn beim nächsten Takt.';
    }

    public function retire(): void
    {
        $doc = $this->current;
        if (! $doc || $doc->kind !== 'playbook' || $this->pendingFor($doc->path)) {
            return;
        }
        if (! $this->confirmRetire) {
            $this->confirmRetire = true;

            return;
        }
        $this->validate(['note' => 'nullable|string|max:1000']);
        $this->order($doc, 'retire', null);
        $this->reset(['confirmRetire', 'note']);
        $this->flash = 'Stilllegen beauftragt — die Notiz wird beim nächsten Takt archiviert.';
    }

    public function withdraw(int $id): void
    {
        OrganizationAgentCuration::where('organization_entity_id', $this->entity->id)
            ->where('status', 'pending')->whereKey($id)
            ->update(['status' => 'cancelled', 'result' => 'zurückgezogen', 'applied_at' => now()]);
        unset($this->curations);
    }

    private function order(OrganizationAgentMemoryDoc $doc, string $op, ?string $content): void
    {
        OrganizationAgentCuration::create([
            'organization_entity_id' => $this->entity->id,
            'path' => $doc->path,
            'op' => $op,
            'base_sha' => $doc->sha,
            'content' => $content,
            'note' => trim($this->note) !== '' ? trim($this->note) : null,
            'status' => 'pending',
            'created_by_user_id' => auth()->id(),
        ]);
        unset($this->curations);
    }

    public function render()
    {
        return view('organization::livewire.agent.memory', [
            'mirroredAt' => $this->docs->max('updated_at'),
        ]);
    }
}
