<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_signals', function (Blueprint $table) {
            // severity already accepts string(20), algedonic fits.
            // Add dismissed_reason for learning pipeline.
            $table->text('dismissed_reason')->nullable()->after('resolved_by');
        });
    }

    public function down(): void
    {
        Schema::table('organization_signals', function (Blueprint $table) {
            $table->dropColumn('dismissed_reason');
        });
    }
};
