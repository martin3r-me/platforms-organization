<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Fügt has_key_result Feld zu organization_time_entries hinzu
     * Zeigt an, ob die verbrachte Zeit an einem KeyResult hängt
     */
    public function up(): void
    {
        Schema::table('organization_time_entries', function (Blueprint $table) {
            $table->boolean('has_key_result')->default(false)->after('is_billed');
            $table->index('has_key_result');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_time_entries', function (Blueprint $table) {
            $table->dropIndex(['has_key_result']);
            $table->dropColumn('has_key_result');
        });
    }
};

