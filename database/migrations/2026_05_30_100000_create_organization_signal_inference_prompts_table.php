<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_signal_inference_prompts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('vsm_system', 10); // s1, s2, s3, s3_star, s4, s5
            $table->text('prompt_template');
            $table->json('data_sources'); // ['snapshots', 'movement', 'correspondence', 'recordings', 'activity_log']
            $table->string('dimension', 50)->nullable(); // 7½-Dimension
            $table->string('default_severity', 20)->default('warning'); // info, warning, critical
            $table->string('scope_type', 50)->default('all'); // all, entity_type, subtree
            $table->json('scope_value')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_evaluated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'is_active']);
            $table->index(['team_id', 'vsm_system']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_signal_inference_prompts');
    }
};
