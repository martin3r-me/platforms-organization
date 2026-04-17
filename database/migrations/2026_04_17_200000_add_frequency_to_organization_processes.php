<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_processes', function (Blueprint $table) {
            $table->string('frequency')->nullable()->after('hourly_rate');
        });
    }

    public function down(): void
    {
        Schema::table('organization_processes', function (Blueprint $table) {
            $table->dropColumn('frequency');
        });
    }
};
