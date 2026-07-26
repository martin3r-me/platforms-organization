<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rollen-Capability 'owner' → 'manage' (siehe core: die Stufe ist ein
 * Zugriffs-Level, kein Besitz).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('organization_roles', 'capability')) {
            DB::table('organization_roles')->where('capability', 'owner')
                ->update(['capability' => 'manage']);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('organization_roles', 'capability')) {
            DB::table('organization_roles')->where('capability', 'manage')
                ->update(['capability' => 'owner']);
        }
    }
};
