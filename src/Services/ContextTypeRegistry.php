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
     * Mapping: Kurzform → vollqualifizierter Model-Klassenname.
     *
     * Wird für Tool-Schema-Dokumentation, Kurzform-Auflösung und Lookup verwendet.
     */
    protected static array $map = [
        'project' => 'Platform\Planner\Models\PlannerProject',
        'task' => 'Platform\Planner\Models\PlannerTask',
        'ticket' => 'Platform\Helpdesk\Models\HelpdeskTicket',
        'company' => 'Platform\Crm\Models\CrmCompany',
    ];

    /**
     * Löst einen context_type auf: akzeptiert Kurzformen UND vollqualifizierte Klassennamen.
     *
     * @param string|null $contextType Kurzform (z.B. 'project') oder voller Klassenname
     * @return string|null Vollqualifizierter Klassenname oder null bei leerem Input
     */
    public static function resolve(?string $contextType): ?string
    {
        if ($contextType === null || $contextType === '') {
            return null;
        }

        // Prüfe ob Kurzform bekannt ist (case-insensitive)
        $lower = strtolower(trim($contextType));
        if (isset(static::$map[$lower])) {
            return static::$map[$lower];
        }

        // Bereits ein vollqualifizierter Klassenname → direkt zurückgeben
        if (str_contains($contextType, '\\')) {
            return $contextType;
        }

        // Unbekannter Wert → null (wird vom Aufrufer als Fehler behandelt)
        return null;
    }

    /**
     * Gibt alle erlaubten Kurzformen zurück.
     *
     * @return string[]
     */
    public static function shortNames(): array
    {
        return array_keys(static::$map);
    }

    /**
     * Gibt das vollständige Mapping zurück (Kurzform → Klassenname).
     *
     * @return array<string, string>
     */
    public static function map(): array
    {
        return static::$map;
    }

    /**
     * Gibt eine für Tool-Schema-Beschreibungen geeignete Auflistung zurück.
     *
     * @return string z.B. "project, task, ticket, company"
     */
    public static function shortNamesDescription(): string
    {
        $parts = [];
        foreach (static::$map as $short => $class) {
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
        foreach (static::$map as $short => $class) {
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
