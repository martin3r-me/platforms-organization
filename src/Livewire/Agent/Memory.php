<?php

namespace Platform\Organization\Livewire\Agent;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Organization\Models\OrganizationAgentCuration;
use Platform\Organization\Models\OrganizationAgentMemoryDoc;
use Platform\Organization\Models\OrganizationAgentRule;
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

    /** rules = Arbeitsweise (Regeln), files = Notizen & Dateien */
    public string $view = 'rules';

    /** live | proposed | history */
    public string $ruleFilter = 'live';

    public ?int $editingRule = null;

    public string $ruleDraft = '';

    public string $ruleNote = '';

    public ?int $confirmRetireRule = null;

    /** approve | reject — Umzug bestätigen */
    public ?string $confirmImport = null;

    private const KIND_LABELS = [
        'routine' => 'Arbeitsweise',
        'index' => 'Index',
        'questions' => 'Offene Fragen',
        'playbook' => 'Notizen',
    ];

    public function mount(OrganizationEntity $entity): void
    {
        $this->entity = $entity;
        $this->view = OrganizationAgentRule::where('organization_entity_id', $entity->id)->exists() ? 'rules' : 'files';
        if ($this->view === 'rules' && ! $this->agentRules->contains(fn ($r) => $r->isLive())) {
            $this->ruleFilter = 'proposed';
        }
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

    // ── Arbeitsweise (Regeln) ──

    #[Computed]
    public function agentRules()
    {
        return OrganizationAgentRule::where('organization_entity_id', $this->entity->id)
            ->orderBy('ord')->orderBy('rule_id')->get();
    }

    /** Stand des Umzugs, wie das Engram ihn zuletzt gemeldet hat (mode, proposed_sha). */
    #[Computed]
    public function rulesMeta(): array
    {
        $s = $this->entity->agentProfile?->settings;

        return is_array($s) && is_array($s['rules'] ?? null) ? $s['rules'] : [];
    }

    /** Regeln für die aktuelle Ansicht, gruppiert (Ablauf / Kern / nach Anlass) und nach Suche gefiltert. */
    #[Computed]
    public function ruleGroups(): array
    {
        $statuses = match ($this->ruleFilter) {
            'proposed' => ['proposed'],
            'history' => ['superseded', 'retired'],
            default => OrganizationAgentRule::LIVE,
        };
        $q = mb_strtolower(trim($this->search));
        $groups = ['Ablauf' => [], 'Regeln' => [], 'Nach Anlass' => []];
        foreach ($this->agentRules as $r) {
            if (! in_array($r->status, $statuses, true)) {
                continue;
            }
            if ($q !== '' && ! str_contains(mb_strtolower($r->text . ' ' . $r->trigger . ' ' . $r->why . ' r' . $r->rule_id), $q)) {
                continue;
            }
            $key = $r->trigger !== '' ? 'Nach Anlass' : ($r->kind === 'step' ? 'Ablauf' : 'Regeln');
            $groups[$key][] = $r;
        }

        return array_filter($groups);
    }

    public function ruleCounts(): array
    {
        $c = ['live' => 0, 'proposed' => 0, 'history' => 0];
        foreach ($this->agentRules as $r) {
            $c[$r->isLive() ? 'live' : ($r->status === 'proposed' ? 'proposed' : 'history')]++;
        }

        return $c;
    }

    public function pendingForRule(int $ruleId): ?OrganizationAgentCuration
    {
        return $this->pendingFor('rules/' . $ruleId);
    }

    public function hasPendingRuleOrders(): bool
    {
        return $this->curations->contains(fn ($c) => $c->status === 'pending' && str_starts_with($c->path, 'rules'));
    }

    public function setView(string $view): void
    {
        $this->view = in_array($view, ['rules', 'files'], true) ? $view : 'rules';
        $this->reset(['editingRule', 'ruleDraft', 'ruleNote', 'confirmRetireRule', 'confirmImport', 'flash', 'editing', 'confirmRetire']);
    }

    public function setRuleFilter(string $f): void
    {
        $this->ruleFilter = in_array($f, ['live', 'proposed', 'history'], true) ? $f : 'live';
        $this->reset(['editingRule', 'confirmRetireRule']);
    }

    public function editRule(int $ruleId): void
    {
        $r = $this->agentRules->firstWhere('rule_id', $ruleId);
        if (! $r || $this->pendingForRule($ruleId)) {
            return;
        }
        $this->editingRule = $ruleId;
        $this->ruleDraft = $r->text;
        $this->ruleNote = '';
        $this->confirmRetireRule = null;
    }

    public function cancelRule(): void
    {
        $this->reset(['editingRule', 'ruleDraft', 'ruleNote', 'confirmRetireRule', 'confirmImport']);
    }

    public function submitRule(): void
    {
        $r = $this->editingRule ? $this->agentRules->firstWhere('rule_id', $this->editingRule) : null;
        if (! $r || $this->pendingForRule($r->rule_id)) {
            return;
        }
        $this->validate(['ruleDraft' => 'required|string|max:4000', 'ruleNote' => 'nullable|string|max:1000']);
        if (trim($this->ruleDraft) === trim($r->text)) {
            $this->flash = 'Keine Änderung — nichts zu senden.';

            return;
        }
        $this->ruleOrder($r, 'revise', trim($this->ruleDraft), $this->ruleNote);
        $this->reset(['editingRule', 'ruleDraft', 'ruleNote']);
        $this->flash = "Korrektur für r{$r->rule_id} gesendet — die neue Fassung löst die alte beim nächsten Takt ab.";
    }

    public function retireRule(int $ruleId): void
    {
        $r = $this->agentRules->firstWhere('rule_id', $ruleId);
        if (! $r || $this->pendingForRule($ruleId) || ! in_array($r->status, ['active', 'probation', 'proposed'], true)) {
            return;
        }
        if ($this->confirmRetireRule !== $ruleId) {
            $this->confirmRetireRule = $ruleId;
            $this->editingRule = null;

            return;
        }
        $this->ruleOrder($r, 'retire', null, $this->ruleNote);
        $this->reset(['confirmRetireRule', 'ruleNote']);
        $this->flash = "r{$r->rule_id} wird stillgelegt.";
    }

    public function approveRule(int $ruleId): void
    {
        $r = $this->agentRules->firstWhere('rule_id', $ruleId);
        if (! $r || $this->pendingForRule($ruleId) || $r->status !== 'probation') {
            return;
        }
        $this->ruleOrder($r, 'approve', null, null);
        $this->flash = "r{$r->rule_id} wird als bewährt freigegeben.";
    }

    public function import(string $what): void
    {
        $sha = $this->rulesMeta['proposed_sha'] ?? null;
        if (! in_array($what, ['approve', 'reject'], true) || ! $sha || $this->hasPendingRuleOrders()) {
            return;
        }
        if ($this->confirmImport !== $what) {
            $this->confirmImport = $what;

            return;
        }
        OrganizationAgentCuration::create([
            'organization_entity_id' => $this->entity->id,
            'path' => 'rules',
            'op' => $what === 'approve' ? 'approve_import' : 'reject_import',
            'base_sha' => $sha,
            'note' => trim($this->ruleNote) !== '' ? trim($this->ruleNote) : null,
            'status' => 'pending',
            'created_by_user_id' => auth()->id(),
        ]);
        unset($this->curations);
        $this->reset(['confirmImport', 'ruleNote']);
        $this->flash = $what === 'approve'
            ? 'Umzug freigegeben — ab dem nächsten Takt führen die Regeln.'
            : 'Umzug verworfen — die bisherige routine.md führt weiter.';
    }

    private function ruleOrder(OrganizationAgentRule $r, string $op, ?string $content, ?string $note): void
    {
        OrganizationAgentCuration::create([
            'organization_entity_id' => $this->entity->id,
            'path' => 'rules/' . $r->rule_id,
            'op' => $op,
            'base_sha' => $r->sha,
            'content' => $content,
            'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
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
