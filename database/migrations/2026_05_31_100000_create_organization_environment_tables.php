<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_environment_sources', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 255);
            $table->string('source_type', 30)->default('rss');
            $table->string('category', 50);
            $table->json('config');
            $table->unsignedInteger('pull_interval_hours')->default(24);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_pulled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'is_active']);
            $table->index(['is_active', 'last_pulled_at']);
        });

        Schema::create('organization_environment_snapshots', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('source_id')->constrained('organization_environment_sources')->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->json('metrics');
            $table->text('summary');
            $table->json('raw_items')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['source_id', 'snapshot_date']);
            $table->index(['team_id', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_environment_snapshots');
        Schema::dropIfExists('organization_environment_sources');
    }
};
