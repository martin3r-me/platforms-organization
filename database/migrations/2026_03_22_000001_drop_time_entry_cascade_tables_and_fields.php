<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Entfernt die Write-Time-Cascade-Infrastruktur für Zeiterfassung.
 *
 * Die Auflösung "welche Zeiten gehören zu Entity X" passiert jetzt
 * zur Lesezeit über EntityTimeResolver statt über denormalisierte
 * Cascade-Tabellen und root_context Felder.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. root_context Felder von organization_time_entries entfernen
        if (Schema::hasTable('organization_time_entries')) {
            // Index auf root_context entfernen (falls vorhanden)
            $indexExists = collect(
                DB::select("SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'organization_time_entries' AND INDEX_NAME = 'organization_time_entries_team_root_context_index'")
            )->isNotEmpty();

            if ($indexExists) {
                Schema::table('organization_time_entries', function (Blueprint $table) {
                    $table->dropIndex('organization_time_entries_team_root_context_index');
                });
            }

            // Spalten entfernen (falls vorhanden)
            $columns = Schema::getColumnListing('organization_time_entries');

            Schema::table('organization_time_entries', function (Blueprint $table) use ($columns) {
                if (in_array('root_context_type', $columns)) {
                    $table->dropColumn('root_context_type');
                }
                if (in_array('root_context_id', $columns)) {
                    $table->dropColumn('root_context_id');
                }
            });
        }

        // 2. Cascade-Tabellen droppen
        Schema::dropIfExists('organization_time_entry_contexts');
        Schema::dropIfExists('organization_time_planned_contexts');
    }

    public function down(): void
    {
        // root_context Felder wiederherstellen
        if (Schema::hasTable('organization_time_entries')) {
            Schema::table('organization_time_entries', function (Blueprint $table) {
                $table->string('root_context_type')->nullable()->after('context_id');
                $table->unsignedBigInteger('root_context_id')->nullable()->after('root_context_type');
                $table->index(['team_id', 'root_context_type', 'root_context_id'], 'organization_time_entries_team_root_context_index');
            });
        }

        // Cascade-Tabellen wiederherstellen
        Schema::create('organization_time_entry_contexts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('time_entry_id')->constrained('organization_time_entries')->cascadeOnDelete();
            $table->string('context_type');
            $table->unsignedBigInteger('context_id');
            $table->integer('depth')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_root')->default(false);
            $table->string('context_label')->nullable();
            $table->timestamps();
            $table->index(['context_type', 'context_id']);
            $table->index(['time_entry_id', 'is_root']);
        });

        Schema::create('organization_time_planned_contexts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planned_id')->constrained('organization_time_planned')->cascadeOnDelete();
            $table->string('context_type');
            $table->unsignedBigInteger('context_id');
            $table->integer('depth')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_root')->default(false);
            $table->string('context_label')->nullable();
            $table->timestamps();
            $table->index(['context_type', 'context_id']);
            $table->index(['planned_id', 'is_root']);
        });
    }
};
