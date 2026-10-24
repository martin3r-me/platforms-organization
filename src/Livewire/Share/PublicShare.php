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

    public function mount(string $token): void
    {
        $this->token = $token;

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

        return view('organization::livewire.share.public-share', [
            'share' => $share,
            'recipient' => $recipient,
            'status' => $status,
            'entity' => $share?->entity,
            'teamName' => $share?->team?->name ?? config('app.name'),
            'senderName' => $share?->createdBy?->name,
            'title' => $title,
            'data' => $data,
        ])->layout('platform::layouts.guest', ['title' => $title]);
    }
}
