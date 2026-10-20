<?php

namespace Platform\Organization\Services;

use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Einzige Stelle, die Objekttypen fremder Module benennt — abgeleitet aus den
 * registrierten EntityLinkProvidern, nicht von Hand gepflegt. Ein neues Modul
 * mit Provider ist damit sofort für brief/link/neighbors/Zeiterfassung adressierbar.
 *
 * Kurzname = Alias ohne Modul-Präfix (dev_issue → issue), nur wenn eindeutig
 * und nicht selbst ein Alias (helpdesk_board/sales_board → kein "board").
 */
class ObjectTypeRegistry
{
    /** Historische Namen, die Aufrufer noch senden. */
    private const LEGACY = [
        'planner_project' => 'project',
    ];

    private static ?array $shortNames = null;
    private static ?int $aliasCount = null;

    /** @return string[] */
    public static function aliases(): array
    {
        return app(EntityLinkRegistry::class)->allMorphAliases();
    }

    /**
     * @return array<string, string> Kurzname → Morph-Alias
     */
    public static function shortNames(): array
    {
        $aliases = static::aliases();
        // Neu berechnen, falls nach dem ersten Aufruf noch Provider registriert wurden.
        if (static::$shortNames !== null && static::$aliasCount === count($aliases)) {
            return static::$shortNames;
        }

        $isAlias = array_flip($aliases);
        $candidates = [];
        foreach ($aliases as $alias) {
            $pos = strpos($alias, '_');
            if ($pos === false) {
                continue;
            }
            $short = substr($alias, $pos + 1);
            if (! isset($isAlias[$short])) {
                $candidates[$short][] = $alias;
            }
        }

        static::$aliasCount = count($aliases);

        return static::$shortNames = array_map(
            fn ($list) => $list[0],
            array_filter($candidates, fn ($list) => count($list) === 1),
        );
    }

    /**
     * Kurzname, Alias, Legacy-Name oder FQCN → Morph-Alias, oder null.
     */
    public static function resolve(?string $type): ?string
    {
        $type = trim((string) $type);
        if ($type === '') {
            return null;
        }

        $lower = strtolower($type);
        if (in_array($lower, static::aliases(), true)) {
            return $lower;
        }
        if (isset(self::LEGACY[$lower])) {
            return self::LEGACY[$lower];
        }
        if ($alias = static::shortNames()[$lower] ?? null) {
            return $alias;
        }

        // Rückfall: jeder Morph-Alias bzw. jede gemappte Klasse, auch ohne Provider (z. B. inbox_item).
        if (Relation::getMorphedModel($lower)) {
            return $lower;
        }

        return array_flip(Relation::morphMap())[ltrim($type, '\\')] ?? null;
    }

    /**
     * Der Name, den Agenten verwenden sollen (Kurzname, sonst Alias).
     */
    public static function displayName(string $alias): string
    {
        return array_search($alias, static::shortNames(), true) ?: $alias;
    }

    /**
     * Für Fehlermeldungen/Schemas: alle adressierbaren Typen, sortiert.
     *
     * @return string[]
     */
    public static function knownTypes(): array
    {
        $types = array_map(fn ($a) => static::displayName($a), static::aliases());
        sort($types);

        return array_values(array_unique($types));
    }

    public static function modelClass(string $alias): ?string
    {
        $class = Relation::getMorphedModel($alias);

        return $class && class_exists($class) ? $class : null;
    }

    public static function label(string $alias, bool $singular = false): ?string
    {
        $config = app(EntityLinkRegistry::class)->allLinkTypeConfig()[$alias] ?? [];

        return ($singular ? ($config['singular'] ?? null) : null) ?? $config['label'] ?? null;
    }
}
