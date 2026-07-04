<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_strategic_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('entity_id')->constrained('organization_entities')->cascadeOnDelete();

            $table->enum('type', ['mission', 'vision']);
            $table->string('title');
            $table->text('content')->nullable();
            $table->integer('version')->default(1);
            $table->boolean('is_active')->default(false);
            $table->date('valid_from');
            $table->text('change_note')->nullable();

            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_strategic_documents');
    }
};
