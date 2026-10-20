<?php

namespace Platform\Organization\Services;

/**
 * Registry für erlaubte context_types bei Zeiteinträgen.
 *
 * Zentrale Stelle für:
 * - Kurzform → vollqualifizierter Klassenname (z.B. 'project' → 'Platform\Planner\Models\PlannerProject')
 * - Auflistung aller erlaubten context_types für Tool-Schemas und Lookups
 */
class ContextTypeRegistry
{
    /**
     * Typen ohne Morph-Alias (nicht verknüpfbar, aber buchbar). Alles andere
     * kommt aus ObjectTypeRegistry (= registrierte EntityLinkProvider).
     */
    protected static array $extra = [
        'sprint' => 'Platform\Planner\Models\PlannerSprint',
        'board' => 'Platform\Dev\Models\DevBoard',
    ];

    /** Zusätzlich beworbene, nicht zeit-kaskadierende Typen. */
    protected const ADVERTISED = ['crm_company', 'change_project'];

    /**
     * Löst einen context_type auf: akzeptiert Kurzformen, Morph-Aliase UND
     * vollqualifizierte Klassennamen.
     *
     * @param string|null $contextType Kurzform (z.B. 'project') oder voller Klassenname
     * @return string|null Vollqualifizierter Klassenname oder null bei leerem/unbekanntem Input
     */
    public static function resolve(?string $contextType): ?string
    {
        if ($contextType === null || $contextType === '') {
            return null;
        }

        $lower = strtolower(trim($contextType));
        if (isset(static::$extra[$lower])) {
            return static::$extra[$lower];
        }

        if ($alias = ObjectTypeRegistry::resolve($contextType)) {
            return ObjectTypeRegistry::modelClass($alias) ?? \Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($alias);
        }

        // Bereits ein vollqualifizierter Klassenname → direkt zurückgeben
        if (str_contains($contextType, '\\')) {
            return $contextType;
        }

        return null;
    }

    /**
     * Gibt alle beworbenen Kurzformen zurück: zeiterfassbare Typen der Provider
     * (timeTrackableCascades) + ADVERTISED + Extras.
     *
     * @return string[]
     */
    public static function shortNames(): array
    {
        return array_keys(static::map());
    }

    /**
     * Gibt das Mapping zurück (Kurzform → Klassenname).
     *
     * @return array<string, string>
     */
    public static function map(): array
    {
        $map = [];
        $aliases = array_merge(
            array_keys(app(EntityLinkRegistry::class)->allTimeTrackableCascades()),
            static::ADVERTISED,
        );
        foreach ($aliases as $alias) {
            if ($class = ObjectTypeRegistry::modelClass($alias)) {
                $map[ObjectTypeRegistry::displayName($alias)] = $class;
            }
        }

        return $map + static::$extra;
    }

    /**
     * Gibt eine für Tool-Schema-Beschreibungen geeignete Auflistung zurück.
     *
     * @return string z.B. "project, task, ticket, company"
     */
    public static function shortNamesDescription(): string
    {
        $parts = [];
        foreach (static::map() as $short => $class) {
            $parts[] = "\"{$short}\" ({$class})";
        }

        return implode(', ', $parts);
    }

    /**
     * Gibt Lookup-Daten für organization.lookups.GET zurück.
     *
     * @return array
     */
    public static function lookupEntries(): array
    {
        $entries = [];
        foreach (static::map() as $short => $class) {
            $entries[] = [
                'short' => $short,
                'class' => $class,
                'module' => static::extractModule($class),
            ];
        }

        return $entries;
    }

    /**
     * Extrahiert den Modul-Namen aus einem vollqualifizierten Klassennamen.
     */
    protected static function extractModule(string $class): ?string
    {
        if (preg_match('/Platform\\\\([^\\\\]+)\\\\/', $class, $matches)) {
            return strtolower($matches[1]);
        }

        return null;
    }
}
