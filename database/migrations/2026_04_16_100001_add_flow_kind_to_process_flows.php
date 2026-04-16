<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_process_flows', function (Blueprint $table) {
            $table->string('flow_kind')->default('sequence')->after('condition_expression');
            $table->unsignedTinyInteger('priority')->default(100)->after('flow_kind');
            $table->index(['from_step_id', 'priority'], 'idx_flows_from_priority');
            $table->index(['process_id', 'flow_kind'], 'idx_flows_process_kind');
        });
    }

    public function down(): void
    {
        Schema::table('organization_process_flows', function (Blueprint $table) {
            $table->dropIndex('idx_flows_from_priority');
            $table->dropIndex('idx_flows_process_kind');
            $table->dropColumn(['flow_kind', 'priority']);
        });
    }
};
