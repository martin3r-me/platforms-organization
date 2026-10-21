<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * organization_milestone_contributions zurückholen — jetzt MIT Code und LOSE gekoppelt.
 *
 * Der Cleanup 2026-10-20 (archive_legacy_organization_tables) hat die damals ungenutzte
 * Tabelle nach `_archived_…` verschoben. Seit 2026-10-21 ist das Einzahlen von Objectives/
 * Key Results auf Meilensteine etabliert (OrganizationMilestoneContribution), also kommt
 * sie zurück:
 *   - liegt sie im Archiv → zurückbenennen (sie war leer)
 *   - existiert sie nicht → lose neu anlegen
 *   - in jedem Fall: kein Fremdschlüssel auf milestone_id (Integrität hält das Model;
 *     OrganizationMilestone räumt beim endgültigen Löschen selbst ab).
 */
return new class extends Migration
{
    private const TABLE = 'organization_milestone_contributions';
    private const ARCHIVED = '_archived_organization_milestone_contributions';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            if (Schema::hasTable(self::ARCHIVED)) {
                Schema::rename(self::ARCHIVED, self::TABLE);
            } else {
                Schema::create(self::TABLE, function (Blueprint $table) {
                    $table->id();
                    $table->uuid('uuid')->unique();
                    $table->unsignedBigInteger('milestone_id');
                    $table->string('linkable_type');
                    $table->unsignedBigInteger('linkable_id');
                    $table->unsignedTinyInteger('weight')->nullable();
                    $table->foreignId('team_id')->constrained()->cascadeOnDelete();
                    $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->timestamps();

                    $table->unique(['milestone_id', 'linkable_type', 'linkable_id'], 'milestone_contrib_unique');
                    $table->index(['linkable_type', 'linkable_id'], 'milestone_contrib_linkable_idx');
                });

                return;
            }
        }

        // Fremdschlüssel auf milestone_id entfernen (Name aus information_schema, robust gegen Kürzung).
        $fks = collect(DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = 'milestone_id'
               AND REFERENCED_TABLE_NAME IS NOT NULL",
            [Schema::getConnection()->getDatabaseName(), self::TABLE]
        ))->pluck('CONSTRAINT_NAME')->unique()->values();

        if ($fks->isNotEmpty()) {
            Schema::table(self::TABLE, function (Blueprint $t) use ($fks) {
                foreach ($fks as $name) {
                    $t->dropForeign($name);
                }
            });
        }
    }

    public function down(): void
    {
        // Bewusst leer: die Tabelle ist jetzt Teil des Modells; ein Zurück ins Archiv wäre ein
        // eigener, bewusster Schritt (und würde Beiträge unsichtbar machen).
    }
};
