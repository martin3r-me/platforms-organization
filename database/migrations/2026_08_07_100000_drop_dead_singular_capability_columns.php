<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aufräumen: die beiden toten Singular-Spalten `capability` entfernen.
 *
 * Beide wurden Ende Juli 2026 als eigene Zugriffsstufe angelegt, aber nie
 * gelesen — der Authz-Materializer wertet stattdessen die Plural-JSON-Spalten
 * `capabilities` aus (höchste Stufe gewinnt: manage>write>read):
 *
 *   organization_roles.capability                  → tot (ersetzt durch capabilities, 2026_08_04)
 *   organization_entity_relation_types.capability  → tot (Materializer liest capabilities)
 *
 * Weder in fillable/casts noch in der UI angekommen. down() stellt die Spalten
 * inkl. der ursprünglichen Backfills wieder her.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('organization_roles', 'capability')) {
            Schema::table('organization_roles', function (Blueprint $table) {
                $table->dropColumn('capability');
            });
        }

        if (Schema::hasColumn('organization_entity_relation_types', 'capability')) {
            Schema::table('organization_entity_relation_types', function (Blueprint $table) {
                $table->dropColumn('capability');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('organization_roles', 'capability')) {
            Schema::table('organization_roles', function (Blueprint $table) {
                $table->string('capability', 16)->nullable()->after('vsm_system');
            });

            DB::table('organization_roles')->whereIn('slug', [
                'venture-lead',
                'business-unit-lead',
                'geschaeftsfuehrer',
                'inhaber',
                'project-lead',
                'account-manager',
            ])->update(['capability' => 'manage']);

            DB::table('organization_roles')->whereIn('slug', [
                'aufsichtsrat',
                'beirat',
                'internal-auditor',
                'compliance-officer',
                'quality-officer',
                'risk-officer',
            ])->update(['capability' => 'read']);
        }

        if (! Schema::hasColumn('organization_entity_relation_types', 'capability')) {
            Schema::table('organization_entity_relation_types', function (Blueprint $table) {
                $table->string('capability', 16)->nullable()->after('code');
            });

            DB::table('organization_entity_relation_types')
                ->whereIn('code', ['owns_venture', 'co_owns_venture'])
                ->update(['capability' => 'manage']);
        }
    }
};
