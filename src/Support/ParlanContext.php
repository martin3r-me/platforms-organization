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
     * Signierter Absprung-Link nach Parlan (gilt 5 Minuten, nur für diesen Nutzer).
     */
    public static function jumpUrl(string $contextType, int $contextId, string $title, ?string $url, string $email): ?string
    {
        $alias = self::alias($contextType);
        if (! self::enabled() || $alias === null || $contextId <= 0) {
            return null;
        }
        $parent = '';
        try {
            $model = $contextType::find($contextId);
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
        $canonical = implode("\n", array_map(static fn ($k) => $q[$k], ['o', 'ref', 't', 'u', 'p', 'l', 'e', 'x']));
        $q['s'] = hash_hmac('sha256', $canonical, (string) config('organization.parlan.secret'));

        return rtrim((string) config('organization.parlan.url'), '/') . '/ctx?' . http_build_query($q, '', '&', PHP_QUERY_RFC3986);
    }
}
