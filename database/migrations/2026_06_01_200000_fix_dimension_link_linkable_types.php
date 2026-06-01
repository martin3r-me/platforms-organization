<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

/**
 * Fix linkable_type values that were stored as short names (e.g. "process")
 * instead of the canonical morph alias (e.g. "organization_process").
 *
 * The DimensionLinkService now resolves context types automatically,
 * but existing rows need to be corrected so that Sidebar components
 * and EntityDimensionBridge queries (which search by morph alias) find them.
 */
return new class extends Migration
{
    public function up(): void
    {
        $morphMap = Relation::morphMap();

        // Build a lookup of short suffixes → morph alias for unambiguous matches
        // e.g. "process" → "organization_process" (only if unique)
        $suffixMap = [];
        foreach ($morphMap as $alias => $class) {
            // Extract the short name (everything after the last underscore)
            $parts = explode('_', $alias);
            $shortName = end($parts);

            if (!isset($suffixMap[$shortName])) {
                $suffixMap[$shortName] = [];
            }
            $suffixMap[$shortName][] = $alias;
        }

        // Also map full class names → alias
        $classToAlias = array_flip($morphMap);

        // Get all distinct linkable_type values currently in the table
        $types = DB::table('organization_dimension_links')
            ->select('linkable_type')
            ->distinct()
            ->pluck('linkable_type');

        foreach ($types as $type) {
            // Already a valid morph alias
            if (isset($morphMap[$type])) {
                continue;
            }

            $resolved = null;

            // Full class name → resolve to alias
            if (isset($classToAlias[$type])) {
                $resolved = $classToAlias[$type];
            }

            // Short name → check suffix map for unique match
            if (!$resolved && isset($suffixMap[$type]) && count($suffixMap[$type]) === 1) {
                $resolved = $suffixMap[$type][0];
            }

            // Also check aliases ending with _<type>
            if (!$resolved) {
                $candidates = [];
                foreach ($morphMap as $alias => $class) {
                    if (str_ends_with($alias, '_' . $type)) {
                        $candidates[] = $alias;
                    }
                }
                if (count($candidates) === 1) {
                    $resolved = $candidates[0];
                }
            }

            if ($resolved) {
                DB::table('organization_dimension_links')
                    ->where('linkable_type', $type)
                    ->update(['linkable_type' => $resolved]);
            }
        }

        // Same fix for the legacy cost_center_links table if it exists
        if (\Schema::hasTable('organization_cost_center_links') && \Schema::hasColumn('organization_cost_center_links', 'linkable_type')) {
            $legacyTypes = DB::table('organization_cost_center_links')
                ->select('linkable_type')
                ->distinct()
                ->pluck('linkable_type');

            foreach ($legacyTypes as $type) {
                if (isset($morphMap[$type])) {
                    continue;
                }

                $resolved = null;
                if (isset($classToAlias[$type])) {
                    $resolved = $classToAlias[$type];
                }
                if (!$resolved && isset($suffixMap[$type]) && count($suffixMap[$type]) === 1) {
                    $resolved = $suffixMap[$type][0];
                }
                if (!$resolved) {
                    $candidates = [];
                    foreach ($morphMap as $alias => $class) {
                        if (str_ends_with($alias, '_' . $type)) {
                            $candidates[] = $alias;
                        }
                    }
                    if (count($candidates) === 1) {
                        $resolved = $candidates[0];
                    }
                }

                if ($resolved) {
                    DB::table('organization_cost_center_links')
                        ->where('linkable_type', $type)
                        ->update(['linkable_type' => $resolved]);
                }
            }
        }
    }

    public function down(): void
    {
        // Not reversible — the old short names were incorrect.
    }
};
