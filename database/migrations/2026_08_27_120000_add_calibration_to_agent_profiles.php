<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kalibrierungs-Snapshot am Agent-Profil (Daemon → Org, read-only). Jeder Agent meldet im Heartbeat,
 * wie kalibriert er ist (behauptete vs. tatsächliche Confidence). Daraus rechnet die Org den ART-PRIOR,
 * den NEUE Agent-Mitglieder bei der Geburt erben (empirical Bayes) — VSM: die Organisation lernt über
 * ihre Mitglieder und prägt die neuen. Der Prior reist im /api/org/agent/profile-Response zurück.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_agent_profiles', function (Blueprint $table) {
            $table->unsignedInteger('calib_n')->default(0);          // gepaarte (Confidence, Ausgang)-Daten
            $table->decimal('calib_mean_conf', 5, 4)->nullable();    // Ø-behauptete Confidence
            $table->decimal('calib_accuracy', 5, 4)->nullable();     // tatsächliche Trefferquote
            $table->decimal('calib_gap', 6, 4)->nullable();          // Ø-Conf − Trefferquote (>0 = überkonfident)
            $table->timestamp('calib_updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organization_agent_profiles', function (Blueprint $table) {
            $table->dropColumn(['calib_n', 'calib_mean_conf', 'calib_accuracy', 'calib_gap', 'calib_updated_at']);
        });
    }
};
