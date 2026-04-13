<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_process_improvements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('process_id')->constrained('organization_processes')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category');  // cost | quality | speed | risk | standardization
            $table->string('priority')->default('medium');  // low | medium | high | critical
            $table->string('status')->default('identified');  // identified | planned | in_progress | completed | rejected
            $table->text('expected_outcome')->nullable();
            $table->text('actual_outcome')->nullable();
            $table->foreignId('before_snapshot_id')->nullable()->constrained('organization_process_snapshots')->nullOnDelete();
            $table->foreignId('after_snapshot_id')->nullable()->constrained('organization_process_snapshots')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['process_id', 'status']);
            $table->index(['team_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_process_improvements');
    }
};
