<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_report_types', function (Blueprint $table) {
            $table->longText('template')->nullable()->after('obsidian_folder');
            $table->json('data_sources')->nullable()->after('template');
            $table->json('ai_sections')->nullable()->after('data_sources');
        });
    }

    public function down(): void
    {
        Schema::table('organization_report_types', function (Blueprint $table) {
            $table->dropColumn(['template', 'data_sources', 'ai_sections']);
        });
    }
};
