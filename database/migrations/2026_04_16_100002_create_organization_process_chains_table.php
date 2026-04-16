<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_process_chains', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->string('chain_type')->default('ad_hoc');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_auto_detected')->default(false);
            $table->foreignId('entry_process_id')->nullable()->constrained('organization_processes')->nullOnDelete();
            $table->foreignId('exit_process_id')->nullable()->constrained('organization_processes')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'code']);
            $table->index(['team_id', 'is_active', 'deleted_at']);
            $table->index(['team_id', 'chain_type']);
            $table->index(['team_id', 'is_auto_detected']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_process_chains');
    }
};
