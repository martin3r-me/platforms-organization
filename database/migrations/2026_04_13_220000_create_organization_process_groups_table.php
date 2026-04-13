<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_process_groups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'is_active', 'deleted_at']);
        });

        Schema::table('organization_processes', function (Blueprint $table) {
            $table->foreignId('process_group_id')->nullable()->after('vsm_system_id')
                ->constrained('organization_process_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('organization_processes', function (Blueprint $table) {
            $table->dropForeign(['process_group_id']);
            $table->dropColumn('process_group_id');
        });

        Schema::dropIfExists('organization_process_groups');
    }
};
