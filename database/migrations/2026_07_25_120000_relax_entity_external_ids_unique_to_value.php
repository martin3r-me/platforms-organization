<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fremd-IDs: von "eine Entity hat je System höchstens einen Wert" auf
     * "ein Wert je System gehört zu genau einer Entity".
     *
     * Die Auflösungsrichtung ist value → entity (welche Entity ist IBAN X?),
     * also muss der Wert eindeutig sein — nicht die Entity. Eine Entity darf
     * dagegen mehrere Werte je System tragen (z. B. eine Umwelt-Partei mit
     * mehreren IBANs / Konten). Der bisherige Rückwärts-Index wird zum Unique
     * hochgezogen.
     */
    public function up(): void
    {
        Schema::table('organization_entity_external_ids', function (Blueprint $table) {
            $table->dropUnique(['team_id', 'system', 'entity_id']);
            $table->dropIndex(['team_id', 'system', 'value']);
            $table->unique(['team_id', 'system', 'value']);
        });
    }

    public function down(): void
    {
        Schema::table('organization_entity_external_ids', function (Blueprint $table) {
            $table->dropUnique(['team_id', 'system', 'value']);
            $table->index(['team_id', 'system', 'value']);
            $table->unique(['team_id', 'system', 'entity_id']);
        });
    }
};
