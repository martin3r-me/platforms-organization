<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_signal_comments', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('signal_id')->constrained('organization_signals')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('organization_signal_comments')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_context', 50)->default('user');
            $table->text('content');
            $table->timestamps();

            $table->index(['signal_id', 'created_at']);
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_signal_comments');
    }
};
