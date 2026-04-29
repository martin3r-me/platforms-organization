<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_job_profiles', function (Blueprint $table) {
            $table->json('exclusion_criteria')->nullable()->after('kpis');
            $table->json('work_model')->nullable()->after('exclusion_criteria');
            $table->json('reporting')->nullable()->after('work_model');
        });
    }

    public function down(): void
    {
        Schema::table('organization_job_profiles', function (Blueprint $table) {
            $table->dropColumn(['exclusion_criteria', 'work_model', 'reporting']);
        });
    }
};
