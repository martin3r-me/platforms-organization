<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_process_steps', function (Blueprint $table) {
            $table->string('gateway_type')->nullable()->after('step_type');
            $table->string('event_type')->nullable()->after('gateway_type');
            $table->index(['process_id', 'step_type', 'gateway_type'], 'idx_steps_process_gateway');
            $table->index(['process_id', 'step_type', 'event_type'], 'idx_steps_process_event');
        });
    }

    public function down(): void
    {
        Schema::table('organization_process_steps', function (Blueprint $table) {
            $table->dropIndex('idx_steps_process_gateway');
            $table->dropIndex('idx_steps_process_event');
            $table->dropColumn(['gateway_type', 'event_type']);
        });
    }
};
