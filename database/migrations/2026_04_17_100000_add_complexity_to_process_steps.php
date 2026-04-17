<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_process_steps', function (Blueprint $table) {
            $table->string('complexity')->nullable()->after('automation_level');
            $table->index(['process_id', 'complexity'], 'idx_steps_process_complexity');
        });
    }

    public function down(): void
    {
        Schema::table('organization_process_steps', function (Blueprint $table) {
            $table->dropIndex('idx_steps_process_complexity');
            $table->dropColumn('complexity');
        });
    }
};
