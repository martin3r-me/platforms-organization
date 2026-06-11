<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Person ↔ JobProfile bekommt einen Context (in welcher Linie/Carrier
 * traegt die Person dieses Profile?). Beer-Recursion-konform: ein Junior
 * Marketing Profile bei Esskultur.Digital ist etwas anderes als das gleiche
 * Profile bei BANKETT.DIGITAL — die Linie macht die Wertschoepfungs-Heimat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_person_job_profiles', function (Blueprint $table) {
            $table->foreignId('context_entity_id')
                ->nullable()
                ->after('job_profile_id')
                ->constrained('organization_entities')
                ->nullOnDelete();

            $table->index('context_entity_id', 'pjp_context_idx');
        });
    }

    public function down(): void
    {
        Schema::table('organization_person_job_profiles', function (Blueprint $table) {
            $table->dropForeign(['context_entity_id']);
            $table->dropIndex('pjp_context_idx');
            $table->dropColumn('context_entity_id');
        });
    }
};
