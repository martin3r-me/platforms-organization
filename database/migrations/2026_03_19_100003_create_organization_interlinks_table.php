<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_interlinks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('category_id')->constrained('organization_interlink_categories')->restrictOnDelete();
            $table->foreignId('type_id')->constrained('organization_interlink_types')->restrictOnDelete();
            $table->boolean('is_bidirectional')->default(false);
            $table->boolean('is_active')->default(true);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'deleted_at']);
            $table->index(['category_id', 'team_id']);
            $table->index(['type_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_interlinks');
    }
};
