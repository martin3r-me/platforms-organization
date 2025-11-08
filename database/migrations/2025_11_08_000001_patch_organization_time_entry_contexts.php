<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_time_entry_contexts', function (Blueprint $table) {
            $table->unsignedInteger('depth')->default(0)->after('context_id');
            $table->boolean('is_primary')->default(false)->after('depth');
            $table->boolean('is_root')->default(false)->after('is_primary');
            $table->string('context_label')->nullable()->after('is_root');

            // Unique-Index verhindert Doppelzählung
            $table->unique(['time_entry_id', 'context_type', 'context_id'], 'organization_time_entry_context_unique');

            // Indizes für schnelle Filterung
            $table->index('depth', 'organization_time_entry_context_depth_index');
            $table->index('is_root', 'organization_time_entry_context_root_index');
        });
    }

    public function down(): void
    {
        Schema::table('organization_time_entry_contexts', function (Blueprint $table) {
            $table->dropUnique('organization_time_entry_context_unique');
            $table->dropIndex('organization_time_entry_context_depth_index');
            $table->dropIndex('organization_time_entry_context_root_index');
            $table->dropColumn(['depth', 'is_primary', 'is_root', 'context_label']);
        });
    }
};

