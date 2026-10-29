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
use Platform\Organization\Models\OrganizationTimeEntry;
use Platform\Organization\Models\OrganizationTimePlanned;
use Platform\Core\Models\User;

/**
 * Kunden-Tor: löst eine Freigabe in ihren Ausschnitt des Baums auf und baut daraus
 * die öffentliche Seite. Einziger Prüfpunkt für geteilte Inhalte — Module prüfen
 * keine Tokens, sie liefern nur ihre Kundenansicht (ProvidesShareView).
 */
class EntityShareService
{
    /** Share-Typen mit 'opt_in' werden respektiert (Module prüfen das vor dem Registrieren). */
    public const SUPPORTS_OPT_IN = true;

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

    /**
     * Erlaubte Typen einer Freigabe: ausdrücklich gewählte, sonst alle mit Kundenansicht —
     * außer opt_in-Typen (z. B. Meetings), die nur erscheinen, wenn man sie anhakt.
     */
    public function allowedTypes(OrganizationEntityShare $share): array
    {
        return $share->types !== null
            ? array_values($share->types)
            : array_keys(array_filter($this->registry->allShareTypes(), fn ($t) => empty($t['opt_in'])));
    }

    // ── Seite ──────────────────────────────────────────────────

    /**
     * Alles für die Übersicht der öffentlichen Seite.
     *
     * @param \Carbon\CarbonInterface|null $since letzter Besuch (für „Seit Ihrem letzten Besuch")
     * @return array{featured: array, sections: array, nav: array, recent: array, upcoming: array, kpis: array, work: ?array, finance: ?array, activity: array, since: array}
     */
    public function pageData(OrganizationEntityShare $share, ?\Carbon\CarbonInterface $since = null, ?int $areaId = null): array
    {
        $root = $share->entity;
        $allEntities = $this->scopeEntities($root, $share->depth);
        $areas = $this->areaOptions($root, $allEntities);
        // Bereichsfilter: nur der Teilbaum eines Knotens (Pfade bleiben relativ zur Freigabe)
        $entities = $areaId && $areaId !== $root->id && isset($areas[$areaId])
            ? $allEntities->only($this->subtreeIds($areaId, $allEntities))
            : $allEntities;
        $areaId = $entities === $allEntities ? null : $areaId;
        $shareTypes = $this->registry->allShareTypes();
        $config = $this->registry->allLinkTypeConfig();
        $options = ['details' => (bool) $share->setting('details', true), 'since' => $since, 'hidden' => (array) $share->setting('hidden', [])];
        $visible = $this->visibleObjects($share, $entities);
        $tree = $this->treeOrder($root, $allEntities);
        $work = $share->setting('work', true) ? $this->workData($visible, $entities, $since) : null;

        $featured = [];
        $sections = [];
        $events = [];

        foreach ($visible as $alias => $idToEntity) {
            $fqcn = $this->modelClass($alias);
            if (! $fqcn) {
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

            $meta = $shareTypes[$alias] ?? [];
            $navMode = $meta['nav'] ?? 'items';
            $hasDetail = $view && $navMode === 'items' && ($meta['detail'] ?? true) && method_exists($view, 'shareDetail');
            $hasSection = $view && $navMode === 'section' && method_exists($view, 'shareSection');
            $slug = $this->slugFor($alias);
            foreach ($items as &$item) {
                // Herkunft im Baum, wenn nicht direkt am geteilten Knoten
                $entityId = $idToEntity[$item['id']] ?? null;
                $place = $tree[$entityId] ?? $tree[$root->id];
                $item['area'] = $place['path'] ? implode(' › ', $place['path']) : null;
                $item['area_id'] = $place['id'];
                $item['area_path'] = $place['path'];
                $item['area_pos'] = $place['pos'];
                $item['area_type'] = $place['type'];
                $item['has_detail'] = $hasDetail;
                if ($work) {
                    $item['minutes'] = $work['by_item'][$alias][$item['id']] ?? 0;
                    $item['planned_minutes'] = $work['planned_by_item'][$alias][$item['id']] ?? 0;
                }
            }
            unset($item);

            // Ereignisse fürs Protokoll (nur mit „Einzelne Schritte zeigen")
            if ($view && $options['details'] && method_exists($view, 'shareActivity')) {
                try {
                    $titles = collect($items)->pluck('title', 'id');
                    foreach ($view->shareActivity($alias, $models, $options) as $event) {
                        $pid = $event['parent_id'] ?? null;
                        $events[] = $event + [
                            'parent' => $pid ? ($titles[$pid] ?? null) : null,
                            'href' => $pid && $hasDetail && isset($titles[$pid]) ? [$slug, $pid, $event['sub'] ?? null] : null,
                        ];
                    }
                } catch (\Throwable $e) {
                    Log::warning('Freigabe: Aktivität nicht darstellbar', ['alias' => $alias, 'error' => $e->getMessage()]);
                }
            }

            $summary = null;
            if ($view && method_exists($view, 'shareSummary')) {
                try {
                    $summary = $view->shareSummary($alias, $models, $options);
                } catch (\Throwable $e) {
                    Log::warning('Freigabe: Kurzstatus nicht darstellbar', ['alias' => $alias, 'error' => $e->getMessage()]);
                }
            }

            $group = [
                'alias' => $alias,
                'slug' => $slug,
                'label' => $meta['label'] ?? $config[$alias]['label'] ?? $alias,
                'icon' => $this->icon($meta['icon'] ?? $config[$alias]['icon'] ?? null),
                'group' => $meta['group'] ?? 'arbeit',
                'order' => $meta['order'] ?? 50,
                'nav_mode' => $navMode,
                'has_detail' => $hasDetail,
                'has_section' => $hasSection,
                'summary' => $summary,
                'items' => $this->sortItems($items),
            ];

            if ($view && ($meta['featured'] ?? false)) {
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

        $finance = $share->setting('finance', false) ? $this->financeData($share, $entities) : null;

        // Zeiten und Zahlungen gehören mit ins Protokoll
        $itemLinks = collect($featured)->merge($sections)
            ->flatMap(fn ($g) => collect($g['items'])->mapWithKeys(fn ($i) => [$g['alias'] . ':' . $i['id'] => $g['has_detail'] ? [$g['slug'], $i['id']] : null]));
        foreach ($work['entries'] ?? [] as $entry) {
            if ($entry['at']->lt(now()->subDays(60))) {
                break;
            }
            $events[] = [
                'at' => $entry['at'], 'kind' => 'work', 'title' => $entry['context'] ?? $entry['item'] ?? 'Arbeit',
                'text' => $entry['note'], 'person' => $entry['person'], 'minutes' => $entry['minutes'],
                'parent' => $entry['item'] !== $entry['context'] ? $entry['item'] : null,
                'href' => $entry['item_key'] ? ($itemLinks[$entry['item_key']] ?? null) : null,
            ];
        }
        foreach ($finance['payments'] ?? [] as $payment) {
            if ($payment['at'] && $payment['at']->gte(now()->subDays(60))) {
                $events[] = ['at' => $payment['at'], 'kind' => 'payment', 'title' => 'Zahlung eingegangen', 'text' => $payment['label'], 'amount' => $payment['amount'], 'currency' => $finance['currency'], 'parent' => null, 'href' => null];
            }
        }
        $events = collect($events)->filter(fn ($e) => ! empty($e['at']))
            ->sortByDesc(fn ($e) => $e['at']->timestamp)->take(80)->values();

        $sinceAt = $since ?? now()->subDays(7);
        $fresh = $events->filter(fn ($e) => $e['at']->gt($sinceAt));

        return [
            'featured' => $featured,
            'sections' => $sections,
            'nav' => collect(array_merge($featured, $sections))
                ->filter(fn ($g) => $g['has_detail'] || $g['has_section'])
                ->sortBy(fn ($g) => [array_search($g['group'], self::NAV_GROUPS, true), $g['order']])
                ->groupBy('group')->all(),
            'impact' => collect(array_merge($featured, $sections))->filter(fn ($g) => ! empty($g['summary']['kpis']))
                ->sortBy(fn ($g) => [array_search($g['group'], self::NAV_GROUPS, true), $g['order']])->values()->all(),
            'areas' => $areas,
            'area' => $areaId,
            'recent' => $recent,
            'upcoming' => $upcoming,
            'kpis' => $this->kpis($featured),
            'work' => $work,
            'finance' => $finance,
            'activity' => $events->all(),
            'since' => [
                'at' => $sinceAt,
                'is_visit' => $since !== null,
                'minutes' => $work['since_minutes'] ?? 0,
                'done' => $fresh->where('kind', 'done')->count(),
                'canvas' => $fresh->where('kind', 'canvas')->count(),
                'payments' => $fresh->where('kind', 'payment')->count(),
                'people' => $fresh->where('kind', 'work')->pluck('person')->unique()->values()->all(),
            ],
        ];
    }

    /**
     * Verortung im Baum: Knoten in Baum-Reihenfolge (Tiefensuche, Wurzel zuerst) mit
     * Pfad unterhalb der Wurzel — damit Projekte dort erscheinen, wo sie herkommen.
     *
     * @return array<int, array{id: int, pos: int, depth: int, path: string[], type: ?string}>
     */
    protected function treeOrder(OrganizationEntity $root, Collection $entities): array
    {
        $children = $entities->groupBy('parent_entity_id');
        $out = [];
        $walk = function (OrganizationEntity $node, array $path, int $depth) use (&$walk, &$out, $children) {
            $out[$node->id] = ['id' => $node->id, 'pos' => count($out), 'depth' => $depth, 'path' => $path, 'type' => $node->type?->name];
            foreach ($children->get($node->id, collect())->sortBy('name') as $child) {
                if (! isset($out[$child->id])) {
                    $walk($child, array_merge($path, [$child->name]), $depth + 1);
                }
            }
        };
        $walk($root, [], 0);

        return $out;
    }

    // ── Leistung (gebuchte Zeiten) ─────────────────────────────

    /**
     * Gebuchte Zeiten im Ausschnitt: an den Knoten selbst und an allem Sichtbaren samt
     * Kindern (Projekt → Aufgaben, über die timeTrackableCascades der Module).
     * Mit Personen und Notizen, ohne Beträge.
     *
     * @param array<string, array<int, int>> $visible alias => [id => entity_id]
     */
    public function workData(array $visible, Collection $entities, ?\Carbon\CarbonInterface $since = null): ?array
    {
        [$pairs, $owners] = $this->workPairs($visible, $entities);
        if (! $pairs) {
            return null;
        }

        try {
            $rows = $this->pairQuery(OrganizationTimeEntry::query(), $pairs)
                ->orderByDesc('work_date')->orderByDesc('id')
                ->get(['id', 'user_id', 'context_type', 'context_id', 'work_date', 'minutes', 'note', 'created_at']);
            $planned = $this->pairQuery(OrganizationTimePlanned::query(), $pairs)
                ->where('is_active', true)
                ->get(['context_type', 'context_id', 'planned_minutes']);
        } catch (\Throwable $e) {
            Log::warning('Freigabe: Zeiten nicht lesbar', ['error' => $e->getMessage()]);

            return null;
        }

        $morph = Relation::morphMap();
        $key = fn ($type, $id) => ($morph[$type] ?? $type) . '|' . $id;
        $users = User::whereIn('id', $rows->pluck('user_id')->filter()->unique())->get()->keyBy('id');
        $name = fn ($uid) => ($u = $users->get($uid)) ? (trim((string) $u->fullname) ?: $u->name) : 'Ehemaliges Teammitglied';

        $byItem = [];
        $plannedByItem = [];
        foreach ($rows as $row) {
            if ($owner = $owners[$key($row->context_type, $row->context_id)] ?? null) {
                $byItem[$owner[0]][$owner[1]] = ($byItem[$owner[0]][$owner[1]] ?? 0) + (int) $row->minutes;
            }
        }
        foreach ($planned as $row) {
            if ($owner = $owners[$key($row->context_type, $row->context_id)] ?? null) {
                $plannedByItem[$owner[0]][$owner[1]] = ($plannedByItem[$owner[0]][$owner[1]] ?? 0) + (int) $row->planned_minutes;
            }
        }

        // Monate: die letzten 12, auch leere
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = now()->startOfMonth()->subMonthsNoOverflow($i);
            $months[$m->format('Y-m')] = ['key' => $m->format('Y-m'), 'label' => $m->locale('de')->isoFormat('MMM'), 'long' => $m->locale('de')->isoFormat('MMMM YYYY'), 'minutes' => 0];
        }
        foreach ($rows as $row) {
            $k = $row->work_date?->format('Y-m');
            if ($k && isset($months[$k])) {
                $months[$k]['minutes'] += (int) $row->minutes;
            }
        }

        $thisMonth = now()->format('Y-m');
        // Zeiträume: der Moment zählt — Gesamt nur als Rückblick
        $cut = ['30' => now()->subDays(30)->startOfDay(), '90' => now()->subDays(90)->startOfDay(), '365' => now()->subDays(365)->startOfDay()];
        $inPeriod = fn ($r, $key) => (int) $r->filter(fn ($x) => $x->work_date && $x->work_date->gte($cut[$key]))->sum('minutes');
        $people = $rows->groupBy('user_id')->map(fn ($r, $uid) => [
            'id' => (int) $uid,
            'name' => $name($uid),
            'has_avatar' => (bool) $users->get($uid)?->avatar,
            'minutes' => (int) $r->sum('minutes'),
            'periods' => ['30' => $inPeriod($r, '30'), '90' => $inPeriod($r, '90'), '365' => $inPeriod($r, '365'), 'all' => (int) $r->sum('minutes')],
            'month_minutes' => (int) $r->filter(fn ($x) => $x->work_date?->format('Y-m') === $thisMonth)->sum('minutes'),
            'last_at' => $r->max('work_date'),
            'first_at' => $r->min('work_date'),
            'entries' => $r->count(),
            'days' => $r->pluck('work_date')->filter()->map(fn ($d) => $d->format('Y-m-d'))->unique()->count(),
        ])->sortByDesc(fn ($p) => [$p['periods']['90'], $p['minutes']])->values()->all();

        // Neueste Buchungen mit Kontext (Aufgabe/Projekt) für Protokoll und Liste
        $recentRows = $rows->take(250);
        $titles = $this->contextTitles($recentRows, collect($owners)->only($recentRows->map(fn ($r) => $key($r->context_type, $r->context_id))->all())->filter(fn ($o) => $o[0])->values());
        $entries = $recentRows->map(function ($row) use ($owners, $key, $titles, $name) {
            $owner = $owners[$key($row->context_type, $row->context_id)] ?? null;
            $sameDay = $row->created_at && $row->work_date && $row->created_at->isSameDay($row->work_date);

            return [
                'at' => $sameDay ? $row->created_at : $row->work_date->copy()->setTime(12, 0),
                'date' => $row->work_date,
                'person' => $name($row->user_id),
                'user_id' => (int) $row->user_id,
                'minutes' => (int) $row->minutes,
                'note' => trim((string) $row->note) ?: null,
                'context' => $titles[$key($row->context_type, $row->context_id)] ?? null,
                'item' => $owner ? ($titles[$this->ownerTitleKey($owner)] ?? null) : null,
                'item_key' => $owner ? $owner[0] . ':' . $owner[1] : null,
                'item_alias' => $owner[0] ?? null,
                'item_id' => $owner[1] ?? null,
            ];
        })->values()->all();

        $total = (int) $rows->sum('minutes');

        return [
            'total_minutes' => $total,
            'period_minutes' => ['30' => $inPeriod($rows, '30'), '90' => $inPeriod($rows, '90'), '365' => $inPeriod($rows, '365'), 'all' => $total],
            'planned_minutes' => (int) $planned->sum('planned_minutes'),
            'month_minutes' => $months[$thisMonth]['minutes'] ?? 0,
            'prev_month_minutes' => $months[now()->startOfMonth()->subMonthNoOverflow()->format('Y-m')]['minutes'] ?? 0,
            'since_minutes' => $since ? (int) $rows->filter(fn ($r) => ($r->created_at && $r->created_at->gt($since)))->sum('minutes') : (int) $rows->filter(fn ($r) => $r->work_date && $r->work_date->gte(now()->subDays(7)->startOfDay()))->sum('minutes'),
            'first_date' => $rows->min('work_date'),
            'last_date' => $rows->max('work_date'),
            'entry_count' => $rows->count(),
            'months' => array_values($months),
            'people' => $people,
            'by_item' => $byItem,
            'planned_by_item' => $plannedByItem,
            'entries' => $entries,
        ];
    }

    /** Arbeiten an einem einzelnen Objekt (Tab „Arbeiten" der Detailseite). */
    public function itemWork(OrganizationEntityShare $share, array $found): ?array
    {
        if (! $share->setting('work', true)) {
            return null;
        }
        $visible = [$found['alias'] => [$found['model']->getKey() => $share->entity_id]];

        return $this->workData($visible, collect());
    }

    /**
     * Kontext-Paare der Zeitbuchungen: [fqcn => ids] und „Typ|id" → [alias, Objekt-ID].
     *
     * @return array{0: array<string, int[]>, 1: array<string, array{0: ?string, 1: ?int}>}
     */
    protected function workPairs(array $visible, Collection $entities): array
    {
        $cascades = EntityTimeResolver::getTimeTrackableCascades();
        $pairs = [];
        $owners = [];
        $add = function (string $fqcn, int $id, ?string $alias, ?int $ownerId) use (&$pairs, &$owners) {
            $pairs[$fqcn][] = $id;
            $owners[$fqcn . '|' . $id] ??= [$alias, $ownerId];
        };

        foreach ($entities->keys() as $entityId) {
            $add(OrganizationEntity::class, (int) $entityId, null, null);
        }

        foreach ($visible as $alias => $idToEntity) {
            $fqcn = $this->modelClass($alias);
            if (! $fqcn) {
                continue;
            }
            [$cascadeClass, $relations] = $cascades[$alias] ?? [$fqcn, []];
            try {
                $models = $cascadeClass::with($relations)->whereIn('id', array_keys($idToEntity))->get();
            } catch (\Throwable) {
                $models = $fqcn::whereIn('id', array_keys($idToEntity))->get();
                $relations = [];
            }
            foreach ($models as $model) {
                $add(get_class($model), (int) $model->getKey(), $alias, (int) $model->getKey());
                foreach ($relations as $path) {
                    foreach ($this->walkRelation($model, $path) as $leaf) {
                        $add(get_class($leaf), (int) $leaf->getKey(), $alias, (int) $model->getKey());
                    }
                }
            }
        }

        return [array_map(fn ($ids) => array_values(array_unique($ids)), $pairs), $owners];
    }

    /** Folgt einem Relationspfad wie „projectSlots.tasks" zu den Blatt-Models. */
    protected function walkRelation(Model $model, string $path): Collection
    {
        $current = collect([$model]);
        foreach (explode('.', $path) as $segment) {
            $current = $current->flatMap(function ($m) use ($segment) {
                if (! method_exists($m, $segment)) {
                    return [];
                }
                $related = $m->{$segment};

                return $related instanceof Collection ? $related->all() : ($related instanceof Model ? [$related] : []);
            });
        }

        return $current->filter(fn ($m) => $m instanceof Model)->values();
    }

    /** Zeitbuchungen speichern den Kontext als Klasse, ältere evtl. als Morph-Alias — beides abfragen. */
    protected function pairQuery($query, array $pairs)
    {
        $reverse = array_flip(Relation::morphMap());

        return $query->where(function ($q) use ($pairs, $reverse) {
            foreach ($pairs as $fqcn => $ids) {
                $types = array_values(array_filter([$fqcn, $reverse[$fqcn] ?? null]));
                $q->orWhere(fn ($sq) => $sq->whereIn('context_type', $types)->whereIn('context_id', $ids));
            }
        });
    }

    /** Titel der Kontexte (Aufgabe, Projekt, Knoten) und ihrer Besitzer — „Typ|id" => Titel. */
    protected function contextTitles(Collection $rows, Collection $owners): array
    {
        $morph = Relation::morphMap();
        $ids = [];
        foreach ($rows as $row) {
            $ids[$morph[$row->context_type] ?? $row->context_type][] = (int) $row->context_id;
        }
        foreach ($owners as $owner) {
            if ($fqcn = $this->modelClass((string) $owner[0])) {
                $ids[$fqcn][] = (int) $owner[1];
            }
        }

        $titles = [];
        foreach ($ids as $fqcn => $list) {
            if (! class_exists($fqcn) || ! is_subclass_of($fqcn, Model::class)) {
                continue;
            }
            try {
                foreach ($fqcn::withoutGlobalScopes()->whereIn('id', array_unique($list))->get() as $m) {
                    $titles[$fqcn . '|' . $m->getKey()] = $this->modelTitle($m);
                }
            } catch (\Throwable) {
                // unbekannter Kontext — bleibt ohne Titel
            }
        }

        return $titles;
    }

    protected function ownerTitleKey(array $owner): string
    {
        return ($this->modelClass((string) $owner[0]) ?? $owner[0]) . '|' . $owner[1];
    }

    protected function modelTitle(Model $m): string
    {
        // name zuerst: berechnete Titel tragen oft interne Präfixe (z. B. Projektart)
        $title = $m->getAttribute('name') ?: $m->getAttribute('title')
            ?: ($m instanceof \Platform\Core\Contracts\HasDisplayName ? $m->getDisplayName() : null);

        return (string) ($title ?: '—');
    }

    // ── Geld ───────────────────────────────────────────────────

    /** Rechnungen, Zahlungen, Belege aus den registrierten Finanz-Modulen. */
    public function financeData(OrganizationEntityShare $share, ?Collection $entities = null): ?array
    {
        $entities ??= $this->scopeEntities($share->entity, $share->depth);
        $linked = $this->linkedIds($entities->keys()->all());
        $result = null;

        foreach ($this->registry->shareFinanceProviders() as $provider) {
            try {
                $part = $provider->shareFinance((int) $share->team_id, $entities->keys()->all(), $linked);
            } catch (\Throwable $e) {
                Log::warning('Freigabe: Finanzen nicht darstellbar', ['provider' => get_class($provider), 'error' => $e->getMessage()]);
                continue;
            }
            if (! $part) {
                continue;
            }
            if (! $result) {
                $result = $part;
                continue;
            }
            foreach (['invoiced', 'paid', 'open', 'overdue'] as $k) {
                $result['summary'][$k] += $part['summary'][$k] ?? 0;
            }
            $result['invoices'] = array_merge($result['invoices'], $part['invoices']);
            $result['payments'] = array_merge($result['payments'], $part['payments']);
        }

        return $result;
    }

    /** Beleg-Datei — nur, was zum Ausschnitt gehört und wenn Geld freigegeben ist. */
    public function document(OrganizationEntityShare $share, string $ref): ?\Symfony\Component\HttpFoundation\Response
    {
        if (! $share->setting('finance', false)) {
            return null;
        }
        $entities = $this->scopeEntities($share->entity, $share->depth);
        $linked = $this->linkedIds($entities->keys()->all());

        foreach ($this->registry->shareFinanceProviders() as $provider) {
            try {
                if ($response = $provider->shareDocument((int) $share->team_id, $entities->keys()->all(), $linked, $ref)) {
                    return $response;
                }
            } catch (\Throwable $e) {
                Log::warning('Freigabe: Beleg nicht abrufbar', ['provider' => get_class($provider), 'ref' => $ref, 'error' => $e->getMessage()]);
            }
        }

        return null;
    }

    /**
     * Sichtbare Objekte: erlaubte Typen im Ausschnitt ohne Ausgeblendetes.
     *
     * @return array<string, array<int, int>> alias => [id => entity_id]
     */
    protected function visibleObjects(OrganizationEntityShare $share, Collection $entities): array
    {
        $allowed = array_flip($this->allowedTypes($share));
        $out = [];
        foreach ($this->linkedIds($entities->keys()->all(), true) as $alias => $idToEntity) {
            if (! isset($allowed[$alias])) {
                continue;
            }
            $idToEntity = array_diff_key($idToEntity, array_flip($this->hiddenIds($share, $alias)));
            if ($idToEntity) {
                $out[$alias] = $idToEntity;
            }
        }

        return $out;
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
        $meta = $alias ? ($this->registry->allShareTypes()[$alias] ?? []) : [];
        if (! $view || ! $fqcn || ! method_exists($view, 'shareDetail') || ($meta['nav'] ?? 'items') !== 'items' || ! ($meta['detail'] ?? true)
            || in_array($id, $this->hiddenIds($share, $alias), true)) {
            return null;
        }

        $ids = $this->linkedIds($this->scopeEntities($share->entity, $share->depth)->keys()->all())[$alias] ?? [];
        if (! in_array($id, array_map('intval', $ids), true)) {
            return null;
        }

        $model = $fqcn::find($id);

        return $model ? ['alias' => $alias, 'model' => $model, 'view' => $view] : null;
    }

    /** Reihenfolge der Sidebar-Gruppen. */
    public const NAV_GROUPS = ['arbeit', 'wirkung', 'service', 'konto'];

    public const NAV_GROUP_LABELS = ['arbeit' => 'Arbeit', 'wirkung' => 'Wirkung', 'service' => 'Service', 'konto' => 'Konto'];

    /**
     * Bereichsseite eines Typs mit nav='section' (z. B. „Sichtbarkeit"): alle sichtbaren
     * Objekte des Typs im Ausschnitt, gebaut vom Modul (shareSection, Format wie shareDetail).
     */
    public function sectionData(OrganizationEntityShare $share, string $slug, ?string $sub, callable $link, ?\Carbon\CarbonInterface $since = null): ?array
    {
        $types = $this->registry->allShareTypes();
        $alias = collect($this->allowedTypes($share))->first(fn ($a) => $this->slugFor($a) === $slug && ($types[$a]['nav'] ?? 'items') === 'section');
        $view = $alias ? $this->registry->shareView($alias) : null;
        $fqcn = $alias ? $this->modelClass($alias) : null;
        if (! $view || ! $fqcn || ! method_exists($view, 'shareSection')) {
            return null;
        }
        $visible = $this->visibleObjects($share, $this->scopeEntities($share->entity, $share->depth))[$alias] ?? [];
        if (! $visible) {
            return null;
        }

        try {
            return $view->shareSection($alias, $fqcn::whereIn('id', array_keys($visible))->get(), [
                'details' => (bool) $share->setting('details', true),
                'sub' => $sub,
                'since' => $since,
                'link' => $link,
                'hidden' => (array) $share->setting('hidden', []),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Freigabe: Bereich nicht darstellbar', ['alias' => $alias, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Knoten für den Bereichsfilter: nur solche, an denen (oder darunter) etwas hängt.
     *
     * @return array<int, array{id: int, name: string, depth: int}>
     */
    public function areaOptions(OrganizationEntity $root, Collection $entities): array
    {
        $withLinks = array_flip(array_merge(...array_values(array_map('array_values', $this->linkedIds($entities->keys()->all(), true)))) ?: []);
        $tree = $this->treeOrder($root, $entities);
        $out = [];
        foreach ($tree as $id => $node) {
            if ($node['depth'] === 0) {
                continue;
            }
            $sub = $this->subtreeIds($id, $entities);
            if (array_intersect_key(array_flip($sub), $withLinks)) {
                $out[$id] = ['id' => $id, 'name' => $entities[$id]->name, 'depth' => $node['depth']];
            }
        }

        return count($out) > 1 ? $out : [];
    }

    /** Knoten-ID samt aller Nachfahren im Ausschnitt. */
    protected function subtreeIds(int $id, Collection $entities): array
    {
        $children = $entities->groupBy('parent_entity_id');
        $ids = [$id];
        for ($i = 0; $i < count($ids); $i++) {
            foreach ($children->get($ids[$i], collect()) as $child) {
                $ids[] = $child->id;
            }
        }

        return $ids;
    }

    /** Detailseite eines gefundenen Objekts (siehe ProvidesShareView) — plus Tab „Arbeiten". */
    public function detailData(OrganizationEntityShare $share, array $found, ?string $sub, callable $link, ?\Carbon\CarbonInterface $since = null): ?array
    {
        try {
            $detail = $found['view']->shareDetail($found['alias'], $found['model'], [
                'details' => (bool) $share->setting('details', true),
                'sub' => $sub,
                'since' => $since,
                'link' => $link,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Freigabe: Detail nicht darstellbar', ['alias' => $found['alias'], 'id' => $found['model']->getKey(), 'error' => $e->getMessage()]);

            return null;
        }
        if (! $detail || isset($detail['page'])) {
            return $detail;
        }

        $work = $this->itemWork($share, $found);
        if ($work && $work['entry_count'] > 0) {
            $detail['work'] = $work;
            $detail['tabs'][] = [
                'key' => 'arbeiten', 'label' => 'Arbeiten', 'count' => null, 'order' => 20,
                'view' => 'organization::livewire.share._work',
                'data' => ['work' => $work, 'compact' => false],
            ];
            $detail['facts'][] = ['label' => 'Geleistet', 'value' => self::hours($work['total_minutes']) . ($work['planned_minutes'] ? ' von ' . self::hours($work['planned_minutes']) : '')];
        }
        usort($detail['tabs'], fn ($a, $b) => ($a['order'] ?? 50) <=> ($b['order'] ?? 50));

        return $detail;
    }

    /** „12,5 h" bzw. „45 min" — Stunden ohne Beträge. */
    public static function hours(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes . ' min';
        }
        $h = $minutes / 60;

        return number_format($h, $h >= 100 || fmod($h, 1.0) === 0.0 ? 0 : 1, ',', '.') . ' h';
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
