<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('organization_entity_name_history', 'change_type')) return;
        Schema::table('organization_entity_name_history', function (Blueprint $table) {
            $table->foreignId('old_parent_entity_id')
                ->nullable()
                ->after('new_code')
                ->constrained('organization_entities')
                ->nullOnDelete();
            $table->foreignId('new_parent_entity_id')
                ->nullable()
                ->after('old_parent_entity_id')
                ->constrained('organization_entities')
                ->nullOnDelete();
            $table->string('change_type', 30)
                ->default('rename')
                ->after('new_parent_entity_id')
                ->comment('rename, move, rename_and_move');
        });
    }

    public function down(): void
    {
        Schema::table('organization_entity_name_history', function (Blueprint $table) {
            $table->dropForeign(['old_parent_entity_id']);
            $table->dropForeign(['new_parent_entity_id']);
            $table->dropColumn(['old_parent_entity_id', 'new_parent_entity_id', 'change_type']);
        });
    }
};
