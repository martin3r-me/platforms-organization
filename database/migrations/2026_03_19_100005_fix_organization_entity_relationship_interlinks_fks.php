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

        // Only add FKs/indexes if they don't already exist (100004 may have succeeded on fresh installs)
        $sm = Schema::getConnection()->getDoctrineSchemaManager();
        $fks = collect($sm->listTableForeignKeys('organization_entity_relationship_interlinks'))
            ->map(fn ($fk) => $fk->getName())
            ->toArray();

        Schema::table('organization_entity_relationship_interlinks', function (Blueprint $table) use ($fks) {
            if (!in_array('org_eri_relationship_id_fk', $fks)) {
                $table->foreign('entity_relationship_id', 'org_eri_relationship_id_fk')
                    ->references('id')->on('organization_entity_relationships')->cascadeOnDelete();
            }
            if (!in_array('org_eri_interlink_id_fk', $fks)) {
                $table->foreign('interlink_id', 'org_eri_interlink_id_fk')
                    ->references('id')->on('organization_interlinks')->cascadeOnDelete();
            }
        });

        $indexes = collect($sm->listTableIndexes('organization_entity_relationship_interlinks'))
            ->keys()
            ->toArray();

        Schema::table('organization_entity_relationship_interlinks', function (Blueprint $table) use ($indexes) {
            if (!in_array('org_eri_rel_interlink_unique', $indexes)) {
                $table->unique(['entity_relationship_id', 'interlink_id'], 'org_eri_rel_interlink_unique');
            }
            if (!in_array('org_eri_interlink_idx', $indexes)) {
                $table->index(['interlink_id'], 'org_eri_interlink_idx');
            }
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
