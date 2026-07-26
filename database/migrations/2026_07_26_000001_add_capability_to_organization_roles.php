<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Capability an der Rolle: jede Rolle trägt EINE Zugriffsstufe (read/write/owner).
 * Jede RoleAssignment erbt sie — kein zweites Feld beim Zuweisen.
 *
 * Backfill in 3 Eimer (Rest = Default 'write'):
 *   manage → Lead-Rollen (sehen + bearbeiten + löschen/verwalten)
 *   read   → Beobachter-/Governance-Rollen (nur sehen)
 *   write  → alle operativen Rollen (Default)
 *
 * Erweiterbar: weitere Stufen kommen additiv über authz_capability (rank-basiert).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('organization_roles', 'capability')) {
            Schema::table('organization_roles', function (Blueprint $table) {
                $table->string('capability', 16)->default('write')->after('vsm_system');
            });
        }

        DB::table('organization_roles')->whereIn('slug', [
            'venture-lead',
            'business-unit-lead',
            'geschaeftsfuehrer',
            'inhaber',
            'project-lead',
            'account-manager',
        ])->update(['capability' => 'manage']);

        DB::table('organization_roles')->whereIn('slug', [
            'aufsichtsrat',
            'beirat',
            'internal-auditor',
            'compliance-officer',
            'quality-officer',
            'risk-officer',
        ])->update(['capability' => 'read']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('organization_roles', 'capability')) {
            Schema::table('organization_roles', function (Blueprint $table) {
                $table->dropColumn('capability');
            });
        }
    }
};
