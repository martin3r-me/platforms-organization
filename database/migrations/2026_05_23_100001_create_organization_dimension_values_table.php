<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('organization_dimension_values')) return;
        Schema::create('organization_dimension_values', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('dimension_definition_id')
                ->constrained('organization_dimension_definitions')
                ->cascadeOnDelete();
            $table->string('code')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('team_id')->nullable()->constrained('teams')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['dimension_definition_id', 'is_active'], 'dim_val_def_active');
            $table->index(['team_id', 'dimension_definition_id'], 'dim_val_team_def');
            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_dimension_values');
    }
};
