<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fundament fuer domaenen-spezifische Agent-Settings (Provider-Registry, #810): eine JSON-Bag
 * fuer Felder, die keine eigene Spalte rechtfertigen (storage=bag im AgentSettingsProvider-
 * Schema). Rein additiv, nullable, keine Datenmigration — bestehende Spalten unveraendert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_agent_profiles', function (Blueprint $table) {
            $table->json('settings')->nullable()->after('github_username');
        });
    }

    public function down(): void
    {
        Schema::table('organization_agent_profiles', function (Blueprint $table) {
            $table->dropColumn('settings');
        });
    }
};
