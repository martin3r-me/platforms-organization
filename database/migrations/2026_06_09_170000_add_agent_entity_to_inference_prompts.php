<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VSM-Phase 1, Schritt 2b: Inference-Prompts an System-Agent-Entities binden.
 *
 * N:1-Relation — viele Prompts pro Agent. Alle Prompts eines Agents
 * sollten dasselbe vsm_system haben (Constraint app-seitig im Modell-Hook).
 *
 * Zusaetzliche Spalten fuer Health-Tracking:
 *  - last_error: Letzte Fehlermeldung (null wenn ok)
 *  - run_count: Gesamtzahl bisheriger Runs
 *
 * Heartbeat-Status (healthy/stale/error) wird aus
 *  - last_evaluated_at (existiert) vs. schedule_interval_hours
 *  - last_error
 * abgeleitet — keine eigene Spalte, computed im Modell.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_signal_inference_prompts', function (Blueprint $table) {
            $table->unsignedBigInteger('agent_entity_id')->nullable()->after('user_id');
            $table->text('last_error')->nullable()->after('last_evaluated_at');
            $table->unsignedInteger('run_count')->default(0)->after('last_error');

            $table->foreign('agent_entity_id', 'inf_prompt_agent_fk')
                ->references('id')->on('organization_entities')
                ->onUpdate('cascade')->onDelete('set null');

            $table->index('agent_entity_id', 'inf_prompt_agent_idx');
        });
    }

    public function down(): void
    {
        Schema::table('organization_signal_inference_prompts', function (Blueprint $table) {
            $table->dropForeign('inf_prompt_agent_fk');
            $table->dropIndex('inf_prompt_agent_idx');
            $table->dropColumn(['agent_entity_id', 'last_error', 'run_count']);
        });
    }
};
