<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Cleanup 2026-10-20: Tabellen, deren Code entfernt wurde, werden zu
 * `_archived_<table>` umbenannt — für die App unsichtbar, Daten bleiben
 * vollständig erhalten. Ein endgültiger Drop ist ein separater, bewusster Schritt.
 */
return new class extends Migration
{
    private const PREFIX = '_archived_';

    private const TABLES = [
        'organization_contexts',
        'organization_entity_links',
        'organization_vsm_functions',
        'organization_vsm_systems',
        'organization_reports',
        'organization_report_types',
        'organization_ops_room_snapshots',
        'organization_entity_type_model_mappings',
        'organization_milestone_contributions',
        'organization_eri_sla_contracts',
        'organization_sla_contracts',
        'organization_company_links',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && ! Schema::hasTable(self::PREFIX . $table)) {
                Schema::rename($table, self::PREFIX . $table);
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable(self::PREFIX . $table) && ! Schema::hasTable($table)) {
                Schema::rename(self::PREFIX . $table, $table);
            }
        }
    }
};
