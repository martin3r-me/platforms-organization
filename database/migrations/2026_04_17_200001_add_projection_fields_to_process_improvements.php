<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = 'organization_process_improvements';

        if (! Schema::hasColumn($table, 'target_step_id')) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('target_step_id')->nullable()->after('metadata')
                    ->constrained('organization_process_steps')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn($table, 'projected_duration_target_minutes')) {
            Schema::table($table, function (Blueprint $t) {
                $t->integer('projected_duration_target_minutes')->nullable()->after('target_step_id');
            });
        }

        if (! Schema::hasColumn($table, 'projected_automation_level')) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('projected_automation_level')->nullable()->after('projected_duration_target_minutes');
            });
        }

        if (! Schema::hasColumn($table, 'projected_complexity')) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('projected_complexity')->nullable()->after('projected_automation_level');
            });
        }

        // Add composite index if not exists
        Schema::table($table, function (Blueprint $t) {
            try {
                $t->index(['process_id', 'target_step_id'], 'org_proc_improv_process_target_step_idx');
            } catch (\Exception $e) {
                // Index already exists
            }
        });
    }

    public function down(): void
    {
        $table = 'organization_process_improvements';

        Schema::table($table, function (Blueprint $t) {
            try {
                $t->dropIndex('org_proc_improv_process_target_step_idx');
            } catch (\Exception $e) {
            }
        });

        if (Schema::hasColumn($table, 'target_step_id')) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('target_step_id');
            });
        }

        $drops = [];
        foreach (['projected_duration_target_minutes', 'projected_automation_level', 'projected_complexity'] as $col) {
            if (Schema::hasColumn($table, $col)) {
                $drops[] = $col;
            }
        }
        if ($drops) {
            Schema::table($table, function (Blueprint $t) use ($drops) {
                $t->dropColumn($drops);
            });
        }
    }
};
