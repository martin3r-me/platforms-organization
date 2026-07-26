<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Capability an den Relation-TYPEN — die Kante trägt sie, genau wie die Rolle.
 * Default null = keine Auswirkung auf Rechte. Nur wenige Typen "leuchten":
 * Besitz-Relations (owns_venture/co_owns_venture) → manage auf die besessene
 * Entity. Materialize (Phase 4) macht daraus Grants (Subjekt = Person-Seite).
 *
 * Bewusst eine eigene, klare Spalte — NICHT das bestehende freie `capabilities`-
 * JSON (das ist Beer-Channel-Tagging, andere Bedeutung).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('organization_entity_relation_types', 'capability')) {
            Schema::table('organization_entity_relation_types', function (Blueprint $table) {
                $table->string('capability', 16)->nullable()->after('code');
            });
        }

        DB::table('organization_entity_relation_types')
            ->whereIn('code', ['owns_venture', 'co_owns_venture'])
            ->update(['capability' => 'manage']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('organization_entity_relation_types', 'capability')) {
            Schema::table('organization_entity_relation_types', function (Blueprint $table) {
                $table->dropColumn('capability');
            });
        }
    }
};
