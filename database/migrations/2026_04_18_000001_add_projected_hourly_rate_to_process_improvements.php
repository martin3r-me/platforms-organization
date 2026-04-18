<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = 'organization_process_improvements';

        if (! Schema::hasColumn($table, 'projected_hourly_rate')) {
            Schema::table($table, function (Blueprint $t) {
                $t->decimal('projected_hourly_rate', 10, 2)->nullable()->after('projected_complexity');
            });
        }
    }

    public function down(): void
    {
        $table = 'organization_process_improvements';

        if (Schema::hasColumn($table, 'projected_hourly_rate')) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('projected_hourly_rate');
            });
        }
    }
};
