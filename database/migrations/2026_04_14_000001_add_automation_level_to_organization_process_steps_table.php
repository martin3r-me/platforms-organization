<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_process_steps', function (Blueprint $table) {
            $table->string('automation_level')->nullable()->after('corefit_classification');
        });
    }

    public function down(): void
    {
        Schema::table('organization_process_steps', function (Blueprint $table) {
            $table->dropColumn('automation_level');
        });
    }
};
