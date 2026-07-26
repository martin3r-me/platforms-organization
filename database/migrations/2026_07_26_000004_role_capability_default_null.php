<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rollen-Capability ist OPT-IN: Default null = kein Zugriff (wie bei Relations).
 * Vorher war der Default fälschlich 'write' → jede Rolle gab pauschal Schreibrecht.
 *
 * Neu sortiert (bewusst leuchten lassen, Rest bleibt null):
 *   write  → operative Rollen (VSM S1–S3: Wertschöpfung/Koordination/Steuerung)
 *   manage → Lead-/Eigentümer-Rollen (override)
 *   read   → Governance/Aufsicht (override)
 *   null   → alles andere (S4/S5-Nicht-Lead, gremiale Rollen ohne VSM etc.)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Spalten-Default auf null (für neue Rollen). Datenteil unten ist das Wesentliche.
        try {
            Schema::table('organization_roles', function (Blueprint $table) {
                $table->string('capability', 16)->nullable()->default(null)->change();
            });
        } catch (\Throwable $e) {
            // Default-Änderung optional (je nach DB/Treiber) — Neusortierung greift trotzdem.
        }

        if (! Schema::hasColumn('organization_roles', 'capability')) {
            return;
        }

        // 1. Reset: alles null (kein Zugriff).
        DB::table('organization_roles')->update(['capability' => null]);

        // 2. Operative Rollen → write.
        DB::table('organization_roles')
            ->whereIn('vsm_system', ['s1', 's2', 's3'])
            ->update(['capability' => 'write']);

        // 3. Lead-/Eigentümer-Rollen → manage (override).
        DB::table('organization_roles')->whereIn('slug', [
            'venture-lead',
            'business-unit-lead',
            'geschaeftsfuehrer',
            'inhaber',
            'project-lead',
            'account-manager',
        ])->update(['capability' => 'manage']);

        // 4. Governance/Aufsicht → read (override).
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
        // bewusst kein Rückweg auf 'write'-Default.
    }
};
