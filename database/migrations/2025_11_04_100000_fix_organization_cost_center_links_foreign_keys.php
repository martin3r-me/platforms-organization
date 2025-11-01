<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('organization_cost_center_links')) {
            return;
        }

        // Prüfe und korrigiere Foreign Key Constraints
        Schema::table('organization_cost_center_links', function (Blueprint $table) {
            // Entferne falsche Foreign Keys
            try {
                DB::statement('ALTER TABLE `organization_cost_center_links` DROP FOREIGN KEY `organization_cost_center_links_cost_center_id_foreign`');
            } catch (\Exception $e) {
                // Constraint existiert nicht oder hat anderen Namen
            }
            
            try {
                DB::statement('ALTER TABLE `organization_cost_center_links` DROP FOREIGN KEY `organization_cost_center_links_entity_id_foreign`');
            } catch (\Exception $e) {
                // Constraint existiert nicht
            }
        });

        // Füge korrekten Foreign Key hinzu
        Schema::table('organization_cost_center_links', function (Blueprint $table) {
            if (Schema::hasColumn('organization_cost_center_links', 'cost_center_id')) {
                // Prüfe ob Foreign Key bereits existiert (korrekt)
                $foreignKeys = DB::select("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'organization_cost_center_links'
                    AND COLUMN_NAME = 'cost_center_id'
                    AND REFERENCED_TABLE_NAME = 'organization_cost_centers'
                ");
                
                if (empty($foreignKeys)) {
                    // Füge korrekten Foreign Key hinzu
                    try {
                        $table->foreign('cost_center_id', 'fk_org_cost_center_links_cost_center_id')
                            ->references('id')
                            ->on('organization_cost_centers')
                            ->cascadeOnDelete();
                    } catch (\Exception $e) {
                        // Falls das nicht funktioniert, direkt SQL
                        DB::statement('
                            ALTER TABLE `organization_cost_center_links` 
                            ADD CONSTRAINT `fk_org_cost_center_links_cost_center_id` 
                            FOREIGN KEY (`cost_center_id`) 
                            REFERENCES `organization_cost_centers` (`id`) 
                            ON DELETE CASCADE
                        ');
                    }
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Nicht rückgängig machen
    }
};

