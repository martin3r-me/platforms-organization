<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_entities', function (Blueprint $table) {
            $table->foreignId('cost_center_id')
                ->nullable()
                ->after('vsm_system_id')
                ->constrained('organization_cost_centers')
                ->nullOnDelete();

            $table->index(['cost_center_id']);
        });
    }

    public function down(): void
    {
        Schema::table('organization_entities', function (Blueprint $table) {
            $table->dropForeign(['cost_center_id']);
            $table->dropIndex(['cost_center_id']);
            $table->dropColumn('cost_center_id');
        });
    }
};


