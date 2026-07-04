<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_milestone_contributions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('milestone_id')->constrained('organization_milestones')->cascadeOnDelete();

            // Polymorphic contributor: e.g. ('okr_objective', 42), ('okr_key_result', 88)
            $table->string('linkable_type');
            $table->unsignedBigInteger('linkable_id');

            // Optional weight/relevance for aggregation. Null = 1 (default).
            $table->unsignedTinyInteger('weight')->nullable();

            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['milestone_id', 'linkable_type', 'linkable_id'], 'milestone_contrib_unique');
            $table->index(['linkable_type', 'linkable_id'], 'milestone_contrib_linkable_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_milestone_contributions');
    }
};
