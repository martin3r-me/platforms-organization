<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_signals', function (Blueprint $table) {
            $table->json('suggested_actions')->nullable()->after('trigger_metrics');
        });
    }

    public function down(): void
    {
        Schema::table('organization_signals', function (Blueprint $table) {
            $table->dropColumn('suggested_actions');
        });
    }
};
