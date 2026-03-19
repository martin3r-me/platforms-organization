<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('organization_entity_relationship_interlinks')) {
            return;
        }

        $db = Schema::getConnection()->getDatabaseName();
        $table = 'organization_entity_relationship_interlinks';

        // Check existing FKs via information_schema
        $existingFks = collect(DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$db, $table]
        ))->pluck('CONSTRAINT_NAME')->toArray();

        Schema::table($table, function (Blueprint $t) use ($existingFks) {
            if (!in_array('org_eri_relationship_id_fk', $existingFks)) {
                $t->foreign('entity_relationship_id', 'org_eri_relationship_id_fk')
                    ->references('id')->on('organization_entity_relationships')->cascadeOnDelete();
            }
            if (!in_array('org_eri_interlink_id_fk', $existingFks)) {
                $t->foreign('interlink_id', 'org_eri_interlink_id_fk')
                    ->references('id')->on('organization_interlinks')->cascadeOnDelete();
            }
        });

        // Check existing indexes
        $existingIndexes = collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->pluck('Key_name')->unique()->toArray();

        Schema::table($table, function (Blueprint $t) use ($existingIndexes) {
            if (!in_array('org_eri_rel_interlink_unique', $existingIndexes)) {
                $t->unique(['entity_relationship_id', 'interlink_id'], 'org_eri_rel_interlink_unique');
            }
            if (!in_array('org_eri_interlink_idx', $existingIndexes)) {
                $t->index(['interlink_id'], 'org_eri_interlink_idx');
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
