<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('organization_process_steps', 'external_cost_per_run')) {
            Schema::table('organization_process_steps', function (Blueprint $table) {
                $table->decimal('external_cost_per_run', 10, 2)->nullable()->after('wait_target_minutes');
            });
        }

        if (! Schema::hasColumn('organization_process_improvements', 'savings_type')) {
            Schema::table('organization_process_improvements', function (Blueprint $table) {
                $table->string('savings_type', 50)->nullable()->after('projected_hourly_rate');
            });
        }

        if (! Schema::hasColumn('organization_process_improvements', 'projected_external_cost_per_run')) {
            Schema::table('organization_process_improvements', function (Blueprint $table) {
                $table->decimal('projected_external_cost_per_run', 10, 2)->nullable()->after('savings_type');
            });
        }
    }

    public function down(): void
    {
        Schema::table('organization_process_steps', function (Blueprint $table) {
            if (Schema::hasColumn('organization_process_steps', 'external_cost_per_run')) {
                $table->dropColumn('external_cost_per_run');
            }
        });

        Schema::table('organization_process_improvements', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('organization_process_improvements', 'savings_type')) {
                $cols[] = 'savings_type';
            }
            if (Schema::hasColumn('organization_process_improvements', 'projected_external_cost_per_run')) {
                $cols[] = 'projected_external_cost_per_run';
            }
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
