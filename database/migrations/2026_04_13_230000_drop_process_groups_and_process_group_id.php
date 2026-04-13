<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove process_group_id FK + column from processes (if they exist)
        if (Schema::hasColumn('organization_processes', 'process_group_id')) {
            Schema::table('organization_processes', function (Blueprint $table) {
                $table->dropForeign(['process_group_id']);
                $table->dropColumn('process_group_id');
            });
        }

        // Drop the process groups table entirely
        Schema::dropIfExists('organization_process_groups');
    }

    public function down(): void
    {
        // Recreate process groups table
        Schema::create('organization_process_groups', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->unsignedBigInteger('team_id')->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name');
            $table->string('code', 50)->nullable();
            $table->text('description')->nullable();
            $table->string('icon', 100)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        // Re-add process_group_id to processes
        Schema::table('organization_processes', function (Blueprint $table) {
            $table->unsignedBigInteger('process_group_id')->nullable()->after('vsm_system_id');
            $table->foreign('process_group_id')->references('id')->on('organization_process_groups')->nullOnDelete();
        });
    }
};
