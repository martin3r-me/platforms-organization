<?php

namespace Platform\Organization\Observers;

use Platform\Organization\Models\OrganizationDimensionValue;

/**
 * Hält DimensionLinks konsistent mit dem Lebenszyklus ihres Values.
 *
 * Wird ein Value soft-gelöscht, werden seine Links hart entfernt — konsistent
 * mit dem bestehenden DB-FK (`dimension_value_id` ist cascadeOnDelete beim
 * Hard-Delete). Verhindert, dass erneut „Geister-Links" auf gelöschte Values
 * entstehen (Phase-0-Befund 1). Beim Force-Delete räumt der FK selbst auf.
 */
class DimensionValueObserver
{
    public function deleted(OrganizationDimensionValue $value): void
    {
        // Force-Delete: DB-FK (cascadeOnDelete) entfernt die Links bereits.
        if (method_exists($value, 'isForceDeleting') && $value->isForceDeleting()) {
            return;
        }

        // Soft-Delete: Links hart entfernen (sie haben keinen SoftDelete und
        // würden sonst auf einen gelöschten Value zeigen).
        $value->links()->delete();
    }
}
