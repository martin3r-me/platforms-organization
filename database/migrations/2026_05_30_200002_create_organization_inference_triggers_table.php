<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_inference_triggers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('trigger_type', 30);
            // scheduled | snapshot_created | signal_stale | inquiry_answered | threshold_breach | meta_event
            $table->unsignedBigInteger('trigger_reference')->nullable();
            $table->json('prompt_filter')->nullable();
            $table->json('entity_filter')->nullable();
            $table->unsignedInteger('priority')->default(50);
            $table->string('status', 20)->default('pending');
            // pending | processing | completed | failed
            $table->string('debounce_key', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();

            $table->index(['team_id', 'status', 'priority', 'created_at'], 'trig_team_status_prio');
            $table->index(['debounce_key', 'status'], 'trig_debounce');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_inference_triggers');
    }
};
