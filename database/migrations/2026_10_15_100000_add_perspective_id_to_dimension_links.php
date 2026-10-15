<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema-Drift-Reconciliation: fügt die in Produktion fehlende Spalte
 * `perspective_id` additiv nach.
 *
 * Die Original-Migration (2026_05_23_100003) legt perspective_id an, no-oppt aber
 * per `if (Schema::hasTable(...)) return;` auf vorbestehenden Tabellen — dadurch
 * fehlt die Spalte produktiv. Diese Migration ist idempotent (hasColumn-Guard)
 * und rein additiv: nullable Spalte + Index + FK (nur wenn Perspektiven-Tabelle
 * existiert). Der perspective_id-haltige Unique-Index wird bewusst NICHT hier
 * gesetzt — er braucht vorher eine Dubletten-Prüfung (siehe Härtungsplan).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('organization_dimension_links')) {
            return;
        }
        if (Schema::hasColumn('organization_dimension_links', 'perspective_id')) {
            return;
        }

        Schema::table('organization_dimension_links', function (Blueprint $table) {
            $table->unsignedBigInteger('perspective_id')->nullable()->after('linkable_id');
            $table->index(['perspective_id']);
        });

        if (Schema::hasTable('organization_perspectives')) {
            Schema::table('organization_dimension_links', function (Blueprint $table) {
                $table->foreign('perspective_id')
                    ->references('id')->on('organization_perspectives')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('organization_dimension_links', 'perspective_id')) {
            return;
        }

        Schema::table('organization_dimension_links', function (Blueprint $table) {
            if (Schema::hasTable('organization_perspectives')) {
                $table->dropForeign(['perspective_id']);
            }
            $table->dropIndex(['perspective_id']);
            $table->dropColumn('perspective_id');
        });
    }
};
