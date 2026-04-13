<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_process_snapshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('process_id')->constrained('organization_processes')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('label')->nullable();
            $table->json('snapshot_data');
            $table->json('metrics')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['process_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_process_snapshots');
    }
};
