<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('organization_cost_center_links')) {
            return;
        }

        Schema::create('organization_cost_center_links', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('cost_center_id')->constrained('organization_cost_centers')->cascadeOnDelete();
            $table->string('linkable_type');
            $table->unsignedBigInteger('linkable_id');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['cost_center_id', 'team_id']);
            $table->index(['linkable_type', 'linkable_id']);
            $table->index(['linkable_type', 'linkable_id', 'cost_center_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_cost_center_links');
    }
};

