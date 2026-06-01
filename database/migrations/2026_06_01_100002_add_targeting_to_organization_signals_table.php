<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_signals', function (Blueprint $table) {
            $table->json('affected_entity_ids')->nullable()->after('snooze_until');
            $table->foreignId('assignee_entity_id')->nullable()->after('affected_entity_ids')
                ->constrained('organization_entities')->nullOnDelete();
            $table->index('assignee_entity_id', 'org_signals_assignee_idx');
        });
    }

    public function down(): void
    {
        Schema::table('organization_signals', function (Blueprint $table) {
            $table->dropIndex('org_signals_assignee_idx');
            $table->dropConstrainedForeignId('assignee_entity_id');
            $table->dropColumn('affected_entity_ids');
        });
    }
};
