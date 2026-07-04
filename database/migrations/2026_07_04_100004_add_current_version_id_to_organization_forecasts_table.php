<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_forecasts', function (Blueprint $table) {
            $table->foreignId('current_version_id')->nullable()->after('content')
                ->constrained('organization_forecast_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('organization_forecasts', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
            $table->dropColumn('current_version_id');
        });
    }
};
