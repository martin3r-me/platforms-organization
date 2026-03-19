<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('organization_entity_relationship_interlinks')) {
            return;
        }

        Schema::table('organization_entity_relationship_interlinks', function (Blueprint $table) {
            // Add FKs that failed due to identifier name too long
            $table->foreign('entity_relationship_id', 'org_eri_relationship_id_fk')
                ->references('id')->on('organization_entity_relationships')->cascadeOnDelete();
            $table->foreign('interlink_id', 'org_eri_interlink_id_fk')
                ->references('id')->on('organization_interlinks')->cascadeOnDelete();

            // Add indexes with short names
            $table->unique(['entity_relationship_id', 'interlink_id'], 'org_eri_rel_interlink_unique');
            $table->index(['interlink_id'], 'org_eri_interlink_idx');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('organization_entity_relationship_interlinks')) {
            return;
        }

        Schema::table('organization_entity_relationship_interlinks', function (Blueprint $table) {
            $table->dropForeign('org_eri_relationship_id_fk');
            $table->dropForeign('org_eri_interlink_id_fk');
            $table->dropUnique('org_eri_rel_interlink_unique');
            $table->dropIndex('org_eri_interlink_idx');
        });
    }
};
