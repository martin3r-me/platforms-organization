<?php

namespace Platform\Organization\Support;

use Illuminate\Database\Eloquent\Relations\Relation;
use Platform\Organization\Services\EntityLinkRegistry;
use Platform\Organization\Services\ObjectTypeRegistry;

/**
 * Brücke zu Parlan-Kontext-Räumen (TRAAN rooms/0.2): das ERP chattet nicht selbst — es baut
 * den Ref seines Objekts (`<herkunft>/<morph-alias>/<id>`) und schickt den Menschen per
 * signiertem, kurzlebigem Link hinein. Parlan öffnet/holt den Raum (einer pro Ref) und nimmt
 * ihn auf. Was dort gesprochen wird, kommt über die Koordinations-Brücke mit demselben Ref
 * zurück (organization_coordination_events.context_ref → Snapshot → Einheit).
 */
class ParlanContext
{
    public static function enabled(): bool
    {
        return (string) config('organization.parlan.url') !== '' && (string) config('organization.parlan.secret') !== '';
    }

    /** Herkunft = Host dieser Instanz (Präfix aller Refs). */
    public static function origin(): string
    {
        return strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
    }

    /** Stabiler Alias statt Klassenname (der bricht beim Umbenennen), oder null. */
    public static function alias(string $contextType): ?string
    {
        $alias = Relation::getMorphAlias($contextType);

        return $alias !== $contextType && $alias !== '' ? $alias : null;
    }

    public static function ref(string $alias, int $id): string
    {
        return self::origin() . '/' . $alias . '/' . $id;
    }

    /**
     * Darf dieser Nutzer das Objekt sehen? Policy (view), wo das Modell eine hat; sonst nur im
     * selben Team (team_id), sonst nein. Dieselbe Regel für Absprung, Mitnehmen und Einladen.
     */
    public static function canView($user, \Illuminate\Database\Eloquent\Model $model): bool
    {
        if (! $user) {
            return false;
        }
        if (\Illuminate\Support\Facades\Gate::getPolicyFor($model)) {
            return \Illuminate\Support\Facades\Gate::forUser($user)->allows('view', $model);
        }
        $team = $model->getAttribute('team_id');
        if ($team && method_exists($user, 'teams')) {
            return $user->teams()->where('teams.id', $team)->exists();
        }

        return false;
    }

    /** Ref → Modell dieser Instanz (oder null). */
    public static function modelForRef(string $ref): ?\Illuminate\Database\Eloquent\Model
    {
        $parts = explode('/', $ref);
        if (count($parts) !== 3 || $parts[0] !== self::origin() || ! ctype_digit($parts[2])) {
            return null;
        }
        $class = ObjectTypeRegistry::modelClass($parts[1]) ?? Relation::getMorphedModel($parts[1]);

        return $class && class_exists($class) ? $class::find((int) $parts[2]) : null;
    }

    /**
     * Beteiligte des Objekts (laut Modul), die es auch sehen dürfen — als E-Mails.
     *
     * @return list<string>
     */
    public static function participantEmails(string $alias, \Illuminate\Database\Eloquent\Model $model, ?int $exceptUserId = null): array
    {
        $ids = array_values(array_diff(app(EntityLinkRegistry::class)->contextParticipants($alias, $model), [$exceptUserId]));
        if ($ids === []) {
            return [];
        }
        $userClass = config('auth.providers.users.model');
        $out = [];
        foreach ($userClass::whereIn('id', array_slice($ids, 0, 30))->get() as $u) {
            if ($u->email && self::canView($u, $model)) {
                $out[] = strtolower($u->email);
            }
        }

        return $out;
    }

    /**
     * Signierter Absprung-Link nach Parlan (gilt 5 Minuten, nur für diesen Nutzer). Nimmt die
     * Beteiligten des Objekts mit (m), damit das Gespräch gleich die richtigen Leute hat.
     */
    public static function jumpUrl(string $contextType, int $contextId, string $title, ?string $url, string $email): ?string
    {
        $alias = self::alias($contextType);
        if (! self::enabled() || $alias === null || $contextId <= 0) {
            return null;
        }
        $parent = '';
        $members = [];
        try {
            $model = $contextType::find($contextId);
            if ($model) {
                $me = auth()->id();
                $members = array_values(array_diff(self::participantEmails($alias, $model, $me), [strtolower($email)]));
            }
            if ($model && ($p = app(EntityLinkRegistry::class)->contextParent($alias, $model)) && ! empty($p[1])) {
                $parent = self::ref((string) $p[0], (int) $p[1]);
            }
        } catch (\Throwable) {
            // Eltern-Kette ist ein Hinweis — ohne geht es auch.
        }
        $q = [
            'o' => self::origin(),
            'ref' => self::ref($alias, $contextId),
            't' => mb_substr($title !== '' ? $title : (ObjectTypeRegistry::label($alias, true) ?? $alias) . ' #' . $contextId, 0, 200),
            'u' => (string) ($url ?? ''),
            'p' => $parent,
            'l' => (string) (ObjectTypeRegistry::label($alias, true) ?? ''),
            'e' => $email,
            'x' => (string) (time() + 300),
        ];
        $keys = ['o', 'ref', 't', 'u', 'p', 'l', 'e', 'x'];
        if ($members !== []) {
            $q['m'] = implode(',', $members);
            $keys[] = 'm';
        }
        $canonical = implode("\n", array_map(static fn ($k) => $q[$k], $keys));
        $q['s'] = hash_hmac('sha256', $canonical, (string) config('organization.parlan.secret'));

        return rtrim((string) config('organization.parlan.url'), '/') . '/ctx?' . http_build_query($q, '', '&', PHP_QUERY_RFC3986);
    }
}
