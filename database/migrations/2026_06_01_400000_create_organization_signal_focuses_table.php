<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_signal_focuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('signal_id')->constrained('organization_signals')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('focused_at')->nullable();
            $table->timestamps();

            $table->unique(['signal_id', 'user_id'], 'sigfocus_signal_user_unique');
            $table->index(['user_id', 'focused_at'], 'sigfocus_user_focused');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_signal_focuses');
    }
};
