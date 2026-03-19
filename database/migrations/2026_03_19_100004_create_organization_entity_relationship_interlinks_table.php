<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_entity_relationship_interlinks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('entity_relationship_id')->constrained('organization_entity_relationships')->cascadeOnDelete();
            $table->foreignId('interlink_id')->constrained('organization_interlinks')->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['entity_relationship_id', 'interlink_id']);
            $table->index(['interlink_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_entity_relationship_interlinks');
    }
};
