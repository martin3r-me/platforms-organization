<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gebündelter Gehirn-Snapshot am Agent-Profil (Daemon → Org, periodisch gepusht). Damit zeigt die
 * Org das EINZELNE Gehirn host-agnostisch (Kalibrierung voll, Hirn-Zähler, Budget) — die Tiefe, die
 * bisher nur die lokale Leitwarte via Vault-Mount sah. Nicht der ganze Vault live, ein beschränkter
 * Snapshot. Reine JSON-Spalte (flexibel für weitere Kennzahlen ohne Migrations-Churn).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_agent_profiles', function (Blueprint $table) {
            $table->json('brain_snapshot')->nullable();
            $table->timestamp('brain_snapshot_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organization_agent_profiles', function (Blueprint $table) {
            $table->dropColumn(['brain_snapshot', 'brain_snapshot_at']);
        });
    }
};
