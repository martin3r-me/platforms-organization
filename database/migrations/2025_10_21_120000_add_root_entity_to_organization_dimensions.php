<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add root_entity_id to cost_centers
        Schema::table('organization_cost_centers', function (Blueprint $table) {
            if (!Schema::hasColumn('organization_cost_centers', 'root_entity_id')) {
                $table->unsignedBigInteger('root_entity_id')->nullable()->after('user_id');
                $table->index(['root_entity_id', 'is_active']);
                $table->index(['team_id', 'root_entity_id']);
            }
        });

        // Create vsm_functions table
        if (!Schema::hasTable('organization_vsm_functions')) {
            Schema::create('organization_vsm_functions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('code')->nullable();
                $table->string('name');
                $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedBigInteger('root_entity_id')->nullable(); // NULL = global, X = entity-specific
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['team_id', 'is_active']);
                $table->index(['root_entity_id', 'is_active']);
                $table->index(['team_id', 'root_entity_id']);
                $table->index(['code']);
                $table->index(['uuid']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('organization_cost_centers', function (Blueprint $table) {
            $table->dropIndex(['root_entity_id', 'is_active']);
            $table->dropIndex(['team_id', 'root_entity_id']);
            $table->dropColumn('root_entity_id');
        });

        Schema::dropIfExists('organization_vsm_functions');
    }
};
