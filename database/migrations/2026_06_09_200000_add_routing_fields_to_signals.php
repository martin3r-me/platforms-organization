<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VSM-Phase 1, Schritt 5b: Routing- und Lifecycle-Felder am Signal.
 *
 * Mit perspective_entity_id (5a) und diesen Feldern wird der Signal-Pfad
 * vollstaendig nachvollziehbar:
 *  - Aus wessen Sicht? (perspective_entity_id, 5a)
 *  - Wer hat es erzeugt? (created_by_agent_entity_id)
 *  - Wer ist gerade zustaendig? (current_owner_entity_id)
 *  - Auf welcher Ebene laeuft es? (vsm_level — kommt aus Prompt initial)
 *  - Quelle und Lifecycle: source_type, escalated_at, deadline_at, acknowledged_at
 *
 * Eskalations-Cron (5c) liest diese Felder und schreibt sie fort.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_signals', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by_agent_entity_id')->nullable()->after('perspective_entity_id');
            $table->unsignedBigInteger('current_owner_entity_id')->nullable()->after('created_by_agent_entity_id');
            $table->string('source_type', 32)->nullable()->after('source')
                ->comment('rule_cron, inference, inference_s3star, human_algedonic, s4_environmental, cross_entity, system_health, aggregation');
            $table->string('vsm_level', 16)->nullable()->after('source_type')
                ->comment('s1, s2, s3, s3_star, s4, s5 — aktuelle Eskalationsebene');
            $table->timestamp('escalated_at')->nullable()->after('current_owner_entity_id');
            $table->timestamp('deadline_at')->nullable()->after('escalated_at');
            $table->timestamp('acknowledged_at')->nullable()->after('deadline_at');

            $table->foreign('created_by_agent_entity_id', 'signal_created_by_fk')
                ->references('id')->on('organization_entities')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->foreign('current_owner_entity_id', 'signal_owner_fk')
                ->references('id')->on('organization_entities')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->index('current_owner_entity_id', 'signal_owner_idx');
            $table->index('deadline_at', 'signal_deadline_idx');
            $table->index(['vsm_level', 'status'], 'signal_vsm_status_idx');
        });

        // Backfill vsm_level aus inference_prompt.vsm_system fuer existierende Signale
        // (wo das eindeutig ist).
        \DB::statement(<<<'SQL'
            UPDATE organization_signals s
            INNER JOIN organization_signal_inference_prompts p ON s.inference_prompt_id = p.id
            SET s.vsm_level = p.vsm_system
            WHERE s.vsm_level IS NULL AND p.vsm_system IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        Schema::table('organization_signals', function (Blueprint $table) {
            try { $table->dropForeign('signal_created_by_fk'); } catch (\Throwable $e) {}
            try { $table->dropForeign('signal_owner_fk'); } catch (\Throwable $e) {}
            try { $table->dropIndex('signal_owner_idx'); } catch (\Throwable $e) {}
            try { $table->dropIndex('signal_deadline_idx'); } catch (\Throwable $e) {}
            try { $table->dropIndex('signal_vsm_status_idx'); } catch (\Throwable $e) {}

            $table->dropColumn([
                'created_by_agent_entity_id',
                'current_owner_entity_id',
                'source_type',
                'vsm_level',
                'escalated_at',
                'deadline_at',
                'acknowledged_at',
            ]);
        });
    }
};
