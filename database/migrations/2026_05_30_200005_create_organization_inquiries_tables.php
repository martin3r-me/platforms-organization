<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->unsignedBigInteger('inference_run_id')->nullable();
            $table->foreignId('inference_prompt_id')->nullable()->constrained('organization_signal_inference_prompts')->nullOnDelete();
            $table->foreignId('entity_id')->constrained('organization_entities')->cascadeOnDelete();
            $table->string('inquiry_type', 30);
            // assessment | clarification | validation | follow_up | context_request | periodic_check_in
            $table->string('recipient_mode', 20)->default('all');
            // all | any | consensus
            $table->json('fields');
            $table->text('context_summary')->nullable();
            $table->string('status', 20)->default('pending');
            // pending | partial | completed | timeout | cancelled
            $table->date('due_date');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('follow_up_signal_id')->nullable();
            $table->json('aggregated_result')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'entity_id', 'status'], 'inq_team_entity_status');
            $table->index(['status', 'due_date'], 'inq_status_due');
        });

        Schema::create('organization_inquiry_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_id')->constrained('organization_inquiries')->cascadeOnDelete();
            $table->foreignId('recipient_entity_id')->constrained('organization_entities')->cascadeOnDelete();
            $table->unsignedBigInteger('recipient_user_id')->nullable();
            $table->string('channel', 20)->default('portal');
            // email | portal | whatsapp
            $table->string('status', 20)->default('pending');
            // pending | sent | answered | timeout
            $table->json('response')->nullable();
            $table->timestamp('response_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();

            $table->index(['inquiry_id', 'status'], 'inqrec_inquiry_status');
            $table->index(['recipient_user_id', 'status'], 'inqrec_user_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_inquiry_recipients');
        Schema::dropIfExists('organization_inquiries');
    }
};
