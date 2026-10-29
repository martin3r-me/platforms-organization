<?php

namespace Platform\Organization\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Schützt polymorphe context()-Eager-Loads vor Resten entfernter Module.
 *
 * Steht in context_type ein Typ, der nicht mehr auf eine existierende Klasse
 * auflösbar ist (z. B. das alte `Platform\Meetings\Models\Meeting` nach der
 * Meetings-Auflösung, dessen un-geprunte Zeit-Zeilen in der Tabelle liegen
 * bleiben), würde das Instanziieren beim Eager-Load `with('context')` einen
 * „Class not found" werfen und die ganze Seite mit 500 killen.
 *
 * `->resolvableContext()` blendet genau solche Zeilen aus — modul-agnostisch über
 * die Morph-Map + class_exists, nicht gegen einen einzelnen toten Typ hartkodiert.
 */
trait ResolvesContextMorph
{
    public function scopeResolvableContext(Builder $query): Builder
    {
        $dead = static::query()
            ->whereNotNull('context_type')
            ->distinct()
            ->pluck('context_type')
            ->reject(fn ($type) => static::contextTypeResolvable((string) $type))
            ->values()
            ->all();

        if ($dead) {
            $query->whereNotIn('context_type', $dead);
        }

        return $query;
    }

    /** Lässt sich der gespeicherte Morph-Typ (Alias ODER FQCN) auf eine Klasse auflösen? */
    protected static function contextTypeResolvable(string $type): bool
    {
        if ($type === '') {
            return false;
        }

        // Morph-Alias (z. B. inbox_item) → gemappte Klasse.
        if (Relation::getMorphedModel($type) !== null) {
            return true;
        }

        // Sonst als voll qualifizierten Klassennamen behandeln.
        return class_exists($type);
    }
}
