<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_signals', function (Blueprint $table) {
            $table->string('source', 20)->default('rule')->after('uuid');
            $table->foreignId('inference_prompt_id')
                ->nullable()
                ->after('signal_definition_id')
                ->constrained('organization_signal_inference_prompts')
                ->nullOnDelete();

            $table->index('inference_prompt_id');
        });

        // Make signal_definition_id nullable for inference signals
        Schema::table('organization_signals', function (Blueprint $table) {
            $table->unsignedBigInteger('signal_definition_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('organization_signals', function (Blueprint $table) {
            $table->dropForeign(['inference_prompt_id']);
            $table->dropIndex(['inference_prompt_id']);
            $table->dropColumn(['source', 'inference_prompt_id']);
        });

        Schema::table('organization_signals', function (Blueprint $table) {
            $table->unsignedBigInteger('signal_definition_id')->nullable(false)->change();
        });
    }
};
