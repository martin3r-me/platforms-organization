<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_time_entries', function (Blueprint $table) {
            $table->string('root_context_type')->nullable()->after('context_id');
            $table->unsignedBigInteger('root_context_id')->nullable()->after('root_context_type');

            // Index für schnelle Root-Abfragen
            $table->index(['team_id', 'root_context_type', 'root_context_id'], 'organization_time_entries_root_index');
        });
    }

    public function down(): void
    {
        Schema::table('organization_time_entries', function (Blueprint $table) {
            $table->dropIndex('organization_time_entries_root_index');
            $table->dropColumn(['root_context_type', 'root_context_id']);
        });
    }
};

