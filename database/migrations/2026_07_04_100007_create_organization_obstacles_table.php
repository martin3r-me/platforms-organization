<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_obstacles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('entity_id')->constrained('organization_entities')->cascadeOnDelete();
            $table->foreignId('focus_area_id')->constrained('organization_focus_areas')->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->text('central_question')->nullable();
            $table->integer('order')->default(0);

            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_obstacles');
    }
};
