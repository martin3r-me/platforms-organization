<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * VSM-Phase 1, Schritt 1: Entity-Klassifikation.
 *
 * Fuegt `vsm_class` (carrier/actor/observed) und `can_be_perspective`
 * auf organization_entity_types ein und klassifiziert die 20 bestehenden
 * Entity-Types laut Phase-1-Brief vom 08.06.2026.
 *
 * `system_agent` (21. Typ, actor) wird in Schritt 2 separat angelegt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_entity_types', function (Blueprint $table) {
            $table->string('vsm_class', 16)->nullable()->after('entity_type_group_id');
            $table->boolean('can_be_perspective')->default(false)->after('vsm_class');
            $table->index('vsm_class');
        });

        $carrier = [
            'business_unit',
            'venture',
            'internal_platform',
            'initiative',
            'external_customer',
        ];

        $actor = [
            'person',
            'board',
            'capability_area',
            'customer_department',
        ];

        $observed = [
            'external_vendor',
            'software',
            'competitor',
            'market',
            'macro_indicator',
            'regulatory',
            'technology_trend',
            'region',
            'product',
            'brand',
            'group',
            'program',
        ];

        DB::table('organization_entity_types')
            ->whereIn('code', $carrier)
            ->update(['vsm_class' => 'carrier', 'can_be_perspective' => true]);

        DB::table('organization_entity_types')
            ->whereIn('code', $actor)
            ->update(['vsm_class' => 'actor', 'can_be_perspective' => false]);

        DB::table('organization_entity_types')
            ->whereIn('code', $observed)
            ->update(['vsm_class' => 'observed', 'can_be_perspective' => false]);
    }

    public function down(): void
    {
        Schema::table('organization_entity_types', function (Blueprint $table) {
            $table->dropIndex(['vsm_class']);
            $table->dropColumn(['vsm_class', 'can_be_perspective']);
        });
    }
};
