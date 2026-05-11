<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('organization_processes', 'workshop_notes')) {
            Schema::table('organization_processes', function (Blueprint $table) {
                $table->json('workshop_notes')->nullable()->after('corefit_classification_notes');
            });
        }
    }

    public function down(): void
    {
        Schema::table('organization_processes', function (Blueprint $table) {
            if (Schema::hasColumn('organization_processes', 'workshop_notes')) {
                $table->dropColumn('workshop_notes');
            }
        });
    }
};
