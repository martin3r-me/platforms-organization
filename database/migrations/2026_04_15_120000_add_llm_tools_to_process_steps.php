<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_process_steps', function (Blueprint $table) {
            $table->json('llm_tools')->nullable()->after('automation_level');
        });
    }

    public function down(): void
    {
        Schema::table('organization_process_steps', function (Blueprint $table) {
            $table->dropColumn('llm_tools');
        });
    }
};
