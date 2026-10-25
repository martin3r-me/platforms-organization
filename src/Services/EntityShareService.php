<?php

namespace Platform\Organization\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Platform\Organization\Mail\ShareInvitationMail;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityShare;
use Platform\Organization\Models\OrganizationEntityShareRecipient;

/**
 * Kunden-Tor: löst eine Freigabe in ihren Ausschnitt des Baums auf und baut daraus
 * die öffentliche Seite. Einziger Prüfpunkt für geteilte Inhalte — Module prüfen
 * keine Tokens, sie liefern nur ihre Kundenansicht (ProvidesShareView).
 */
class EntityShareService
{
    public function __construct(protected EntityLinkRegistry $registry)
    {
    }

    // ── Zugang ─────────────────────────────────────────────────

    /**
     * Token → [Freigabe, eingeladene Person|null]. Der Freigabe-Token öffnet nur offene
     * Links; bei „nur Eingeladene" öffnet ausschließlich der persönliche Link.
     *
     * @return array{0: ?OrganizationEntityShare, 1: ?OrganizationEntityShareRecipient, 2: string} [share, recipient, status]
     *         status: ok | request (Zugang anfordern) | inactive | missing
     */
    public function resolve(string $token): array
    {
        $recipient = OrganizationEntityShareRecipient::where('token', $token)->first();
        $share = $recipient?->share ?? OrganizationEntityShare::where('token', $token)->first();

        if (! $share || ! $share->entity) {
            return [null, null, 'missing'];
        }
        if (! $share->isActive()) {
            return [$share, $recipient, 'inactive'];
        }
        if ($share->isInviteOnly() && ! $recipient) {
            return [$share, null, 'request'];
        }

        return [$share, $recipient, 'ok'];
    }

    public function recordView(OrganizationEntityShare $share, ?OrganizationEntityShareRecipient $recipient): void
    {
        $share->forceFill(['last_viewed_at' => now(), 'view_count' => $share->view_count + 1])->saveQuietly();
        $recipient?->forceFill(['last_viewed_at' => now(), 'view_count' => $recipient->view_count + 1])->saveQuietly();
    }

    /**
     * Personen einladen — legt sie an (falls neu) und schickt den persönlichen Link.
     *
     * @return array{invited: string[], failed: string[]}
     */
    public function invite(OrganizationEntityShare $share, array $emails, ?string $senderName = null, bool $send = true): array
    {
        $invited = [];
        $failed = [];

        foreach ($emails as $email) {
            $email = mb_strtolower(trim((string) $email));
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $failed[] = $email;
                continue;
            }

            $recipient = $share->recipients()->firstOrCreate(['email' => $email]);
            if ($send && ! $this->sendLink($share, $recipient, $senderName)) {
                $failed[] = $email;
                continue;
            }
            $invited[] = $email;
        }

        return ['invited' => $invited, 'failed' => $failed];
    }

    /**
     * Zugang anfordern (Seite bei „nur Eingeladene"): ist die Adresse eingeladen, geht
     * ihr persönlicher Link raus. Die Antwort verrät nie, ob die Adresse eingeladen ist.
     */
    public function requestAccess(OrganizationEntityShare $share, string $email): void
    {
        $recipient = $share->recipients()->where('email', mb_strtolower(trim($email)))->first();
        if ($recipient && $share->isActive()) {
            $this->sendLink($share, $recipient, $share->createdBy?->name);
        }
    }

    protected function sendLink(OrganizationEntityShare $share, OrganizationEntityShareRecipient $recipient, ?string $senderName): bool
    {
        try {
            Mail::to($recipient->email)->send(new ShareInvitationMail($share, $recipient, $senderName));
            $recipient->forceFill(['invited_at' => now()])->save();

            return true;
        } catch (\Throwable $e) {
            Log::warning('Freigabe-Einladung fehlgeschlagen', ['share' => $share->id, 'email' => $recipient->email, 'error' => $e->getMessage()]);

            return false;
        }
    }

    // ── Ausschnitt ─────────────────────────────────────────────

    /**
     * Knoten + Unterknoten bis zur Tiefe (null = alles darunter), nur aktive im selben Team.
     *
     * @return Collection<int, OrganizationEntity> keyed by id, Wurzel zuerst
     */
    public function scopeEntities(OrganizationEntity $root, ?int $depth): Collection
    {
        $all = collect([$root->id => $root]);
        $frontier = [$root->id];
        $level = 0;

        while ($frontier && ($depth === null || $level < $depth) && $level < 12) {
            $children = OrganizationEntity::whereIn('parent_entity_id', $frontier)
                ->where('team_id', $root->team_id)
                ->where('is_active', true)
                ->whereNotIn('id', $all->keys())
                ->with('type')
                ->orderBy('name')
                ->get();
            foreach ($children as $child) {
                $all->put($child->id, $child);
            }
            $frontier = $children->pluck('id')->all();
            $level++;
        }

        return $all;
    }

    /**
     * Was hängt im Ausschnitt? Für die Einstellungen der Freigabe.
     *
     * @return array<string, array{label: string, icon: string, count: int, has_view: bool}>
     */
    public function availableTypes(OrganizationEntity $root, ?int $depth): array
    {
        $config = $this->registry->allLinkTypeConfig();
        $shareTypes = $this->registry->allShareTypes();
        $types = [];

        foreach ($this->linkedIds($this->scopeEntities($root, $depth)->keys()->all()) as $alias => $ids) {
            $types[$alias] = [
                'label' => $shareTypes[$alias]['label'] ?? $config[$alias]['label'] ?? $alias,
                'icon' => $this->icon($shareTypes[$alias]['icon'] ?? $config[$alias]['icon'] ?? null),
                'count' => count($ids),
                'has_view' => isset($shareTypes[$alias]),
            ];
        }

        uasort($types, fn ($a, $b) => [$b['has_view'], $b['count']] <=> [$a['has_view'], $a['count']]);

        return $types;
    }

    /** Erlaubte Typen einer Freigabe: ausdrücklich gewählte, sonst alle mit Kundenansicht. */
    public function allowedTypes(OrganizationEntityShare $share): array
    {
        return $share->types !== null
            ? array_values($share->types)
            : array_keys($this->registry->allShareTypes());
    }

    // ── Seite ──────────────────────────────────────────────────

    /**
     * Alles für die Übersicht der öffentlichen Seite.
     *
     * @return array{featured: array, sections: array, nav: array, recent: array, upcoming: array, kpis: array}
     */
    public function pageData(OrganizationEntityShare $share): array
    {
        $root = $share->entity;
        $entities = $this->scopeEntities($root, $share->depth);
        $allowed = array_flip($this->allowedTypes($share));
        $shareTypes = $this->registry->allShareTypes();
        $config = $this->registry->allLinkTypeConfig();
        $options = ['details' => (bool) $share->setting('details', true)];

        $featured = [];
        $sections = [];

        foreach ($this->linkedIds($entities->keys()->all(), true) as $alias => $idToEntity) {
            if (! isset($allowed[$alias])) {
                continue;
            }
            $idToEntity = array_diff_key($idToEntity, array_flip($this->hiddenIds($share, $alias)));
            $fqcn = $this->modelClass($alias);
            if (! $idToEntity || ! $fqcn) {
                continue;
            }

            try {
                $models = $fqcn::whereIn('id', array_keys($idToEntity))->get();
                $view = $this->registry->shareView($alias);
                $items = $view
                    ? $view->shareItems($alias, $models, $options)
                    : $this->fallbackItems($alias, $models);
            } catch (\Throwable $e) {
                Log::warning('Freigabe: Typ nicht darstellbar', ['alias' => $alias, 'error' => $e->getMessage()]);
                continue;
            }
            if (! $items) {
                continue;
            }

            $hasDetail = $view && method_exists($view, 'shareDetail');
            foreach ($items as &$item) {
                // Herkunft im Baum, wenn nicht direkt am geteilten Knoten
                $entityId = $idToEntity[$item['id']] ?? null;
                $item['area'] = ($entityId && $entityId !== $root->id) ? ($entities[$entityId]->name ?? null) : null;
                $item['has_detail'] = $hasDetail;
            }
            unset($item);

            $group = [
                'alias' => $alias,
                'slug' => $this->slugFor($alias),
                'label' => $shareTypes[$alias]['label'] ?? $config[$alias]['label'] ?? $alias,
                'icon' => $this->icon($shareTypes[$alias]['icon'] ?? $config[$alias]['icon'] ?? null),
                'has_detail' => $hasDetail,
                'items' => $this->sortItems($items),
            ];

            if ($view && ($shareTypes[$alias]['featured'] ?? false)) {
                $featured[] = $group;
            } else {
                $sections[] = $group;
            }
        }

        // Quer über alle Projekte: was zuletzt erledigt wurde und was als Nächstes ansteht
        $steps = collect($featured)->flatMap(fn ($g) => collect($g['items'])->map(fn ($i) => $i + ['slug' => $g['slug']]));
        $recent = $steps->flatMap(fn ($i) => collect($i['recent'] ?? [])->map(fn ($s) => $s + ['parent' => $i['title'], 'slug' => $i['slug'], 'parent_id' => $i['id'], 'linkable' => $i['has_detail']]))
            ->sortByDesc(fn ($s) => optional($s['at'] ?? null)->timestamp ?? 0)->take(8)->values()->all();
        $upcoming = $steps->flatMap(fn ($i) => collect($i['next'] ?? [])->map(fn ($s) => $s + ['parent' => $i['title'], 'slug' => $i['slug'], 'parent_id' => $i['id'], 'linkable' => $i['has_detail']]))
            ->sortBy(fn ($s) => [empty($s['due']) ? 1 : 0, optional($s['due'] ?? null)->timestamp ?? 0])->take(8)->values()->all();

        return [
            'featured' => $featured,
            'sections' => $sections,
            'nav' => array_values(array_filter(array_merge($featured, $sections), fn ($g) => $g['has_detail'])),
            'recent' => $recent,
            'upcoming' => $upcoming,
            'kpis' => $this->kpis($featured),
        ];
    }

    /**
     * Ein Objekt der Freigabe über seinen URL-Teil — nur, wenn es im Ausschnitt liegt,
     * sein Typ freigegeben und es nicht ausgeblendet ist und das Modul eine Detailseite hat.
     *
     * @return array{alias: string, model: Model, view: object}|null
     */
    public function findItem(OrganizationEntityShare $share, string $slug, int $id): ?array
    {
        $alias = collect($this->allowedTypes($share))->first(fn ($a) => $this->slugFor($a) === $slug);
        $view = $alias ? $this->registry->shareView($alias) : null;
        $fqcn = $alias ? $this->modelClass($alias) : null;
        if (! $view || ! $fqcn || ! method_exists($view, 'shareDetail') || in_array($id, $this->hiddenIds($share, $alias), true)) {
            return null;
        }

        $ids = $this->linkedIds($this->scopeEntities($share->entity, $share->depth)->keys()->all())[$alias] ?? [];
        if (! in_array($id, array_map('intval', $ids), true)) {
            return null;
        }

        $model = $fqcn::find($id);

        return $model ? ['alias' => $alias, 'model' => $model, 'view' => $view] : null;
    }

    /** Detailseite eines gefundenen Objekts (siehe ProvidesShareView). */
    public function detailData(OrganizationEntityShare $share, array $found, ?string $sub, callable $link): ?array
    {
        try {
            return $found['view']->shareDetail($found['alias'], $found['model'], [
                'details' => (bool) $share->setting('details', true),
                'sub' => $sub,
                'link' => $link,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Freigabe: Detail nicht darstellbar', ['alias' => $found['alias'], 'id' => $found['model']->getKey(), 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Objekte eines Typs mit Kundenansicht im Ausschnitt — für die Auswahl „sichtbar" im Panel.
     *
     * @return array<int, string> id => Titel
     */
    public function scopeItems(OrganizationEntity $root, ?int $depth, string $alias): array
    {
        $ids = $this->linkedIds($this->scopeEntities($root, $depth)->keys()->all())[$alias] ?? [];
        $view = $this->registry->shareView($alias);
        $fqcn = $this->modelClass($alias);
        if (! $ids || ! $view || ! $fqcn) {
            return [];
        }

        try {
            $items = $view->shareItems($alias, $fqcn::whereIn('id', $ids)->get(), ['details' => false]);
        } catch (\Throwable) {
            return [];
        }

        return collect($this->sortItems($items))->mapWithKeys(fn ($i) => [$i['id'] => $i['title']])->all();
    }

    /** Ausgeblendete Objekte eines Typs (Einstellung der Freigabe). */
    public function hiddenIds(OrganizationEntityShare $share, string $alias): array
    {
        return array_map('intval', (array) ($share->setting('hidden', [])[$alias] ?? []));
    }

    public function slugFor(string $alias): string
    {
        return $this->registry->allShareTypes()[$alias]['slug'] ?? $alias;
    }

    protected function modelClass(string $alias): ?string
    {
        $fqcn = Relation::getMorphedModel($alias) ?? (class_exists($alias) ? $alias : null);

        return $fqcn && is_subclass_of($fqcn, Model::class) ? $fqcn : null;
    }

    /**
     * Verknüpfte Objekte im Ausschnitt: alias => [id, …] bzw. mit $withEntity alias => [id => entity_id].
     */
    protected function linkedIds(array $entityIds, bool $withEntity = false): array
    {
        $reverse = array_flip(Relation::morphMap());
        $out = [];

        foreach (EntityDimensionBridge::linksForEntities($entityIds) as $link) {
            $alias = $reverse[$link->linkable_type] ?? $link->linkable_type;
            // Erster Treffer gewinnt (nächster Knoten zur Wurzel, da Wurzel zuerst geladen)
            $out[$alias][$link->linkable_id] ??= $link->entity_id;
        }

        return $withEntity ? $out : array_map('array_keys', $out);
    }

    /** Typen ohne Kundenansicht: nur Titel + erledigt. */
    protected function fallbackItems(string $alias, Collection $models): array
    {
        return $models->map(function ($m) use ($alias) {
            $title = $m instanceof \Platform\Core\Contracts\HasDisplayName
                ? ($m->getDisplayName() ?? null)
                : null;
            $done = $this->registry->contextDone($alias, $m);

            return [
                'id' => $m->getKey(),
                'title' => $title ?? $m->name ?? $m->title ?? '—',
                'state' => $done ? 'done' : 'active',
                'state_label' => $done ? 'Erledigt' : 'Offen',
                'updated_at' => $m->updated_at ?? null,
            ];
        })->all();
    }

    /** Laufende zuerst, dann nach letzter Bewegung. */
    protected function sortItems(array $items): array
    {
        $rank = ['active' => 0, 'planned' => 1, 'paused' => 2, 'done' => 3];
        usort($items, fn ($a, $b) => [($rank[$a['state'] ?? 'active'] ?? 0), -(optional($a['updated_at'] ?? null)->timestamp ?? 0)]
            <=> [($rank[$b['state'] ?? 'active'] ?? 0), -(optional($b['updated_at'] ?? null)->timestamp ?? 0)]);

        return $items;
    }

    protected function kpis(array $featured): array
    {
        $items = collect($featured)->flatMap(fn ($g) => $g['items']);
        $withProgress = $items->filter(fn ($i) => ! empty($i['progress']['total']));
        $done = $withProgress->sum(fn ($i) => $i['progress']['done']);
        $total = $withProgress->sum(fn ($i) => $i['progress']['total']);
        $nextDue = $items->flatMap(fn ($i) => $i['next'] ?? [])
            ->pluck('due')->filter()->filter(fn ($d) => $d->isFuture() || $d->isToday())->sort()->first();

        $overdue = $items->flatMap(fn ($i) => $i['next'] ?? [])
            ->pluck('due')->filter()->filter(fn ($d) => $d->isPast() && ! $d->isToday())->count();

        return [
            'overdue' => $overdue,
            'active' => $items->where('state', 'active')->count(),
            'done' => $items->where('state', 'done')->count(),
            'progress' => $total > 0 ? (int) round($done / $total * 100) : null,
            'steps_done' => $done,
            'steps_total' => $total,
            'next_due' => $nextDue,
        ];
    }

    /**
     * Icons kommen aus fremden Link-Konfigurationen und sind nicht immer
     * Heroicons (z. B. „arrow-right-left") — unbekannte fallen auf „cube" zurück.
     */
    protected function icon(?string $name): string
    {
        static $known = [];

        if (! $name) {
            return 'cube';
        }

        return $known[$name] ??= (function () use ($name) {
            try {
                svg('heroicon-o-' . $name);

                return $name;
            } catch (\Throwable) {
                return 'cube';
            }
        })();
    }
}
