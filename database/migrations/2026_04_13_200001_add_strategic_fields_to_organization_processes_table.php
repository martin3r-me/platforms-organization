<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_processes', function (Blueprint $table) {
            $table->text('target_description')->nullable()->after('metadata');
            $table->text('value_proposition')->nullable()->after('target_description');
            $table->text('cost_analysis')->nullable()->after('value_proposition');
            $table->text('risk_assessment')->nullable()->after('cost_analysis');
            $table->text('improvement_levers')->nullable()->after('risk_assessment');
            $table->text('action_plan')->nullable()->after('improvement_levers');
            $table->text('standardization_notes')->nullable()->after('action_plan');
        });
    }

    public function down(): void
    {
        Schema::table('organization_processes', function (Blueprint $table) {
            $table->dropColumn([
                'target_description',
                'value_proposition',
                'cost_analysis',
                'risk_assessment',
                'improvement_levers',
                'action_plan',
                'standardization_notes',
            ]);
        });
    }
};
