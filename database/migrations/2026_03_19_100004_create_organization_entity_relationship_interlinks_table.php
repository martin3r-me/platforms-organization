<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('organization_entity_relationship_interlinks')) {
            return;
        }

        Schema::create('organization_entity_relationship_interlinks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('entity_relationship_id');
            $table->unsignedBigInteger('interlink_id');

            $table->foreign('entity_relationship_id', 'org_eri_relationship_id_fk')
                ->references('id')->on('organization_entity_relationships')->cascadeOnDelete();
            $table->foreign('interlink_id', 'org_eri_interlink_id_fk')
                ->references('id')->on('organization_interlinks')->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['entity_relationship_id', 'interlink_id'], 'org_eri_rel_interlink_unique');
            $table->index(['interlink_id'], 'org_eri_interlink_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_entity_relationship_interlinks');
    }
};
