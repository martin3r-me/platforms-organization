<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_time_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('context_type');
            $table->unsignedBigInteger('context_id');

            $table->date('work_date');
            $table->unsignedInteger('minutes');
            $table->unsignedInteger('rate_cents')->nullable();
            $table->unsignedInteger('amount_cents')->nullable();
            $table->boolean('is_billed')->default(false);
            $table->json('metadata')->nullable();
            $table->text('note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['context_type', 'context_id'], 'organization_time_entries_context_index');
            $table->index(['team_id', 'work_date']);
            $table->index(['team_id', 'is_billed']);
        });

        Schema::create('organization_time_entry_contexts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('time_entry_id')->constrained('organization_time_entries')->cascadeOnDelete();
            $table->string('context_type');
            $table->unsignedBigInteger('context_id');
            $table->timestamps();

            $table->index(['context_type', 'context_id'], 'organization_time_entry_context_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_time_entry_contexts');
        Schema::dropIfExists('organization_time_entries');
    }
};


