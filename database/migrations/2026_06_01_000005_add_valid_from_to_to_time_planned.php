<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_time_planned', function (Blueprint $table) {
            $table->date('valid_from')->nullable()->after('is_active');
            $table->date('valid_to')->nullable()->after('valid_from');
        });
    }

    public function down(): void
    {
        Schema::table('organization_time_planned', function (Blueprint $table) {
            $table->dropColumn(['valid_from', 'valid_to']);
        });
    }
};
