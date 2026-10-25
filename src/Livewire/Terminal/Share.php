<?php

namespace Platform\Organization\Livewire\Terminal;

use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Core\Livewire\Terminal\Concerns\WithTerminalContext;
use Platform\Organization\Authz\DimensionLinkAuthorizer;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityShare;
use Platform\Organization\Services\EntityShareService;

/**
 * Kontext-Panel „Freigabe": diesen Knoten nach außen teilen (Kunden-Tor).
 * Eine URL, wahlweise nur für eingeladene Adressen; sichtbar ist, was im Baum
 * darunter hängt — eingeschränkt über Tiefe und Typen. Änderungen speichern sofort.
 */
class Share extends Component
{
    use WithTerminalContext;

    public ?int $shareId = null;

    // Einstellungen der offenen Freigabe (sofort gespeichert)
    public string $title = '';
    public string $message = '';
    public string $access = OrganizationEntityShare::ACCESS_LINK;
    public string $depth = 'all';          // all | 0 | 1 | 2
    public array $types = [];              // gewählte Aliase
    public bool $details = true;
    public bool $work = true;              // gebuchte Zeiten mit Personen und Notizen
    public bool $finance = false;          // Rechnungen, Zahlungen, Belege
    public array $visible = [];            // sichtbare Objekte als "alias:id" (Rest = ausgeblendet)
    public string $expires = '';           // Y-m-d oder leer

    public string $inviteEmails = '';
    public string $notice = '';

    protected function onContextChanged(): void
    {
        $this->shareId = null;
        $this->notice = '';
        unset($this->entity, $this->canWrite, $this->shares);
        $this->loadShare();
    }

    public function mount(): void
    {
        $this->loadShare();
    }

    /** Ist ein Finanz-Modul angebunden (z. B. drip)? Sonst bleibt der Schalter weg. */
    #[Computed]
    public function hasFinance(): bool
    {
        return app(\Platform\Organization\Services\EntityLinkRegistry::class)->shareFinanceProviders() !== [];
    }

    #[Computed]
    public function entity(): ?OrganizationEntity
    {
        return $this->hasContext() && $this->contextType === OrganizationEntity::class
            ? OrganizationEntity::find($this->contextId)
            : null;
    }

    #[Computed]
    public function canWrite(): bool
    {
        $user = auth()->user();

        return $user && $this->entity
            && (new DimensionLinkAuthorizer())->canWriteContext($user, OrganizationEntity::class, $this->entity->id);
    }

    /** Aktive Freigaben dieses Knotens (neueste zuerst). */
    #[Computed]
    public function shares()
    {
        return $this->entity
            ? OrganizationEntityShare::where('entity_id', $this->entity->id)->active()->with('recipients')->latest()->get()
            : collect();
    }

    #[Computed]
    public function share(): ?OrganizationEntityShare
    {
        return $this->shareId ? $this->shares->firstWhere('id', $this->shareId) : null;
    }

    /** Was hängt im gewählten Ausschnitt — für die Typ-Auswahl. */
    #[Computed]
    public function availableTypes(): array
    {
        return $this->entity
            ? app(EntityShareService::class)->availableTypes($this->entity, $this->depthValue())
            : [];
    }

    /**
     * Einzelne Objekte der Typen mit Kundenansicht (z. B. Projekte) — zum Ein-/Ausblenden.
     *
     * @return array<string, array{label: string, items: array<int, string>}>
     */
    #[Computed]
    public function shareableItems(): array
    {
        if (! $this->entity) {
            return [];
        }
        $service = app(EntityShareService::class);
        $out = [];
        foreach ($this->availableTypes as $alias => $t) {
            if (! $t['has_view'] || ! in_array($alias, $this->types, true)) {
                continue;
            }
            $items = $service->scopeItems($this->entity, $this->depthValue(), $alias);
            if ($items) {
                $out[$alias] = ['label' => $t['label'], 'items' => $items];
            }
        }

        return $out;
    }

    protected function loadShare(): void
    {
        $share = $this->shareId ? $this->shares->firstWhere('id', $this->shareId) : $this->shares->first();
        $this->shareId = $share?->id;
        if (! $share) {
            return;
        }

        $this->title = (string) $share->title;
        $this->message = (string) $share->message;
        $this->access = $share->access;
        $this->depth = $share->depth === null ? 'all' : (string) $share->depth;
        $this->types = app(EntityShareService::class)->allowedTypes($share);
        $this->details = (bool) $share->setting('details', true);
        $this->work = (bool) $share->setting('work', true);
        $this->finance = (bool) $share->setting('finance', false);
        $this->expires = $share->expires_at?->format('Y-m-d') ?? '';
        unset($this->shareableItems);
        $this->visible = $this->visibleKeys($share);
    }

    /** Alle Objekte im Ausschnitt außer den ausgeblendeten. */
    protected function visibleKeys(OrganizationEntityShare $share): array
    {
        $hidden = $share->setting('hidden', []);
        $keys = [];
        foreach ($this->shareableItems as $alias => $group) {
            foreach (array_keys($group['items']) as $id) {
                if (! in_array((int) $id, array_map('intval', (array) ($hidden[$alias] ?? [])), true)) {
                    $keys[] = $alias . ':' . $id;
                }
            }
        }

        return $keys;
    }

    /** Ausgeblendet = im Ausschnitt, aber nicht angehakt. Neue Objekte sind automatisch sichtbar. */
    protected function hiddenFromVisible(): array
    {
        $hidden = [];
        foreach ($this->shareableItems as $alias => $group) {
            foreach (array_keys($group['items']) as $id) {
                if (! in_array($alias . ':' . $id, $this->visible, true)) {
                    $hidden[$alias][] = (int) $id;
                }
            }
        }

        return $hidden;
    }

    public function create(): void
    {
        if (! $this->canWrite) {
            return;
        }

        $share = OrganizationEntityShare::create([
            'team_id' => $this->entity->team_id,
            'entity_id' => $this->entity->id,
            'created_by_user_id' => auth()->id(),
            'settings' => ['details' => true],
        ]);

        $this->shareId = $share->id;
        $this->refreshShares();
        $this->loadShare();
        $this->changed('Freigabe erstellt — der Link ist sofort gültig.');
    }

    public function select(int $id): void
    {
        $this->shareId = $id;
        $this->notice = '';
        $this->loadShare();
    }

    /** Einstellungen sofort speichern. */
    public function updated(string $property): void
    {
        if (! in_array($property, ['title', 'message', 'access', 'depth', 'types', 'details', 'work', 'finance', 'expires', 'visible'], true)
            && ! str_starts_with($property, 'types.') && ! str_starts_with($property, 'visible.')) {
            return;
        }
        $share = $this->share;
        if (! $share || ! $this->canWrite) {
            return;
        }

        if ($property === 'depth' || $property === 'types' || str_starts_with($property, 'types.')) {
            // Ausschnitt geändert: bisher Ausgeblendetes bleibt ausgeblendet, Neues wird sichtbar
            unset($this->availableTypes, $this->shareableItems);
            $this->visible = $this->visibleKeys($share);
        }
        $hidden = $property === 'visible' || str_starts_with($property, 'visible.')
            ? $this->hiddenFromVisible()
            : ($share->setting('hidden', []) ?: []);

        $share->update([
            'title' => trim($this->title) !== '' ? mb_substr(trim($this->title), 0, 190) : null,
            'message' => trim($this->message) !== '' ? mb_substr(trim($this->message), 0, 2000) : null,
            'access' => $this->access === OrganizationEntityShare::ACCESS_INVITE ? OrganizationEntityShare::ACCESS_INVITE : OrganizationEntityShare::ACCESS_LINK,
            'depth' => $this->depthValue(),
            'types' => array_values(array_unique(array_filter($this->types, 'is_string'))),
            'settings' => array_merge($share->settings ?? [], ['details' => $this->details, 'work' => $this->work, 'finance' => $this->finance, 'hidden' => $hidden]),
            'expires_at' => $this->expires !== '' ? Carbon::parse($this->expires)->endOfDay() : null,
        ]);

        $this->refreshShares();
        $this->notice = 'Gespeichert';
    }

    public function invite(): void
    {
        $share = $this->share;
        if (! $share || ! $this->canWrite) {
            return;
        }

        $emails = preg_split('/[\s,;]+/', $this->inviteEmails, -1, PREG_SPLIT_NO_EMPTY);
        if (! $emails) {
            return;
        }

        $result = app(EntityShareService::class)->invite($share, array_slice($emails, 0, 25), auth()->user()?->name);
        $this->inviteEmails = '';
        $this->refreshShares();

        $msg = count($result['invited']) ? count($result['invited']) . ' eingeladen — der persönliche Link ist per E-Mail unterwegs.' : '';
        if ($result['failed']) {
            $msg .= ' Nicht verschickt: ' . implode(', ', $result['failed']) . '.';
        }
        $this->notice = trim($msg);
    }

    public function resend(int $recipientId): void
    {
        $share = $this->share;
        $recipient = $share?->recipients->firstWhere('id', $recipientId);
        if (! $recipient || ! $this->canWrite) {
            return;
        }

        $result = app(EntityShareService::class)->invite($share, [$recipient->email], auth()->user()?->name);
        $this->refreshShares();
        $this->notice = $result['invited'] ? 'Link erneut an ' . $recipient->email . ' geschickt.' : 'Versand an ' . $recipient->email . ' fehlgeschlagen.';
    }

    public function removeRecipient(int $recipientId): void
    {
        if (! $this->share || ! $this->canWrite) {
            return;
        }
        $this->share->recipients()->whereKey($recipientId)->delete();
        $this->refreshShares();
        $this->notice = 'Zugang entzogen — der persönliche Link gilt nicht mehr.';
    }

    /** Neuer Link: der bisherige (offene) Link gilt sofort nicht mehr. */
    public function renewToken(): void
    {
        if (! $this->share || ! $this->canWrite) {
            return;
        }
        $this->share->update(['token' => OrganizationEntityShare::newToken()]);
        $this->refreshShares();
        $this->notice = 'Neuer Link erzeugt — der alte gilt nicht mehr.';
    }

    public function revoke(): void
    {
        if (! $this->share || ! $this->canWrite) {
            return;
        }
        $this->share->update(['revoked_at' => now()]);
        $this->shareId = null;
        $this->refreshShares();
        $this->loadShare();
        $this->changed('Freigabe beendet — alle Links sind ab sofort ungültig.');
    }

    protected function depthValue(): ?int
    {
        return $this->depth === 'all' ? null : max(0, min(9, (int) $this->depth));
    }

    protected function refreshShares(): void
    {
        unset($this->shares, $this->share, $this->availableTypes, $this->shareableItems);
    }

    protected function changed(string $notice): void
    {
        $this->notice = $notice;
        $this->dispatch('context-panel-badges');
    }

    public function render()
    {
        return view('organization::livewire.terminal.share');
    }
}
