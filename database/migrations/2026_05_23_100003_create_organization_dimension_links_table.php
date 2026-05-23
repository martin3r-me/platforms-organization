<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_dimension_links', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('dimension_definition_id')
                ->constrained('organization_dimension_definitions')
                ->cascadeOnDelete();
            $table->foreignId('dimension_value_id')
                ->constrained('organization_dimension_values')
                ->cascadeOnDelete();
            $table->string('linkable_type');
            $table->unsignedBigInteger('linkable_id');
            $table->foreignId('perspective_id')
                ->nullable()
                ->constrained('organization_perspectives')
                ->cascadeOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->foreignId('team_id')->nullable()->constrained('teams')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Unique: one value per dimension per linkable per perspective
            $table->unique(
                ['dimension_definition_id', 'linkable_type', 'linkable_id', 'dimension_value_id', 'perspective_id'],
                'dim_link_unique'
            );

            $table->index(['linkable_type', 'linkable_id'], 'dim_link_linkable');
            $table->index(['dimension_value_id']);
            $table->index(['perspective_id']);
            $table->index(['team_id', 'dimension_definition_id'], 'dim_link_team_def');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_dimension_links');
    }
};
