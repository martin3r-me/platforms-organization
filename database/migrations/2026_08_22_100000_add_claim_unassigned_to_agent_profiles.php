<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Claim-Policy: darf der Agent auch NICHT-zugewiesene (herrenlose Pool-)Issues ziehen, oder nur
 * die ihm explizit zugewiesenen? Default true (Pool erlaubt) — der Daemon sendet es als
 * `allow_unassigned` an den Claim (dev next-issue/next-untriaged).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_agent_profiles', function (Blueprint $table) {
            $table->boolean('claim_unassigned')->default(true)->after('claude_model');
        });
    }

    public function down(): void
    {
        Schema::table('organization_agent_profiles', function (Blueprint $table) {
            $table->dropColumn('claim_unassigned');
        });
    }
};
