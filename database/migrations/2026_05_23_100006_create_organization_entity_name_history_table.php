<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('organization_entity_name_history')) return;
        Schema::create('organization_entity_name_history', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('entity_id')->constrained('organization_entities')->cascadeOnDelete();
            $table->string('old_name')->nullable();
            $table->string('new_name')->nullable();
            $table->string('old_code')->nullable();
            $table->string('new_code')->nullable();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['entity_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_entity_name_history');
    }
};
