<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agent-Identität wandert an die Org-Rolle (Domäne × Stufe), pro-Agent-Runtime-Knöpfe ans Profil.
 *
 * Vorher trug das Agent-Profil `domain` + `stages` — eine parallele Erfindung neben dem
 * bestehenden Rollen-System. Jetzt: eine Rolle IST eine (Domäne × Stufe) — z. B. „Entwickler·
 * Execute". Der Agent hält sie via OrganizationRoleAssignment wie jedes Mitglied; `/profile`
 * leitet Domäne/Stufen aus den Assignments ab. Das Profil behält nur den Runtime-Facet:
 * Governor, aktiv, github_username, Status — PLUS die pro-Agent-Policy `max_story_points` +
 * `claude_model` (die dürfen NICHT an die geteilte Rolle, sonst gälten sie für alle Träger).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_roles', function (Blueprint $table) {
            $table->string('domain', 32)->nullable()->after('vsm_system'); // development|backoffice|helpdesk|assistant|analysis
            $table->string('stage', 32)->nullable()->after('domain');      // triage|execute|learn|signal
            $table->index(['domain', 'stage'], 'org_roles_domain_stage_idx');
        });

        Schema::table('organization_agent_profiles', function (Blueprint $table) {
            $table->dropColumn(['domain', 'stages']);
            $table->unsignedSmallInteger('max_story_points')->nullable()->after('seven_day_burn_margin_pct'); // Claim-Cap (null = kein Cap)
            $table->string('claude_model')->nullable()->after('max_story_points');                           // optional; leer = bestes verfügbares
        });
    }

    public function down(): void
    {
        Schema::table('organization_roles', function (Blueprint $table) {
            $table->dropIndex('org_roles_domain_stage_idx');
            $table->dropColumn(['domain', 'stage']);
        });

        Schema::table('organization_agent_profiles', function (Blueprint $table) {
            $table->dropColumn(['max_story_points', 'claude_model']);
            $table->string('domain')->nullable();
            $table->json('stages')->nullable();
        });
    }
};
