<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_signal_definitions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('pattern_type', 50); // threshold, trend, cross_dimension, ratio
            $table->json('conditions');
            $table->string('scope_type', 50); // all, entity_type, entity_ids, subtree
            $table->json('scope_value')->nullable();
            $table->string('frequency', 20); // every_snapshot, daily, weekly
            $table->string('severity', 20); // info, warning, critical
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'is_active']);
            $table->index(['team_id', 'pattern_type']);
        });

        Schema::create('organization_signals', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('signal_definition_id')->constrained('organization_signal_definitions')->cascadeOnDelete();
            $table->foreignId('entity_id')->constrained('organization_entities')->cascadeOnDelete();
            $table->string('status', 20); // open, acknowledged, resolved, dismissed
            $table->string('severity', 20); // info, warning, critical
            $table->text('message');
            $table->json('trigger_metrics');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'status']);
            $table->index(['entity_id', 'status']);
            $table->index(['signal_definition_id']);
            $table->index(['team_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_signals');
        Schema::dropIfExists('organization_signal_definitions');
    }
};
