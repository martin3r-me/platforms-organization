<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Platform\Organization\Services\DimensionLinkService;

/**
 * Re-normalize linkable_type values across dimension_links + cost_center_links.
 *
 * The 2026_06_01 fix migration only ran once and any rows written after it
 * with a non-canonical alias (e.g. "planner_project" instead of "project")
 * stayed broken — Sidebar / Entity Show filter them out via morph map lookup.
 *
 * This migration is idempotent: calling resolveContextType on an already
 * canonical alias is a no-op. Safe to run again after future drifts.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->normalizeTable('organization_dimension_links');

        if (\Schema::hasTable('organization_cost_center_links')) {
            $this->normalizeTable('organization_cost_center_links');
        }
    }

    public function down(): void
    {
        // Not reversible — old short/non-canonical names were incorrect.
    }

    protected function normalizeTable(string $table): void
    {
        $types = DB::table($table)
            ->select('linkable_type')
            ->distinct()
            ->pluck('linkable_type');

        foreach ($types as $type) {
            if (!$type) {
                continue;
            }

            $resolved = DimensionLinkService::resolveContextType($type);

            if ($resolved !== $type) {
                DB::table($table)
                    ->where('linkable_type', $type)
                    ->update(['linkable_type' => $resolved]);
            }
        }
    }
};
