<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_focus_areas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('entity_id')->constrained('organization_entities')->cascadeOnDelete();
            $table->foreignId('forecast_id')->constrained('organization_forecasts')->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->text('content')->nullable();
            $table->text('central_question_vision_images')->nullable();
            $table->text('central_question_obstacles')->nullable();
            $table->text('central_question_milestones')->nullable();
            $table->integer('order')->default(0);

            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_focus_areas');
    }
};
