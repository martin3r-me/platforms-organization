<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kontext-Kommunikation (TRAAN rooms/0.2): ein Koordinations-Event aus einem Kontext-Raum
 * trägt den Ref des Objekts (`<herkunft>/<morph-alias>/<id>`). Der Snapshot verortet darüber
 * an der Einheit, an der das Objekt hängt — die Kommunikation kommt zurück in die Organisation.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('organization_coordination_events') || Schema::hasColumn('organization_coordination_events', 'context_ref')) {
            return;
        }
        Schema::table('organization_coordination_events', function (Blueprint $t) {
            $t->string('context_ref', 300)->nullable()->after('thread');
            $t->index('context_ref', 'org_coord_events_context_ref');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('organization_coordination_events', 'context_ref')) {
            Schema::table('organization_coordination_events', function (Blueprint $t) {
                $t->dropIndex('org_coord_events_context_ref');
                $t->dropColumn('context_ref');
            });
        }
    }
};
