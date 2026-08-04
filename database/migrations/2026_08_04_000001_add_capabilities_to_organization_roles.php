<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plural-Capabilities auf Rollen — analog zu organization_entity_relation_types.
 * Der Authz-Materializer (Phase 3) leitet daraus die Grant-Stufe ab
 * (höchste gewinnt: manage>write>read). Ersetzt die tote Singular-Spalte
 * 'capability', die weder im Model/fillable noch im UI je ankam.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('organization_roles', 'capabilities')) {
            Schema::table('organization_roles', function (Blueprint $table) {
                $table->json('capabilities')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('organization_roles', 'capabilities')) {
            Schema::table('organization_roles', function (Blueprint $table) {
                $table->dropColumn('capabilities');
            });
        }
    }
};
