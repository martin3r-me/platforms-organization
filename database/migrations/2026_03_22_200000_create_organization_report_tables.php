<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_report_types', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name');
            $table->string('key');
            $table->text('description')->nullable();

            $table->json('hull');
            $table->json('requirements')->nullable();
            $table->json('modules');

            $table->boolean('include_time_entries')->default(false);
            $table->string('frequency')->default('manual');
            $table->string('output_channel')->default('obsidian');
            $table->string('obsidian_folder')->nullable();
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'deleted_at']);
            $table->unique(['team_id', 'key'], 'org_report_types_team_key_unique');
        });

        Schema::create('organization_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();

            $table->foreignId('report_type_id')
                  ->constrained('organization_report_types')
                  ->restrictOnDelete();

            $table->foreignId('entity_id')
                  ->nullable()
                  ->constrained('organization_entities')
                  ->nullOnDelete();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->timestamp('snapshot_at')->nullable();
            $table->longText('generated_content')->nullable();
            $table->string('status')->default('draft');
            $table->string('output_channel')->default('obsidian');
            $table->string('obsidian_path')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'deleted_at']);
            $table->index(['user_id', 'report_type_id']);
            $table->index('entity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_reports');
        Schema::dropIfExists('organization_report_types');
    }
};
