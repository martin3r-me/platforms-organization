<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('organization_time_periods');

        Schema::create('organization_time_periods', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('context_type');
            $table->unsignedBigInteger('context_id');
            $table->date('planned_start')->nullable();
            $table->date('planned_end')->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['context_type', 'context_id', 'is_active'], 'org_time_periods_ctx_active_idx');
            $table->index(['context_type', 'context_id'], 'org_time_periods_ctx_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_time_periods');
    }
};
