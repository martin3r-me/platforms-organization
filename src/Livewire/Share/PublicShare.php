<?php

namespace Platform\Organization\Livewire\Share;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
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

    // Bereichsseite: /teilen/{token}/{section} — leistung | rechnungen | aktivitaet
    public ?string $section = null;

    /** Letzter Besuch vor diesem (persönlicher Link, sonst Cookie dieses Browsers) — ISO-Zeit. */
    public ?string $since = null;

    /** Bereichsfilter (Teilbaum) — gemerkt je Freigabe in der Sitzung. */
    public ?int $area = null;

    public function mount(string $token, ?string $type = null, ?string $id = null, ?string $sub = null, ?string $section = null): void
    {
        $this->token = $token;
        $this->section = $section;
        $this->type = $type;
        $this->itemId = $id !== null ? (int) $id : null;
        $this->sub = $sub;

        [$share, $recipient, $status] = app(EntityShareService::class)->resolve($token);
        if ($status === 'ok') {
            // Ein Besuch = eine Sitzung: Seitenwechsel innerhalb der Freigabe behalten den Stand
            $key = 'gp_since_' . $share->id;
            if (session()->has($key)) {
                $this->since = session($key);
            } else {
                $cookie = request()->cookie('gp_seen_' . $share->id);
                $last = $recipient?->last_viewed_at ?? (is_numeric($cookie) ? Carbon::createFromTimestamp((int) $cookie) : null);
                $this->since = $last?->toIso8601String();
                session([$key => $this->since]);
            }
            $this->area = session('gp_area_' . $share->id);
            Cookie::queue('gp_seen_' . $share->id, (string) now()->timestamp, 60 * 24 * 365);
            app(EntityShareService::class)->recordView($share, $recipient);
        }
    }

    public function setArea(?int $id = null): void
    {
        [$share, , $status] = app(EntityShareService::class)->resolve($this->token);
        if ($status !== 'ok') {
            return;
        }
        $this->area = $id ?: null;
        session(['gp_area_' . $share->id => $this->area]);
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

        $since = $this->since ? Carbon::parse($this->since) : null;
        $data = $status === 'ok' ? $service->pageData($share, $since, $this->area) : null;

        // Modul-Bereich (nav=section), z. B. /teilen/{token}/sichtbarkeit/{sub?}
        $sectionPage = null;
        if ($data && $this->section && ! isset(self::SECTIONS[$this->section])) {
            $sectionPage = $service->sectionData($share, $this->section, $this->sub, fn (?string $sub = null) => $this->link($this->section, null, $sub), $since);
        }
        $title = $share?->displayTitle() ?? 'Freigabe';

        // Detailseite eines Objekts — nur, was im Ausschnitt der Freigabe liegt
        $detail = null;
        $found = null;
        if ($data && $this->type && $this->itemId) {
            $found = $service->findItem($share, $this->type, $this->itemId);
            $detail = $found ? $service->detailData($share, $found, $this->sub, fn (?string $sub = null) => $this->link($this->type, $this->itemId, $sub), $since) : null;
        }

        // Profilbilder: nur Personen, die diese Seite zeigt (siehe ShareFileController)
        $people = collect($data['work']['people'] ?? [])->merge($detail['work']['people'] ?? []);
        if ($share && $people->where('has_avatar', true)->isNotEmpty()) {
            $known = (array) Cache::get('gp_people_' . $share->id, []);
            Cache::put('gp_people_' . $share->id, array_values(array_unique(array_merge($known, $people->where('has_avatar', true)->pluck('id')->all()))), now()->addDay());
        }

        return view('organization::livewire.share.public-share', [
            'share' => $share,
            'recipient' => $recipient,
            'status' => $status,
            'entity' => $share?->entity,
            'teamName' => $share?->team?->name ?? config('app.name'),
            'senderName' => $share?->createdBy ? (trim((string) $share->createdBy->fullname) ?: $share->createdBy->name) : null,
            'title' => $title,
            'data' => $data,
            'detail' => $detail,
            'notFound' => ($data && $this->type && ! $detail) || ($data && $this->section && ! $sectionPage && ! $this->sectionAvailable($data)),
            'sectionPage' => $sectionPage,
            'section' => $this->type ? null : $this->section,
            'activeSlug' => $found ? $this->type : null,
            'activeId' => $found ? $this->itemId : null,
            'link' => fn (?string $type = null, ?int $id = null, ?string $sub = null) => $this->link($type, $id, $sub),
            'avatar' => fn (array $person) => ! empty($person['has_avatar']) ? route('organization.share.avatar', ['token' => $this->token, 'user' => $person['id']]) : null,
            'document' => fn (string $ref) => route('organization.share.document', ['token' => $this->token, 'ref' => $ref]),
        ])->layout('platform::layouts.guest', ['title' => match (true) {
            (bool) $detail => ($detail['page']['title'] ?? $detail['title']) . ' · ' . $title,
            (bool) $this->section => (self::SECTIONS[$this->section] ?? ($sectionPage['page']['title'] ?? $sectionPage['title'] ?? '')) . ' · ' . $title,
            default => $title,
        }]);
    }

    public const SECTIONS = ['leistung' => 'Leistung', 'rechnungen' => 'Rechnungen', 'aktivitaet' => 'Aktivität'];

    protected function sectionAvailable(array $data): bool
    {
        return match ($this->section) {
            'leistung' => ! empty($data['work']['entry_count']) && ! $this->sub,
            'rechnungen' => ! empty($data['finance']) && ! $this->sub,
            'aktivitaet' => ! $this->sub,
            default => false,
        };
    }

    /** URL innerhalb der Freigabe — behält den Token, über den man gekommen ist. */
    public function link(?string $type = null, ?int $id = null, ?string $sub = null): string
    {
        return match (true) {
            $type && $id => route('organization.share.item', array_filter(['token' => $this->token, 'type' => $type, 'id' => $id, 'sub' => $sub])),
            (bool) $type => route('organization.share.section', array_filter(['token' => $this->token, 'section' => $type, 'sub' => $sub])),
            default => route('organization.share.public', ['token' => $this->token]),
        };
    }
}
