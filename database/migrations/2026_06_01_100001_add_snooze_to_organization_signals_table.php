<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_signals', function (Blueprint $table) {
            $table->timestamp('snooze_until')->nullable()->after('dismissed_reason');
            $table->index(['team_id', 'status', 'snooze_until'], 'org_signals_team_status_snooze');
        });
    }

    public function down(): void
    {
        Schema::table('organization_signals', function (Blueprint $table) {
            $table->dropIndex('org_signals_team_status_snooze');
            $table->dropColumn('snooze_until');
        });
    }
};
