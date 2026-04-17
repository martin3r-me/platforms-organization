<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_process_improvements', function (Blueprint $table) {
            $table->foreignId('target_step_id')->nullable()->after('metadata')
                ->constrained('organization_process_steps')->nullOnDelete();
            $table->integer('projected_duration_target_minutes')->nullable()->after('target_step_id');
            $table->string('projected_automation_level')->nullable()->after('projected_duration_target_minutes');
            $table->string('projected_complexity')->nullable()->after('projected_automation_level');

            $table->index(['process_id', 'target_step_id']);
        });
    }

    public function down(): void
    {
        Schema::table('organization_process_improvements', function (Blueprint $table) {
            $table->dropIndex(['process_id', 'target_step_id']);
            $table->dropConstrainedForeignId('target_step_id');
            $table->dropColumn(['projected_duration_target_minutes', 'projected_automation_level', 'projected_complexity']);
        });
    }
};
