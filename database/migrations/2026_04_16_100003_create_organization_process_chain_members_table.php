<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_process_chain_members', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('chain_id')->constrained('organization_process_chains')->cascadeOnDelete();
            $table->foreignId('process_id')->constrained('organization_processes')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('role')->default('middle');
            $table->boolean('is_required')->default(true);
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['chain_id', 'process_id'], 'uq_chain_process');
            $table->index(['chain_id', 'position']);
            $table->index(['chain_id', 'role']);
            $table->index(['team_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_process_chain_members');
    }
};
