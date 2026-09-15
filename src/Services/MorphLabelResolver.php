<?php

namespace Platform\Organization\Services;

use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Baut aus den vorhandenen Spalten einer Tabelle einen SQL-Ausdruck fuer ein
 * menschenlesbares Label (`... as label`). Genutzt von der VSM-Mindmap (live)
 * und vom Snapshot-Builder, damit verlinkte Objekte (Projekte, Transaktionen,
 * URLs, Threads, …) einen Titel statt nur "#id" zeigen.
 *
 * Strategie: COALESCE ueber ALLE vorhandenen sinnvollen Spalten (nicht nur die
 * erste) in Reihenfolge menschlicher Aussagekraft — ist das Primaerfeld leer
 * (z.B. subject=''), greift das naechste (sender_label) statt auf #id zu fallen.
 */
class MorphLabelResolver
{
    /** Spalten VOR dem first/last-Namen (klassische Titel-Felder). */
    private const HEAD = ['name', 'title', 'subject', 'label', 'display_name', 'headline', 'heading'];

    /** Spalten NACH dem first/last-Namen (domänenspezifische Fallbacks). */
    private const TAIL = ['counterparty_name', 'creditor_name', 'debtor_name', 'sender_label', 'path', 'url', 'reference', 'summary', 'preview'];

    /**
     * @param  Collection<int, string>  $columns  Vorhandene Spaltennamen der Tabelle.
     */
    public static function expression(Collection $columns): Expression
    {
        $terms = [];

        foreach (self::HEAD as $col) {
            if ($columns->contains($col)) {
                $terms[] = "NULLIF({$col}, '')";
            }
        }

        // first_name + last_name als kombiniertes Feld (Personen)
        if ($columns->contains('first_name') && $columns->contains('last_name')) {
            $terms[] = "NULLIF(TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))), '')";
        }

        foreach (self::TAIL as $col) {
            if ($columns->contains($col)) {
                $terms[] = "NULLIF({$col}, '')";
            }
        }

        if ($columns->contains('email')) {
            $terms[] = "NULLIF(email, '')";
        }

        $terms[] = "CONCAT('#', id)"; // finaler Fallback

        return DB::raw('COALESCE(' . implode(', ', $terms) . ') as label');
    }
}
