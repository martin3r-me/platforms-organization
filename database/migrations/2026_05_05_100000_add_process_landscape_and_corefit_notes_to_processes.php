<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_processes', function (Blueprint $table) {
            $table->text('process_landscape')->nullable()->after('standardization_notes');
            $table->text('corefit_classification_notes')->nullable()->after('process_landscape');
        });
    }

    public function down(): void
    {
        Schema::table('organization_processes', function (Blueprint $table) {
            $table->dropColumn(['process_landscape', 'corefit_classification_notes']);
        });
    }
};
