<?php

namespace Platform\Organization\Livewire\Share;

use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Platform\Organization\Models\OrganizationEntityShare;
use Platform\Organization\Services\EntityShareService;

/**
 * Kunden-Tor: die geteilte Seite eines Knotens — ohne Login, über den Freigabe-
 * oder den persönlichen Link. Bei „nur Eingeladene" fordert man seinen Link per E-Mail an.
 */
class PublicShare extends Component
{
    public string $token = '';

    public string $email = '';

    public bool $requested = false;

    // Detailseite: /teilen/{token}/{type}/{id}/{sub?}
    public ?string $type = null;

    public ?int $itemId = null;

    public ?string $sub = null;

    public function mount(string $token, ?string $type = null, ?string $id = null, ?string $sub = null): void
    {
        $this->token = $token;
        $this->type = $type;
        $this->itemId = $id !== null ? (int) $id : null;
        $this->sub = $sub;

        [$share, $recipient, $status] = app(EntityShareService::class)->resolve($token);
        if ($status === 'ok') {
            app(EntityShareService::class)->recordView($share, $recipient);
        }
    }

    public function requestAccess(): void
    {
        $this->validate(['email' => 'required|email|max:200'], [], ['email' => 'E-Mail-Adresse']);

        [$share, , $status] = app(EntityShareService::class)->resolve($this->token);
        $key = 'org-share-request:' . sha1($this->token . '|' . request()->ip());

        if ($share && $status === 'request' && ! RateLimiter::tooManyAttempts($key, 5)) {
            RateLimiter::hit($key, 600);
            app(EntityShareService::class)->requestAccess($share, $this->email);
        }

        // Immer dieselbe Antwort — verrät nicht, ob die Adresse eingeladen ist
        $this->requested = true;
    }

    public function render()
    {
        $service = app(EntityShareService::class);
        [$share, $recipient, $status] = $service->resolve($this->token);

        $data = $status === 'ok' ? $service->pageData($share) : null;
        $title = $share?->displayTitle() ?? 'Freigabe';

        // Detailseite eines Objekts — nur, was im Ausschnitt der Freigabe liegt
        $detail = null;
        $found = null;
        if ($data && $this->type && $this->itemId) {
            $found = $service->findItem($share, $this->type, $this->itemId);
            $detail = $found ? $service->detailData($share, $found, $this->sub, fn (?string $sub = null) => $this->link($this->type, $this->itemId, $sub)) : null;
        }

        return view('organization::livewire.share.public-share', [
            'share' => $share,
            'recipient' => $recipient,
            'status' => $status,
            'entity' => $share?->entity,
            'teamName' => $share?->team?->name ?? config('app.name'),
            'senderName' => $share?->createdBy?->name,
            'title' => $title,
            'data' => $data,
            'detail' => $detail,
            'notFound' => $data && $this->type && ! $detail,
            'activeSlug' => $found ? $this->type : null,
            'activeId' => $found ? $this->itemId : null,
            'link' => fn (?string $type = null, ?int $id = null, ?string $sub = null) => $this->link($type, $id, $sub),
        ])->layout('platform::layouts.guest', ['title' => $detail ? ($detail['page']['title'] ?? $detail['title']) . ' · ' . $title : $title]);
    }

    /** URL innerhalb der Freigabe — behält den Token, über den man gekommen ist. */
    public function link(?string $type = null, ?int $id = null, ?string $sub = null): string
    {
        return $type && $id
            ? route('organization.share.item', array_filter(['token' => $this->token, 'type' => $type, 'id' => $id, 'sub' => $sub]))
            : route('organization.share.public', ['token' => $this->token]);
    }
}
